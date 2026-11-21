@php
    $voucherRoutePrefix = $voucher instanceof \App\Models\CommisionVoucher ? 'commision.voucher' : 'journal.voucher';
    $salesEditable = $voucher->type === 'SV' && $voucher->status !== 'rejected';
@endphp
@canany(['edit jv', 'delete jv', 'print jv'])
    <div class="btn-group">
        @can('edit jv')
            {{-- SV uses the same typed journal source editor, with pending-approval
                 and posted-source lifecycle checks enforced server-side. --}}
            @if(($salesEditable || ($voucher->type !== 'SV' && (!($approvalTracked ?? false) || (\App\Services\Finance\FinanceWorkflowAccess::normalErpMode() && !($canResubmit ?? false))))))
            <a href="{{ route($voucherRoutePrefix.'.edit', $voucher->id) }}" class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            @endif
        @endcan

        @can('delete jv')
            @if(($salesEditable || ($voucher->type !== 'SV' && (!($approvalTracked ?? false) || (\App\Services\Finance\FinanceWorkflowAccess::normalErpMode() && !($canResubmit ?? false))))))
            <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $voucher->id }}">
                <i class="fas fa-undo"></i> {{ $voucher->type === 'SV' ? 'Delete' : 'Reverse' }}
            </button>
            @endif
        @endcan

        @if(($canResubmit ?? false) && $voucherRoutePrefix === 'journal.voucher' && !\App\Services\Finance\FinanceWorkflowAccess::selected())
            @can('edit jv')
                <a href="{{ route('journal.voucher.edit', $voucher->id) }}?resubmit=1" class="btn btn-sm btn-outline-warning jv-resubmit" title="Revise rejected voucher and resubmit for approval">
                    <i class="fas fa-redo"></i> Revise
                </a>
            @endcan
        @endif

        @can('print jv')
            <a href="{{ route($voucherRoutePrefix.'.print', $voucher->id) }}" target="_blank" rel="noopener" data-blue-action-label="{{ $voucher->type === 'SV' ? 'View / Print Sales Voucher' : 'Print Journal Voucher' }}" class="btn btn-sm btn-info">
                <i class="fas fa-print"></i> Print
            </a>
        @endcan
    </div>
@endcanany
