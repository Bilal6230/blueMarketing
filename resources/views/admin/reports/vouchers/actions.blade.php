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

        @can('print jv')
            <a href="{{ route($voucherRoutePrefix.'.print', $voucher->id) }}" target="_blank" class="btn btn-sm btn-info">
                <i class="fas fa-print"></i> Print
            </a>
        @endcan
    </div>
@endcanany
