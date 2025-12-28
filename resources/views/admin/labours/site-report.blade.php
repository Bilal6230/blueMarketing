@foreach ($reports as $report)
    <tr data-id="" data-name="{{ $report['name'] }}"
        data-mobile="{{ $report['mobile'] }}" data-role="{{ $report['designation'] }}"
        data-rate="{{ $report['rate'] }}" data-advance="0">
        <td  class="labour-tooltip"
        data-tooltip="
Name: {{ $report['name'] }}
Father Name: {{ $report['father_name'] }}
CNIC: {{ $report['cnic'] }}
Phone: {{ $report['mobile'] }}
Role: {{ $report['designation'] }}
Daily Wage: Rs {{ $report['rate'] }}
">{{ $report['name'] ?? '' }} ({{ substr($report['cnic'], -4) }})</td>
        <td>{{ $report['mobile'] ?? '' }}</td>
        <td>{{ $report['designation'] ?? '' }}</td>
        <td class="right">PKR {{ $report['rate'] ?? '' }}</td>
        <td class="right">{{ $report['days'] }}</td>
        <td class="right">{{ $report['overtime'] }}</td>
        <td class="right">{{ $report['amount'] }}</td>
    </tr>
@endforeach
<tr>
    <td colspan="6" style="text-align:right;">Total</td>
    <td class="right">{{ $total_amount ?? '' }}</td>
</tr>
