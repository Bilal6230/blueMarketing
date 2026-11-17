<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('labour_ledgers') || !Schema::hasTable('labours') || !Schema::hasTable('contractors') || !Schema::hasTable('customer_ledger')) {
            throw new LogicException('Payroll linkage requires labour_ledgers, labours, contractors, and customer_ledger tables.');
        }

        if (!Schema::hasIndex('labours', 'labours_id_project_unique')) {
            Schema::table('labours', function (Blueprint $table): void {
                $table->unique(['id', 'project_id'], 'labours_id_project_unique');
            });
        }

        if (!Schema::hasColumn('labour_ledgers', 'project_id')) {
            Schema::table('labour_ledgers', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id')->nullable()->after('labour_id');
            });
        }

        if (DB::table('labour_ledgers')->whereNull('project_id')->exists()) {
            DB::statement('UPDATE labour_ledgers AS ll INNER JOIN labours AS l ON l.id = ll.labour_id SET ll.project_id = l.project_id WHERE ll.project_id IS NULL');
        }
        if (DB::table('labour_ledgers')->whereNull('project_id')->exists()) {
            throw new LogicException('Cannot scope labour ledger rows: one or more rows have no matching project-owned labour record. Reconcile those rows before retrying.');
        }
        $misattributed = DB::table('labour_ledgers as ll')->leftJoin('labours as l', 'l.id', '=', 'll.labour_id')
            ->where(function ($query): void {
                $query->whereNull('l.id')->orWhereNull('l.project_id')
                    ->orWhereColumn('ll.project_id', '<>', 'l.project_id');
            })->exists();
        if ($misattributed) {
            throw new LogicException('Cannot scope labour ledger rows: a labour master is missing, unscoped, or belongs to another project. Reconcile those records before retrying.');
        }
        Schema::table('labour_ledgers', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });

        if (!Schema::hasColumn('labour_ledgers', 'contractor_id')) {
            Schema::table('labour_ledgers', function (Blueprint $table): void {
                $table->unsignedBigInteger('contractor_id')->nullable()->after('project_id');
            });
        }

        if (!Schema::hasIndex('labour_ledgers', 'labour_ledgers_project_labour_idx')) {
            Schema::table('labour_ledgers', function (Blueprint $table): void {
                $table->index(['project_id', 'labour_id'], 'labour_ledgers_project_labour_idx');
            });
        }
        if (!Schema::hasIndex('labour_ledgers', 'labour_ledgers_project_contractor_idx')) {
            Schema::table('labour_ledgers', function (Blueprint $table): void {
                $table->index(['project_id', 'contractor_id'], 'labour_ledgers_project_contractor_idx');
            });
        }

        // Replace the legacy cascading worker FK with restrictive project-aware references.
        $legacyWorkerFks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'labour_ledgers' AND COLUMN_NAME = 'labour_id' AND REFERENCED_TABLE_NAME = 'labours'");
        foreach ($legacyWorkerFks as $foreignKey) {
            Schema::table('labour_ledgers', function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey->CONSTRAINT_NAME);
            });
        }

        if (!$this->foreignKeyExists('labour_ledgers', 'labour_ledgers_project_fk')) {
            Schema::table('labour_ledgers', function (Blueprint $table): void {
                $table->foreign('project_id', 'labour_ledgers_project_fk')->references('id')->on('projects')->restrictOnDelete();
            });
        }
        if (!$this->foreignKeyExists('labour_ledgers', 'labour_ledgers_labour_project_fk')) {
            Schema::table('labour_ledgers', function (Blueprint $table): void {
                $table->foreign(['labour_id', 'project_id'], 'labour_ledgers_labour_project_fk')
                    ->references(['id', 'project_id'])->on('labours')->restrictOnDelete();
            });
        }
        if (!$this->foreignKeyExists('labour_ledgers', 'labour_ledgers_contractor_project_fk')) {
            Schema::table('labour_ledgers', function (Blueprint $table): void {
                $table->foreign(['contractor_id', 'project_id'], 'labour_ledgers_contractor_project_fk')
                    ->references(['id', 'project_id'])->on('contractors')->restrictOnDelete();
            });
        }

        if (!Schema::hasColumn('customer_ledger', 'contractor_id')) {
            Schema::table('customer_ledger', function (Blueprint $table): void {
                $table->unsignedBigInteger('contractor_id')->nullable()->after('customer_id');
                $table->index(['project_id', 'contractor_id'], 'customer_ledger_project_contractor_idx');
            });
        }
        if (!$this->foreignKeyExists('customer_ledger', 'customer_ledger_contractor_project_fk')) {
            Schema::table('customer_ledger', function (Blueprint $table): void {
                $table->foreign(['contractor_id', 'project_id'], 'customer_ledger_contractor_project_fk')
                    ->references(['id', 'project_id'])->on('contractors')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Payroll records are financial history; retain the project and contractor references on rollback.
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)->exists();
    }
};
