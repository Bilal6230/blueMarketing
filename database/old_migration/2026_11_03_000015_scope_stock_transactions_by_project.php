<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['stock_entries', 'stock_bills', 'stock_movements', 'stock_entry_lines', 'stock_bill_lines', 'stock_party_project_accounts', 'stock_items'] as $table) {
            if (!Schema::hasTable($table)) throw new LogicException("Project stock migration requires {$table}.");
        }

        foreach (['stock_entries', 'stock_bills', 'stock_movements'] as $name) {
            if (!Schema::hasColumn($name, 'project_id')) {
                Schema::table($name, function (Blueprint $table): void { $table->unsignedBigInteger('project_id')->nullable(); });
            }
        }

        // Assign history only where the configured party identifies one project unambiguously.
        DB::statement("UPDATE stock_entries e JOIN (SELECT stock_party_id, MIN(project_id) project_id FROM stock_party_project_accounts GROUP BY stock_party_id HAVING COUNT(*) = 1) m ON m.stock_party_id=e.party_id SET e.project_id=m.project_id WHERE e.project_id IS NULL");
        DB::statement("UPDATE stock_bills b LEFT JOIN stock_entries e ON e.id=b.entry_id JOIN (SELECT stock_party_id, MIN(project_id) project_id FROM stock_party_project_accounts GROUP BY stock_party_id HAVING COUNT(*) = 1) m ON m.stock_party_id=b.party_id SET b.project_id=m.project_id WHERE b.project_id IS NULL AND (b.entry_id IS NULL OR e.project_id=m.project_id)");
        DB::statement("UPDATE stock_movements m JOIN stock_entries e ON m.source_type='entry' AND e.id=m.source_id SET m.project_id=e.project_id WHERE m.project_id IS NULL AND e.project_id IS NOT NULL AND (m.party_id IS NULL OR EXISTS (SELECT 1 FROM stock_party_project_accounts p WHERE p.project_id=e.project_id AND p.stock_party_id=m.party_id))");
        DB::statement("UPDATE stock_movements m JOIN stock_bills b ON m.source_type='bill' AND b.id=m.source_id SET m.project_id=b.project_id WHERE m.project_id IS NULL AND b.project_id IS NOT NULL AND (m.party_id IS NULL OR EXISTS (SELECT 1 FROM stock_party_project_accounts p WHERE p.project_id=b.project_id AND p.stock_party_id=m.party_id))");

        foreach (['stock_entries', 'stock_bills'] as $name) {
            if (!Schema::hasIndex($name, $name.'_id_project_unique')) {
                Schema::table($name, function (Blueprint $table) use ($name): void { $table->unique(['id', 'project_id'], $name.'_id_project_unique'); });
            }
        }
        foreach (['stock_entry_lines' => ['entry_id', 'stock_entries'], 'stock_bill_lines' => ['bill_id', 'stock_bills']] as $name => [$fk, $parent]) {
            if (!Schema::hasColumn($name, 'project_id')) {
                Schema::table($name, function (Blueprint $table): void { $table->unsignedBigInteger('project_id')->nullable(); });
            }
            DB::statement("UPDATE {$name} line JOIN {$parent} parent ON parent.id=line.{$fk} SET line.project_id=parent.project_id WHERE line.project_id IS NULL AND parent.project_id IS NOT NULL");
            if (!Schema::hasIndex($name, $name.'_id_project_unique')) {
                Schema::table($name, function (Blueprint $table) use ($name): void { $table->unique(['id', 'project_id'], $name.'_id_project_unique'); });
            }
            if (!Schema::hasIndex($name, $name.'_project_parent_idx')) {
                Schema::table($name, function (Blueprint $table) use ($fk, $name): void { $table->index(['project_id', $fk], $name.'_project_parent_idx'); });
            }
        }

        foreach (['stock_entries', 'stock_bills', 'stock_movements'] as $name) {
            if (!Schema::hasIndex($name, $name.'_project_lookup_idx')) {
                Schema::table($name, function (Blueprint $table) use ($name): void { $table->index(['project_id', 'id'], $name.'_project_lookup_idx'); });
            }
        }

        if (!Schema::hasTable('stock_project_item_balances')) {
            Schema::create('stock_project_item_balances', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('item_id');
                $table->decimal('on_hand_qty', 18, 3)->default(0);
                $table->timestamps();
                $table->primary(['project_id', 'item_id'], 'stock_project_item_balance_pk');
                $table->foreign('project_id', 'stock_project_balance_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->foreign('item_id', 'stock_project_balance_item_fk')->references('id')->on('stock_items')->restrictOnDelete();
            });
        }
        DB::statement("INSERT INTO stock_project_item_balances (project_id,item_id,on_hand_qty,created_at,updated_at) SELECT project_id,item_id,SUM(CASE WHEN direction='IN' THEN qty ELSE -qty END),NOW(),NOW() FROM stock_movements WHERE project_id IS NOT NULL GROUP BY project_id,item_id ON DUPLICATE KEY UPDATE on_hand_qty=VALUES(on_hand_qty),updated_at=VALUES(updated_at)");

        // Replace cascading/nulling relations with project-aware restrictive foreign keys.
        $this->dropForeignKeyIfPresent('stock_bills', 'stock_bills_entry_id_foreign');
        $this->dropForeignKeyIfPresent('stock_entry_lines', 'stock_entry_lines_entry_id_foreign');
        $this->dropForeignKeyIfPresent('stock_bill_lines', 'stock_bill_lines_bill_id_foreign');
        $this->addForeignKey('stock_entries', ['project_id','party_id'], 'stock_party_project_accounts', ['project_id','stock_party_id'], 'stock_entries_party_project_fk');
        $this->addForeignKey('stock_bills', ['project_id','party_id'], 'stock_party_project_accounts', ['project_id','stock_party_id'], 'stock_bills_party_project_fk');
        $this->addForeignKey('stock_movements', ['project_id','party_id'], 'stock_party_project_accounts', ['project_id','stock_party_id'], 'stock_movements_party_project_fk');
        $this->addForeignKey('stock_bills', ['entry_id','project_id'], 'stock_entries', ['id','project_id'], 'stock_bills_entry_project_fk');
        $this->addForeignKey('stock_entry_lines', ['entry_id','project_id'], 'stock_entries', ['id','project_id'], 'stock_entry_lines_entry_project_fk');
        $this->addForeignKey('stock_bill_lines', ['bill_id','project_id'], 'stock_bills', ['id','project_id'], 'stock_bill_lines_bill_project_fk');
        foreach (['stock_entries','stock_bills','stock_movements','stock_entry_lines','stock_bill_lines'] as $name) {
            $this->addForeignKey($name, ['project_id'], 'projects', ['id'], $name.'_project_fk');
        }
    }

    public function down(): void
    {
        // Keep project attribution and balances; rolling back must not erase stock history.
    }

    private function addForeignKey(string $table, array $columns, string $target, array $targetColumns, string $name): void
    {
        if ($this->foreignKeyExists($table, $name)) return;
        Schema::table($table, function (Blueprint $blueprint) use ($columns,$target,$targetColumns,$name): void {
            $blueprint->foreign($columns, $name)->references($targetColumns)->on($target)->restrictOnDelete();
        });
    }

    private function dropForeignKeyIfPresent(string $table, string $name): void
    {
        if (!$this->foreignKeyExists($table, $name)) return;
        Schema::table($table, function (Blueprint $blueprint) use ($name): void { $blueprint->dropForeign($name); });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $name)->exists();
    }
};
