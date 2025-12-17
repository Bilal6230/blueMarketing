@foreach ($personWiseReports as $report)
    <tr class="labour-row" data-id="{{ $report['id'] }}" data-name="{{ $report['name'] }}"
        data-mobile="{{ $report['mobile'] }}" data-role="{{ $report['designation'] }}" data-rate="{{ $report['rate'] }}"
        data-advance="{{ $report['advance'] }}" data-attendance-dates="{{ $report['attendance_dates'] }}">
        <td class="labour-tooltip"
            data-tooltip="
Name: {{ $report['name'] }}
Father Name: {{ $report['father_name'] }}
CNIC: {{ $report['cnic'] }}
Phone: {{ $report['mobile'] }}
Role: {{ $report['designation'] }}
Daily Wage: Rs {{ $report['rate'] }}
">
            {{ $report['name'] ?? '' }} ({{ substr($report['cnic'], -4) }})</td>
        <td>{{ $report['mobile'] ?? '' }}</td>
        <td>{{ $report['designation'] ?? '' }}</td>
        <td>PKR {{ $report['rate'] ?? '' }}</td>
        <td>{{ $report['days'] }}</td>
        <td>{{ $report['overtime'] }}</td>
        {{-- <td>
            <label class="switch">
                <input type="checkbox" class="toggle-paid" data-id="{{ $report['id'] }}" data-ids="{{ $report['attendance_ids'] }}"
                    {{ $report['paid_status'] == 'paid' ? 'checked' : '' }}>
                <span class="slider round"></span>
            </label>
        </td> --}}
        <td>
            <div class="stars" style="--rating: {{ $report['ratings'] }};"
                aria-label="Rating of {{ $report['ratings'] }} out of 5"></div>
        </td>
        <td>{{ $report['amount'] }}</td>
        <td>{{ $report['remaningAmount'] }}</td>
        {{-- <td>{{ $report['advance'] }}</td> --}}
        {{-- <td>{{ number_format(floatval(str_replace(',', '', $report['amount'])) - $report['advance'], 2) }}</td> --}}
        <td><input type="number" class="form-control labour_amount" name="labour_amount[]"
                data-id="{{ $report['id'] }}">
        </td>
    </tr>
@endforeach
<tr>
    <td colspan="8" style="text-align:right;">Total</td>
    <td>{{ $total_amount ?? '' }}</td>
    <td>
        @if ($personWiseReports->count() > 0)
            <button class="btn ghost" id="createLabourVoucher">Create Labour Voucher</button>
        @endif
    </td>
</tr>
