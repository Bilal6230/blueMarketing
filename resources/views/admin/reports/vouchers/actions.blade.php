@canany(['edit jv', 'delete jv', 'print jv'])
    <div class="btn-group">
        @can('edit jv')
            <a href="{{ route('journal.voucher.edit', $voucher->id) }}" class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
        @endcan

        @can('delete jv')
            <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $voucher->id }}">
                <i class="fas fa-trash"></i> Delete
            </button>
        @endcan

        @can('print jv')
            <a href="{{ route('journal.voucher.print', $voucher->id) }}" target="_blank" class="btn btn-sm btn-info">
                <i class="fas fa-print"></i> Print
            </a>
        @endcan
    </div>
@endcanany
