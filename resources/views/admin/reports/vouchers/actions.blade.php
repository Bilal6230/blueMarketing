@php
    $voucherRoutePrefix = $voucher instanceof \App\Models\CommisionVoucher ? 'commision.voucher' : 'journal.voucher';
@endphp
@canany(['edit jv', 'delete jv', 'print jv'])
    <div class="btn-group">
        @can('edit jv')
            @unless($approvalTracked ?? false)
            <a href="{{ route($voucherRoutePrefix.'.edit', $voucher->id) }}" class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            @endunless
        @endcan

        @can('delete jv')
            @unless($approvalTracked ?? false)
            <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $voucher->id }}">
                <i class="fas fa-undo"></i> Reverse
            </button>
            @endunless
        @endcan

        @if(($canResubmit ?? false) && $voucherRoutePrefix === 'journal.voucher')
            @can('edit jv')
                <a href="{{ route('journal.voucher.edit', $voucher->id) }}?resubmit=1" class="btn btn-sm btn-outline-warning jv-resubmit" title="Revise rejected voucher and resubmit for approval">
                    <i class="fas fa-redo"></i> Revise
                </a>
            @endcan
        @endif

        @can('print jv')
            <a href="{{ route($voucherRoutePrefix.'.print', $voucher->id) }}" target="_blank" class="btn btn-sm btn-info">
                <i class="fas fa-print"></i> Print
            </a>
        @endcan
    </div>
@endcanany
