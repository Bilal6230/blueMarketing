@foreach ($labours as $labour)
    <tr data-id="{{ $labour->id }}" data-name="{{ $labour->name }}"
        data-mobile="{{ $labour->phone }}" data-role="{{ $labour->role }}"
        data-rate="{{ $labour->daily_wage }}" data-advance="0">
        <td>{{ $labour->name ?? '' }}</td>
        <td>{{ $labour->phone ?? '' }}</td>
        <td>{{ $labour->role ?? '' }}</td>
        <td class="right">PKR {{ $labour->daily_wage ?? '' }}</td>
        <td class="right">PKR 0</td>
        <td><button class="btn" data-action="edit-lab" title="Edit">Edit</button>
        </td>
    </tr>
@endforeach
