<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class TypedLedgerBackfillSafetyContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(base_path($path));
        self::assertIsString($source);
        return str_replace(["\r\n","\r"],"\n",$source);
    }

    public function test_backfill_reuses_existing_ledger_and_source_identity_tables(): void
    {
        $src=$this->source('app/Services/Finance/TypedLedgerBackfill.php');
        self::assertStringContainsString("'BD'=>['bank_transfer'",$src);
        self::assertStringContainsString("'BW'=>['bank_transfer'",$src);
        self::assertStringContainsString("'PR'=>['plot_receipt'",$src);
        self::assertStringContainsString("'PP'=>['plot_purchase'",$src);
        self::assertStringContainsString("'PPR','plot_receipt'",$src);
        self::assertStringContainsString("'ledger_source_attributions'",$src);
        self::assertStringContainsString("'legacy_ledger_id'=>$ledgerId",$src);
        self::assertStringContainsString("'type'=>$ledgerType",$src);
        self::assertStringContainsString("'type_id'=>$sourceId",$src);
        self::assertStringNotContainsString('Schema::create(',$src);
    }

    public function test_backfill_requires_verified_source_project_accounts_and_balanced_active_legs(): void
    {
        $src=$this->source('app/Services/Finance/TypedLedgerBackfill.php');
        foreach([
            'LEDGER_BACKFILL_SOURCE_CLAIM_MISMATCH',
            'LEDGER_BACKFILL_SOURCE_PROJECT_MISMATCH',
            'LEDGER_BACKFILL_POSSIBLE_DUPLICATE_SOURCE',
            'LEDGER_BACKFILL_PARTIAL_LINKS',
            'LEDGER_BACKFILL_EXISTING_LINK_MISMATCH',
            'LEDGER_BACKFILL_FOREIGN_ACCOUNT',
            'LEDGER_BACKFILL_ACTIVE_LINES_UNBALANCED',
            'LEDGER_BACKFILL_ARCHIVED_SOURCE_ACTIVE_LINE',
            'LEDGER_BACKFILL_INVALID_LEG',
        ] as $guard){
            self::assertStringContainsString($guard,$src);
        }
        self::assertStringContainsString('lockForUpdate()',$src);
        self::assertStringContainsString('DB::transaction(',$src);
        self::assertStringContainsString('if(!$apply)return $summary;',$src);
    }

    public function test_cli_requires_specific_database_project_and_explicit_apply(): void
    {
        $src=$this->source('app/Console/Commands/BackfillVerifiedTypedLedger.php');
        self::assertStringContainsString('finance:backfill-verified-ledgers',$src);
        self::assertStringContainsString('{--database=',$src);
        self::assertStringContainsString('{--project=',$src);
        self::assertStringContainsString('{--apply',$src);
        self::assertStringContainsString('{--confirm=',$src);
        self::assertStringContainsString('No writes attempted. Resolve every blocked batch first.',$src);
        self::assertStringContainsString('$backfill->inspect((int)$project,(int)$id,false)',$src);
        self::assertStringContainsString('$backfill->inspect((int)$project,(int)$id,$apply)',$src);
    }
}
