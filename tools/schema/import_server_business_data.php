<?php
declare(strict_types=1);

/**
 * Controlled, local-only server data import.
 *
 * Default: READ-ONLY preflight.
 * Run: php tools/schema/import_server_business_data.php
 * Run: php tools/schema/import_server_business_data.php --execute
 *
 * Source: bluemarketing_server_source (latest server SQL imported as a separate DB)
 * Permissions/grants: bluenew_clean_final
 * Target: bluemarketing_live_final (freshly migrated schema)
 *
 * This script NEVER changes the source or the tested database.
 * It does not run migrations, DROP/TRUNCATE, recalculate finance postings,
 * or import historical migration rows. For imported tables, IDs are retained.
 */
if (PHP_SAPI !== 'cli' || count(array_diff(array_slice($argv, 1), ['--execute'])) > 0
    || count($argv) > 2) {
    fwrite(STDERR, "Usage: php tools/schema/import_server_business_data.php [--execute]\n");
    exit(2);
}
$execute = in_array('--execute', $argv, true);

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

const SOURCE_DB = 'bluemarketing_server_source';
const PERMISSION_DB = 'bluenew_clean_final';
const TARGET_DB = 'bluemarketing_live_final';

function failImport(string $reason): never {
    throw new RuntimeException('IMPORT REFUSED: '.$reason);
}
function identifier(string $name): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) failImport('Unsafe SQL identifier');
    return '`'.$name.'`';
}
function qualified(string $db, string $table): string {
    return identifier($db).'.'.identifier($table);
}
function info(string $sql, array $bindings = []): array {
    return DB::select($sql, $bindings);
}
function names(string $database): array {
    return array_map(static fn($r) => (string)$r->table_name, info(
        "SELECT TABLE_NAME AS table_name FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'", [$database]
    ));
}
function columns(string $database, string $table): array {
    return array_map(static fn($r) => (string)$r->col_name, info(
        "SELECT COLUMN_NAME AS col_name FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION", [$database,$table]
    ));
}
function countRows(string $database, string $table): int {
    return (int) DB::table($database.'.'.$table)->count();
}
function roleIds(string $db): array {
    $map = [];
    foreach (info('SELECT id,name,guard_name FROM '.qualified($db,'roles')) as $row) {
        $map[(string)$row->guard_name."\0".(string)$row->name] = (int)$row->id;
    }
    return $map;
}
function permissionIds(string $db): array {
    $map=[];
    foreach (info('SELECT id,name,guard_name FROM '.qualified($db,'permissions').' WHERE deleted_at IS NULL') as $row) {
        $key = (string)$row->guard_name."\0".(string)$row->name;
        if (isset($map[$key])) failImport("Duplicate active permission name/guard in $db");
        $map[$key] = (int)$row->id;
    }
    return $map;
}
function grants(string $db): array {
    return info('SELECT r.name AS role_name,r.guard_name AS role_guard,p.name AS permission_name,
        p.guard_name AS permission_guard
        FROM '.qualified($db,'role_has_permissions').' g
        JOIN '.qualified($db,'roles').' r ON r.id=g.role_id
        JOIN '.qualified($db,'permissions').' p ON p.id=g.permission_id
        WHERE (g.deleted_at IS NULL) AND (r.deleted_at IS NULL) AND (p.deleted_at IS NULL)');
}
function fkGroups(): array {
    $rows = info("SELECT k.CONSTRAINT_NAME AS fk_name,k.TABLE_NAME AS child_table,
        k.COLUMN_NAME AS child_column,k.REFERENCED_TABLE_NAME AS parent_table,
        k.REFERENCED_COLUMN_NAME AS parent_column
        FROM information_schema.KEY_COLUMN_USAGE k
        WHERE k.TABLE_SCHEMA=? AND k.REFERENCED_TABLE_SCHEMA=?
          AND k.REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION", [TARGET_DB,TARGET_DB]);
    $groups=[];
    foreach ($rows as $r) {
        $key=(string)$r->child_table."\0".(string)$r->fk_name;
        if (!isset($groups[$key])) $groups[$key]=[
            'name'=>$r->fk_name,'child'=>$r->child_table,'parent'=>$r->parent_table,
            'pairs'=>[]
        ];
        $groups[$key]['pairs'][]=[$r->child_column,$r->parent_column];
    }
    return array_values($groups);
}
function validateForeignKeys(array $groups): void {
    $bad=[];
    foreach ($groups as $fk) {
        $where=[];$join=[];
        foreach ($fk['pairs'] as [$child,$parent]) {
            $where[]='c.'.identifier($child).' IS NOT NULL';
            $join[]='c.'.identifier($child).' = p.'.identifier($parent);
        }
        $firstParent=$fk['pairs'][0][1];
        $sql='SELECT COUNT(*) AS n FROM '.qualified(TARGET_DB,$fk['child']).' c
            LEFT JOIN '.qualified(TARGET_DB,$fk['parent']).' p ON '.implode(' AND ',$join).'
            WHERE '.implode(' AND ',$where).' AND p.'.identifier($firstParent).' IS NULL';
        $n=(int)info($sql)[0]->n;
        if ($n) $bad[]=$fk['child'].'.'.$fk['name'].': '.$n.' orphan rows';
    }
    if ($bad) failImport("Broken foreign keys:\n".implode("\n",$bad));
}

try {
    $db=DB::connection();
    $cfg=config('database.connections.'.config('database.default'));
    $host=strtolower((string)($cfg['host']??''));
    if (!app()->environment('local') || $db->getDriverName()!=='mysql'
        || $db->getDatabaseName()!==TARGET_DB
        || !in_array($host,['localhost','127.0.0.1','::1'],true)
        || !empty($cfg['url'])) failImport('Only local MySQL targeting '.TARGET_DB.' is allowed');

    foreach ([SOURCE_DB,PERMISSION_DB,TARGET_DB] as $database) {
        if (!info('SELECT SCHEMA_NAME AS db_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$database])) {
            failImport('Database missing: '.$database);
        }
    }
    $sourceTables=names(SOURCE_DB);
    $targetTables=names(TARGET_DB);
    foreach (['users','projects','leads','bookings','ledgers','customer_ledger',
              'journal_vouchers','journal_voucher_details','roles','model_has_roles'] as $table) {
        if (!in_array($table,$sourceTables,true) || !in_array($table,$targetTables,true)) {
            failImport('Required business table missing: '.$table);
        }
    }
    foreach (['permissions','roles','role_has_permissions'] as $table) {
        if (!in_array($table,names(PERMISSION_DB),true)) failImport('Tested permission table missing: '.$table);
    }
    foreach (['migrations','permissions','roles','role_has_permissions','model_has_permissions',
               'model_has_roles'] as $table) {
        if (!in_array($table,$targetTables,true)) failImport('Missing target table: '.$table);
    }

    // Only the original source can supply users. Local test user 53 is deliberately excluded.
    foreach (['users','projects','leads','bookings','ledgers','customer_ledger',
              'journal_vouchers','journal_voucher_details','roles','model_has_roles',
              'role_has_permissions','model_has_permissions'] as $table) {
        if (countRows(TARGET_DB,$table)!==0) failImport('Target already has rows in '.$table);
    }

    $excluded=['migrations','permissions','role_has_permissions','model_has_permissions',
               'failed_jobs','password_resets','personal_access_tokens'];
    $copyPlan=[];
    foreach ($sourceTables as $table) {
        if (in_array($table,$excluded,true)) continue;
        $n=countRows(SOURCE_DB,$table);
        if (!in_array($table,$targetTables,true)) {
            if ($n) failImport('Source-only table contains '.$n.' rows: '.$table);
            continue;
        }
        if (!$n) continue;
        if (countRows(TARGET_DB,$table)) failImport('Nonempty destination: '.$table);
        $src=columns(SOURCE_DB,$table);
        $dst=columns(TARGET_DB,$table);
        $dropped=array_values(array_diff($src,$dst));
        if ($dropped && !($table==='labours' && $dropped===['project_id'])) {
            failImport('Unmapped source columns in '.$table.': '.implode(',',$dropped));
        }
        $usable=array_values(array_intersect($src,$dst));
        if (!$usable) failImport('No transferable columns: '.$table);
        $copyPlan[$table]=['rows'=>$n,'columns'=>$usable];
    }

    // Check the local role setup maps by business identity, not numeric IDs.
    $serverRoles=roleIds(SOURCE_DB);
    $localRoles=roleIds(PERMISSION_DB);
    foreach ($serverRoles as $name=>$id) {
        if (!isset($localRoles[$name])) failImport('Server role is absent from tested permissions: '.str_replace("\0",' / ',$name));
    }
    $localPerms=permissionIds(PERMISSION_DB);
    $grants=grants(PERMISSION_DB);
    foreach ($grants as $grant) {
        $roleKey=$grant->role_guard."\0".$grant->role_name;
        $permKey=$grant->permission_guard."\0".$grant->permission_name;
        if (!isset($localPerms[$permKey])) failImport('Permission grant maps to missing permission');
        // Local-only roles are not created: user explicitly chose server's nine roles.
        if (!isset($serverRoles[$roleKey])) failImport('Tested grant references role absent on server: '.$grant->role_name);
    }
    $fk=fkGroups();
    echo ($execute?'EXECUTE':'READ-ONLY PREFLIGHT')." | Source: ".SOURCE_DB.
        " | Target: ".TARGET_DB."\n";
    echo 'Business tables to copy: '.count($copyPlan).
        '; source users: '.countRows(SOURCE_DB,'users').
        '; local active permissions: '.count($localPerms).
        '; tested role grants: '.count($grants).
        '; target FK constraints: '.count($fk)."\n";
    foreach ($copyPlan as $table=>$plan) echo "  $table: ".$plan['rows']." rows\n";
    if (!$execute) {
        echo "PREFLIGHT PASSED. No data written. Run with --execute to import.\n";
        exit(0);
    }

    $pdo=$db->getPdo();
    // The copied historical tables are mutually dependent. FK checks are
    // temporarily off only in this connection; every FK is explicitly
    // validated against final imported data BEFORE committing.
    $oldFk=(int)info('SELECT @@SESSION.foreign_key_checks AS v')[0]->v;
    if ($oldFk!==1) failImport('Session foreign_key_checks is already disabled');
    $pdo->beginTransaction();
    try {
        $db->statement('SET SESSION foreign_key_checks = 0');
        foreach ($copyPlan as $table=>$plan) {
            $columns=implode(',',array_map('identifier',$plan['columns']));
            $sql='INSERT INTO '.qualified(TARGET_DB,$table).' ('.$columns.')
                SELECT '.$columns.' FROM '.qualified(SOURCE_DB,$table);
            $written=$db->affectingStatement($sql);
            if ($written!==$plan['rows']) failImport("$table copied $written rows, expected ".$plan['rows']);
            echo "COPIED $table: $written\n";
        }
        // Fresh migration permissions remain. Add definitions from tested DB by natural key.
        $active=permissionIds(TARGET_DB);
        $permissionRows=info('SELECT * FROM '.qualified(PERMISSION_DB,'permissions').' WHERE deleted_at IS NULL');
        $permittedColumns=array_values(array_intersect(columns(PERMISSION_DB,'permissions'),columns(TARGET_DB,'permissions')));
        $insertColumns=array_values(array_diff($permittedColumns,['id']));
        foreach ($permissionRows as $p) {
            $key=$p->guard_name."\0".$p->name;
            if (isset($active[$key])) continue;
            $data=[];
            foreach ($insertColumns as $column) $data[$column]=$p->$column;
            $newId=DB::table(TARGET_DB.'.permissions')->insertGetId($data);
            $active[$key]=(int)$newId;
        }
        if (count($active)!==count($localPerms)) {
            failImport('Permission sets differ: local='.count($localPerms).', destination='.count($active).
                '; verify target migration-only permission names');
        }
        foreach ($localPerms as $key=>$id) {
            if (!isset($active[$key])) failImport('Missing local permission in target: '.$key);
        }
        $targetRoles=roleIds(TARGET_DB);
        foreach ($grants as $grant) {
            $roleKey=$grant->role_guard."\0".$grant->role_name;
            $permKey=$grant->permission_guard."\0".$grant->permission_name;
            DB::table(TARGET_DB.'.role_has_permissions')->insert([
                'role_id'=>$targetRoles[$roleKey],
                'permission_id'=>$active[$permKey],
            ]);
        }
        if (countRows(TARGET_DB,'role_has_permissions')!==count($grants)) {
            failImport('Role grant row count mismatch');
        }
        foreach ($copyPlan as $table=>$plan) {
            if (countRows(TARGET_DB,$table)!==$plan['rows']) failImport('Row count mismatch: '.$table);
        }
        validateForeignKeys($fk);
        $db->statement('SET SESSION foreign_key_checks = 1');
        $pdo->commit();
        echo "IMPORT COMMITTED. All destination foreign keys validated.\n";
        echo "Next: run finance:audit-voucher-schema --require-fks and finance:audit-posting-balances.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $db->statement('SET SESSION foreign_key_checks = 1');
    }
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage()."\n");
    exit(1);
}
