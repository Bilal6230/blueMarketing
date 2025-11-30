@foreach ($personWiseReports as $report)
    <tr data-id="" data-name="{{ $report['name'] }}" data-mobile="{{ $report['mobile'] }}"
        data-role="{{ $report['designation'] }}" data-rate="{{ $report['rate'] }}" data-advance="0">
        <td>{{ $report['name'] ?? '' }}</td>
        <td>{{ $report['mobile'] ?? '' }}</td>
        <td>{{ $report['designation'] ?? '' }}</td>
        <td>PKR {{ $report['rate'] ?? '' }}</td>
        <td>{{ $report['days'] }}</td>
        <td>{{ $report['overtime'] }}</td>
        <td>
            <label class="switch">
                <input type="checkbox" class="toggle-paid" data-id="{{ $report['id'] }}" data-ids="{{ $report['attendance_ids'] }}"
                    {{ $report['paid_status'] == 'paid' ? 'checked' : '' }}>
                <span class="slider round"></span>
            </label>
        </td>
        <td>
            <div class="stars" style="--rating: {{ $report['ratings'] }};" aria-label="Rating of {{ $report['ratings'] }} out of 5"></div>
        </td>
        <td>{{ $report['amount'] }}</td>
        <td>{{ $report['advance'] }}</td>
        <td>{{ number_format(floatval(str_replace(',', '', $report['amount'])) - $report['advance'], 2) }}</td>
    </tr>
@endforeach
<tr>
    <td colspan="8" style="text-align:right;">Total</td>
    <td>{{ $total_amount ?? '' }}</td>
</tr>
