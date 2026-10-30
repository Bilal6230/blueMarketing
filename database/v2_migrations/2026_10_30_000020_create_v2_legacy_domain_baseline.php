<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V2 ONLY: faithful 56-table business schema baseline (legacy migrations table
 * is intentionally NOT replayed). Schema metadata is human-readable in
 * database/v2_schema/legacy_columns_01..04.json + legacy_constraints.json.
 * This migration is opt-in via --path=database/v2_migrations; never shipped to
 * Laravel's ordinary database/migrations directory.
 */
return new class extends Migration
{
    // Verified against the 2026-10-30 dev dump and app relationship audit.
    // Deliberately excludes polymorphic IDs, business voucher numbers and
    // pending_updates.record_id / stock_movements.source_id.
    // Additional verified relationships are source-controlled in
    // database/v2_schema/v2_verified_extra_fks.json. This SAME registry
    // drives migration FK generation and the V2 historical import planner.
    private function verifiedExtraForeignKeys(): array
    {
        $metadata = json_decode((string) file_get_contents(
            base_path('database/v2_schema/v2_verified_extra_fks.json')
        ), true, 512, JSON_THROW_ON_ERROR);
        if (($metadata['source_sha256'] ?? '') !==
            'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed') {
            throw new RuntimeException('V2 verified foreign-key registry fingerprint mismatch.');
        }
        return $metadata['foreign_keys'];
    }

    // Do not create SoftDeletes on accounting ledgers; immutable corrections
    // belong to reversal batches. Only master data gets a V2 nullable column.
    private const MASTER_SOFT_DELETE = [
        'users', 'leads', 'projects', 'plots', 'labours', 'project_head_subheads',
        'areas', 'zones', 'sites', 'head_accountings', 'subhead_accountings',
        'facings', 'roads',
    ];

    private function guard(): void
    {
        $connection = DB::connection();
        $name = (string) $connection->getDatabaseName();
        if (!str_starts_with($name, 'bluemarketing_v2_') &&
            !($connection->getDriverName() === 'sqlite' && $name === ':memory:')) {
            throw new RuntimeException('Refusing to run 57-table V2 baseline outside an isolated V2 database.');
        }
    }

    private function specifications(): array
    {
        $all = [];
        for ($i = 1; $i <= 4; $i++) {
            $file = base_path('database/v2_schema/legacy_columns_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . '.json');
            $part = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (($part['source_sha256'] ?? '') !==
                'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed') {
                throw new RuntimeException('V2 schema evidence fingerprint changed.');
            }
            foreach ($part['tables'] as $entry) {
                if (isset($all[$entry['table']])) {
                    throw new RuntimeException('Duplicate V2 table definition: ' . $entry['table']);
                }
                if ($entry['table'] !== 'migrations') {
                    $all[$entry['table']] = $entry['columns'];
                }
            }
        }
        if (count($all) !== 56 || isset($all['migrations'])) {
            throw new RuntimeException('V2 business baseline requires exactly 56 distinct tables.');
        }
        $spec = json_decode(
            (string) file_get_contents(base_path('database/v2_schema/legacy_constraints.json')),
            true, 512, JSON_THROW_ON_ERROR
        );
        if (($spec['foreign_keys'] ?? null) !== 40 ||
            ($spec['source_sha256'] ?? '') !==
                'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed') {
            throw new RuntimeException('V2 legacy key evidence differs from verified snapshot.');
        }
        return [$all, $spec['table_constraints']];
    }

    private function foreignKeys(array $constraints): array
    {
        $foreign = [];
        foreach ($constraints as $name => $meta) {
            foreach ($meta['foreign'] ?? [] as $fk) {
                $foreign[$name][$fk['column']] = [
                    'table' => $fk['references'], 'column' => $fk['ref_column'],
                    'name' => $fk['name'], 'on_delete' => $fk['on_delete'] ?? 'RESTRICT',
                ];
            }
        }
        foreach ($this->verifiedExtraForeignKeys() as $table => $columns) {
            foreach ($columns as $column => $parent) {
                if (isset($foreign[$table][$column])) {
                    throw new RuntimeException('V2 additional FK duplicates a declared FK.');
                }
                $foreign[$table][$column] = [
                    'table' => $parent, 'column' => 'id',
                    'name' => 'v2_' . $table . '_' . $column . '_fk',
                    'on_delete' => 'RESTRICT',
                ];
            }
        }
        return $foreign;
    }

    private function creationOrder(array $definitions, array $foreign): array
    {
        $todo = array_fill_keys(array_keys($definitions), true);
        $order = [];
        while ($todo !== []) {
            $ready = [];
            foreach (array_keys($todo) as $table) {
                $deps = array_unique(array_map(
                    static fn (array $f) => $f['table'], $foreign[$table] ?? []
                ));
                if (array_diff($deps, array_keys($definitions), ['banks']) !== []) {
                    throw new RuntimeException('Unknown V2 source FK parent for ' . $table);
                }
                if (array_intersect($deps, array_keys($todo)) === []) {
                    $ready[] = $table;
                }
            }
            if ($ready === []) {
                throw new RuntimeException('V2 foreign key graph contains a cycle; review source links.');
            }
            sort($ready, SORT_STRING);
            foreach ($ready as $table) {
                $order[] = $table;
                unset($todo[$table]);
            }
        }
        return $order;
    }

    private function defineColumn(Blueprint $table, string $name, string $definition, bool $isId, bool $isForeign): void
    {
        if ($isId) {
            $table->id();
            return;
        }
        $nullable = !str_contains(strtoupper($definition), 'NOT NULL');
        $unsigned = $isForeign || str_contains(strtoupper($definition), 'UNSIGNED');
        // Lowercasing the complete type token also changed quoted ENUM
        // labels (IN->in, Supplier->supplier) and silently altered source
        // values on first MySQL import. Preserve literal arguments exactly;
        // only the SQL type identifier itself is case-insensitive.
        $sqlType = (string) strtok($definition, ' ');
        if (!preg_match('/^([a-z]+)(?:\\((.*)\\))?$/i', $sqlType, $m)) {
            throw new RuntimeException('Unsupported V2 legacy SQL type for ' . $name . ': ' . $definition);
        }
        $base = strtolower($m[1]);
        $args = $m[2] ?? '';
        if ($isForeign) {
            $column = $table->unsignedBigInteger($name);
        } else {
            $column = match ($base) {
                'bigint' => $unsigned ? $table->unsignedBigInteger($name) : $table->bigInteger($name),
                'int' => $unsigned ? $table->unsignedInteger($name) : $table->integer($name),
                'tinyint' => $unsigned ? $table->unsignedTinyInteger($name) : $table->tinyInteger($name),
                'smallint' => $unsigned ? $table->unsignedSmallInteger($name) : $table->smallInteger($name),
                'varchar', 'char' => $table->string($name, (int) $args),
                'decimal' => $table->decimal($name, ...(array_map('intval', explode(',', $args)))),
                'float', 'double' => $table->double($name),
                'text' => $table->text($name),
                'mediumtext' => $table->mediumText($name),
                'longtext' => $table->longText($name),
                'date' => $table->date($name),
                'timestamp' => $table->timestamp($name),
                'datetime' => $table->dateTime($name),
                'json' => $table->json($name),
                'enum' => $table->enum($name, $this->enumValues($args)),
                default => throw new RuntimeException('Unsupported V2 legacy SQL type: ' . $base),
            };
        }
        if ($nullable) {
            $column->nullable();
        }
        if (preg_match("/DEFAULT\\s+'((?:[^']|'')*)'/i", $definition, $default)) {
            $column->default(str_replace("''", "'", $default[1]));
        } elseif (preg_match('/DEFAULT\\s+(-?[0-9]+(?:\\.[0-9]+)?)/i', $definition, $default)) {
            $column->default($default[1]);
        } elseif (stripos($definition, 'DEFAULT current_timestamp()') !== false) {
            $column->useCurrent();
        }
    }

    private function enumValues(string $definition): array
    {
        preg_match_all("/'((?:[^']|'')*)'/", $definition, $m);
        if ($m[1] === []) {
            throw new RuntimeException('Empty V2 legacy enum');
        }
        return array_map(static fn ($v) => str_replace("''", "'", $v), $m[1]);
    }

    public function up(): void
    {
        $this->guard();
        if (!Schema::hasTable('banks')) {
            throw new RuntimeException('V2 bank lookup migration must run first.');
        }
        [$definitions, $constraints] = $this->specifications();
        $foreign = $this->foreignKeys($constraints);
        $order = $this->creationOrder($definitions, $foreign);
        foreach ($order as $name) {
            if (Schema::hasTable($name)) {
                throw new RuntimeException('Refusing V2 baseline overwrite of ' . $name);
            }
        }
        // The first V2 import must preserve original IDs; BIGINT ids ensure
        // matching typed FK widths even for legacy INT/signed source columns.
        foreach ($order as $name) {
            Schema::create($name, function (Blueprint $table) use ($name, $definitions, $constraints, $foreign): void {
                $meta = $constraints[$name] ?? [];
                $primary = $meta['primary'] ?? [];
                foreach ($definitions[$name] as [$columnName, $sqlType]) {
                    $this->defineColumn(
                        $table, $columnName, $sqlType,
                        $columnName === 'id' && $primary === ['id'],
                        isset($foreign[$name][$columnName])
                    );
                }
                if (!in_array('deleted_at', array_column($definitions[$name], 0), true) &&
                    in_array($name, self::MASTER_SOFT_DELETE, true)) {
                    $table->softDeletes();
                }
                if ($name === 'sites') {
                    // Keep original misleading legacy value as archived column;
                    // verified V2 importer will map it into a real project account.
                    $table->unsignedBigInteger('project_head_subhead_id')->nullable();
                }
                if ($name === 'pending_updates') {
                    $table->text('remarks')->nullable();
                }
                if ($primary !== [] && $primary !== ['id']) {
                    $table->primary($primary, 'v2_' . $name . '_primary');
                }
                foreach ($meta['unique'] ?? [] as $u) {
                    $table->unique($u['cols'], $u['name']);
                }
                foreach ($meta['indexes'] ?? [] as $index) {
                    $table->index($index['cols'], $index['name']);
                }
                foreach ($foreign[$name] ?? [] as $column => $fk) {
                    // Audit/history entities never silently cascade their
                    // persisted financial evidence in the clean V2 schema.
                    $table->foreign($column, $fk['name'])
                        ->references($fk['column'])->on($fk['table'])
                        ->restrictOnDelete();
                }
                if ($name === 'sites') {
                    $table->foreign('project_head_subhead_id', 'v2_site_project_account_fk')
                        ->references('id')->on('project_head_subheads')->restrictOnDelete();
                }
            });
        }

        // Preserve all 14 old Laravel migration-history rows as BUSINESS
        // EVIDENCE, never overwrite V2's own framework migrations table.
        Schema::create('legacy_migration_history', function (Blueprint $table): void {
            $table->id();
            $table->string('migration', 255);
            $table->integer('batch');
        });
    }

    public function down(): void
    {
        $this->guard();
        Schema::dropIfExists('legacy_migration_history');
        [$definitions, $constraints] = $this->specifications();
        foreach (array_reverse($this->creationOrder($definitions, $this->foreignKeys($constraints))) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
