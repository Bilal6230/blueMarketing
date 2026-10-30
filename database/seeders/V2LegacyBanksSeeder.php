<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class V2LegacyBanksSeeder extends Seeder
{
    private const LEGACY_BANKS = [1 => 'Bank Alfalah,
        2 => 'Habib Bank Limited (HBL),
        3 => 'United Bank Limited (UBL),
        4 => 'National Bank of Pakistan,
        5 => 'MCB Bank,
        6 => 'Allied Bank Limited,
        7 => 'Dubai Islamic Bank Pakistan,
        8 => 'Faysal Bank,
        9 => 'JS Bank,
        10 => 'Bank of Punjab,
        11 => 'Sindh Bank,
        12 => 'Bank of Khyber,
        13 => 'Zarai Taraqiati Bank Limited,
        14 => 'Askari Bank,
        15 => 'The Bank of Punjab,
        16 => 'Habib Metropolitan Bank,
        17 => 'Meezan Bank,
        18 => 'Al Baraka Bank Pakistan,
        19 => 'Burj Bank,
        20 => 'Dawood Islamic Bank,
        21 => 'Soneri Bank,
        22 => 'Summit Bank,
        23 => 'BankIslami Pakistan,
        24 => 'UBL UK,
        25 => 'Dubai Islamic Bank,
        26 => 'Faysal Islamic Bank,
        27 => 'Bank Al-Habib];

    public function run(): void
    {
        $connection = DB::connection();
        $name = (string) $connection->getDatabaseName();
        if (!str_starts_with($name, 'bluemarketing_v2_') &&
            !($connection->getDriverName() === 'sqlite' && $name === ':memory:')) {
            throw new \RuntimeException('V2 legacy banks seed refused outside isolated V2 database.');
        }
        if (!Schema::hasTable('banks')) {
            throw new \RuntimeException('V2 banks migration must run first.');
        }

        DB::transaction(function (): void {
            $existing = DB::table('banks')->orderBy('id')->get(['id', 'display_name']);
            if ($existing->isNotEmpty()) {
                if ($existing->count() !== count(self::LEGACY_BANKS)) {
                    throw new \RuntimeException('Banks already populated with divergent source mapping.');
                }
                foreach ($existing as $bank) {
                    if (!array_key_exists((int) $bank->id, self::LEGACY_BANKS) ||
                        $bank->display_name !== self::LEGACY_BANKS[(int) $bank->id]) {
                        throw new \RuntimeException('Refusing to renumber or rename an existing bank source ID.');
                    }
                }
                return;
            }

            $rows = [];
            foreach (self::LEGACY_BANKS as $id => $name) {
                // These are new lookup rows, not historical banking transactions.
                $rows[] = ['id' => $id, 'display_name' => $name, 'is_active' => true,
                    'created_at' => null, 'updated_at' => null];
            }
            DB::table('banks')->insert($rows);
        });
    }
}
