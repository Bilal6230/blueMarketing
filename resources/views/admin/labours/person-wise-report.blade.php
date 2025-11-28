@foreach ($personWiseReports as $report)
    <tr data-id="" data-name="{{ $report['name'] }}"
        data-mobile="{{ $report['mobile'] }}" data-role="{{ $report['designation'] }}"
        data-rate="{{ $report['rate'] }}" data-advance="0">
        <td>{{ $report['name'] ?? '' }}</td>
        <td>{{ $report['mobile'] ?? '' }}</td>
        <td>{{ $report['designation'] ?? '' }}</td>
        <td>PKR {{ $report['rate'] ?? '' }}</td>
        <td></td>
        <td>{{ $report['days'] }}</td>
        <td>{{ $report['overtime'] }}</td>
        <td>{{ $report['paid_status'] }}</td>
        <td></td>
        <td>{{ $report['amount'] }}</td>
    </tr>
@endforeach
<tr>
    <td colspan="9" style="text-align:right;">Total</td>
    <td>{{ $total_amount ?? '' }}</td>
</tr>
