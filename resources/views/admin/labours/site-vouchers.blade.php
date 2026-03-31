@php
    $selectedSiteName = $selectedSite?->site_name;
@endphp

@if (!$selectedSite)
    <tr>
        <td colspan="7" class="text-center muted">Select a site to view created vouchers.</td>
    </tr>
@elseif ($siteVouchers->isEmpty())
    <tr>
        <td colspan="7" class="text-center muted">No created vouchers found for {{ $selectedSiteName }}.</td>
    </tr>
@else
    @foreach ($siteVouchers as $voucher)
        <tr>
            <td>{{ 'JV-' . get_jv_number($voucher->voucher_number) }}</td>
            <td>{{ \Carbon\Carbon::parse($voucher->date)->format('d M Y') }}</td>
            <td>{{ $voucher->site?->site_name ?? $selectedSiteName ?? 'N/A' }}</td>
            <td class="right">{{ number_format((float) $voucher->total_debit, 2) }}</td>
            <td>{{ $voucher->creator?->name ?? 'System' }}</td>
            <td>
                <span class="tag">{{ ucfirst($voucher->status ?? 'pending') }}</span>
            </td>
            <td class="actions">
                <a href="{{ route('journal.voucher.print', $voucher->id) }}" target="_blank" class="icon-btn"
                    title="Print Voucher">
                    <i class="fa-solid fa-print"></i>
                </a>
            </td>
        </tr>
    @endforeach
@endif
