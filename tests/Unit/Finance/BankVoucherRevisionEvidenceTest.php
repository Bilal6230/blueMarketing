<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\BankVoucherRevisionEvidence;
use PHPUnit\Framework\TestCase;

final class BankVoucherRevisionEvidenceTest extends TestCase
{
    private function source(): object
    {
        return (object)[
            'transaction_type'=>'CR','payment_type'=>2,'bank_id'=>5,
            't_number'=>'ONLINE-2026-123','passing_date'=>'2026-11-12',
            'cheque_purpose'=>null,
        ];
    }

    private function allocation(): object
    {
        return (object)[
            'id'=>72,'source_method'=>'online','source_id'=>81,'amount'=>650.25,
        ];
    }

    public function test_unchanged_bor_revision_instrument_passes(): void
    {
        $source=$this->source();
        $allocation=$this->allocation();
        $before=BankVoucherRevisionEvidence::capture($source,'BOR',$allocation);
        BankVoucherRevisionEvidence::assertUnchanged($before,$source,$allocation,'BOR');
        $this->assertSame('650.25',$before['bank_allocation_amount']);
        $this->assertSame('ONLINE-2026-123',$before['bank_reference']);
    }

    public function test_changed_bank_reference_is_blocked(): void
    {
        $source=$this->source();
        $allocation=$this->allocation();
        $before=BankVoucherRevisionEvidence::capture($source,'BOR',$allocation);
        $source->t_number='TAMPERED';
        $this->expectException(\DomainException::class);
        BankVoucherRevisionEvidence::assertUnchanged($before,$source,$allocation,'BOR');
    }

    public function test_changed_bank_or_passing_date_is_blocked(): void
    {
        foreach (['bank_id'=>10,'passing_date'=>'2026-11-13'] as $field=>$value) {
            $source=$this->source();
            $allocation=$this->allocation();
            $before=BankVoucherRevisionEvidence::capture($source,'BOR',$allocation);
            $source->{$field}=$value;
            try {
                BankVoucherRevisionEvidence::assertUnchanged($before,$source,$allocation,'BOR');
                $this->fail('Instrument change must be rejected: '.$field);
            } catch (\DomainException $exception) {
                $this->assertNotSame('',$exception->getMessage());
            }
        }
    }

    public function test_changed_source_link_or_allocation_amount_is_blocked(): void
    {
        foreach (['source_id'=>90,'amount'=>650.26,'id'=>73] as $field=>$value) {
            $source=$this->source();
            $allocation=$this->allocation();
            $before=BankVoucherRevisionEvidence::capture($source,'BOR',$allocation);
            $allocation->{$field}=$value;
            try {
                BankVoucherRevisionEvidence::assertUnchanged($before,$source,$allocation,'BOR');
                $this->fail('Allocation identity must not change: '.$field);
            } catch (\DomainException $exception) {
                $this->assertNotSame('',$exception->getMessage());
            }
        }
    }

    public function test_legacy_snapshot_without_instrument_evidence_fails_closed(): void
    {
        $this->expectException(\DomainException::class);
        BankVoucherRevisionEvidence::assertUnchanged(
            ['type'=>'CR'],$this->source(),$this->allocation(),'BOR');
    }

    public function test_cheque_purpose_is_immutable_for_bop(): void
    {
        $source=$this->source();
        $source->transaction_type='CP';
        $source->payment_type=3;
        $source->cheque_purpose='Property settlement';
        $allocation=$this->allocation();
        $allocation->source_method='cheque';
        $before=BankVoucherRevisionEvidence::capture($source,'BOP',$allocation);
        $source->cheque_purpose='Unapproved change';
        $this->expectException(\DomainException::class);
        BankVoucherRevisionEvidence::assertUnchanged($before,$source,$allocation,'BOP');
    }

    public function test_payment_type_mismatch_is_rejected(): void
    {
        $source=$this->source();
        $source->payment_type=3;
        $this->expectException(\DomainException::class);
        BankVoucherRevisionEvidence::capture($source,'BOR',$this->allocation());
    }

    public function test_cross_direction_voucher_series_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        BankVoucherRevisionEvidence::capture($this->source(),'BOP',$this->allocation());
    }
}
