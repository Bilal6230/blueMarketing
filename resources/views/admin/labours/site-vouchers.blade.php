@php
    $selectedSiteName = $selectedSite?->site_name;
@endphp


    @foreach ($siteVouchersNotCreated as $voucher)
        <tr>
            <td>--</td>
            <td>--</td>
            <td>{{ $voucher->site?->site_name ?? $selectedSiteName ?? 'N/A' }}</td>
            <td class="right">{{ $voucher->total_amount }}</td>
            <td>--</td>
            <td class="tag">Not Created</td>
            <td class="actions">
               --
            </td>
        </tr>
    @endforeach
    @foreach ($siteVouchers as $voucher)
        <tr>
            <td>{{ 'JV-' . get_jv_number($voucher->voucher_number) }}</td>
            <td>{{ \Carbon\Carbon::parse($voucher->date)->format('d M Y') }}</td>
            <td> {{ str_replace('Labour payment for Site: ', '', $voucher->description ?? 'N/A') }}</td>
            <td class="right">{{ number_format((float) $voucher->total_debit, 2) }}</td>
            <td>{{ $voucher->creator?->name ?? 'System' }}</td>
            <td>
                <span class="tag">{{ ucfirst($voucher->status ? 'Created' : 'Pending') }}</span>
            </td>
            <td class="actions">
                <a href="{{ route('journal.voucher.print', $voucher->id) }}" target="_blank" class="icon-btn"
                    title="Print Voucher">
                    <i class="fa-solid fa-print"></i>
                </a>
            </td>
        </tr>
    @endforeach
