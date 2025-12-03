@foreach ($labours as $labour)
    <tr data-id="{{ $labour->id }}" data-name="{{ $labour->name }}"
        data-mobile="{{ $labour->phone }}" data-role="{{ $labour->role }}"
        data-rate="{{ $labour->daily_wage }}" data-advance="0">
        <td>{{ $labour->name ?? '' }}</td>
        <td>{{ $labour->phone ?? '' }}</td>
        <td>{{ $labour->role ?? '' }}</td>
        <td class="right">PKR {{ $labour->daily_wage ?? '' }}</td>
        <td class="right">PKR {{ $labour->advance ?? '' }}</td>
        <td><button class="btn editLabourBtn" data-id="{{ $labour->id }}" labourData="{{ json_encode($labour) }}">Edit</button>
        </td>
    </tr>
@endforeach
