<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('Clean baseline requires MySQL 8.4.');
        $name=(string) DB::getDatabaseName();
        if (in_array($name,['bluenew','bluenew_clean'],true)) throw new RuntimeException('Protected historical database: '.$name);
        $hasTables=(int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME<>'migrations'",[$name])->total;
        $history=(int) DB::table('migrations')->count();
        if ($hasTables>0 || $history>0) throw new RuntimeException('One-table baseline only runs on a fresh EMPTY database; refused '.$name);
    }
    public function down(): void {}
};
