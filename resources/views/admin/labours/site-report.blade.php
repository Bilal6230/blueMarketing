@foreach ($reports as $report)
    <tr data-id="" data-name="{{ $report['name'] }}"
        data-mobile="{{ $report['mobile'] }}" data-role="{{ $report['designation'] }}"
        data-rate="{{ $report['rate'] }}" data-advance="0">
        <td>{{ $report['name'] ?? '' }}</td>
        <td>{{ $report['mobile'] ?? '' }}</td>
        <td>{{ $report['designation'] ?? '' }}</td>
        <td class="right">PKR {{ $report['rate'] ?? '' }}</td>
        <td class="right">{{ $report['days'] }}</td>
        <td class="right">{{ $report['overtime'] }}</td>
        <td class="right">{{ $report['amount'] }}</td>
    </tr>
@endforeach
