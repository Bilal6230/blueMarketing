@foreach ($labours as $labour)
<tr data-id="{{ $labour->id }}"
    data-name="{{ $labour->name }}"
    data-mobile="{{ $labour->phone }}"
    data-role="{{ $labour->role }}"
    data-rate="{{ $labour->daily_wage }}"
    data-advance="0">

    <td><a href="{{ Route('labour.history', $labour->id) }}" target="_blank">{{ $labour->name ?? '' }}</a></td>
    <td>{{ $labour->phone ?? '' }}</td>
    <td>{{ $labour->role ?? '' }}</td>
    <td class="right">PKR {{ $labour->daily_wage ?? '' }}</td>
    <td class="right">PKR {{ $labour->advance ?? '' }}</td>

    <td class="actions">
        <!-- Edit Labour -->
        <button type="button"
                class="icon-btn editLabourBtn"
                title="Edit Labour"
                data-id="{{ $labour->id }}"
                labourData='@json($labour)'>
            <i class="fa fa-edit"></i>
        </button>

        <!-- Change Rate -->
        <button type="button"
                class="icon-btn changeRateBtn"
                title="Change Rate"
                data-id="{{ $labour->id }}"
                data-name="{{ $labour->name }}"
                data-rate="{{ $labour->daily_wage }}">
            <i class="fa fa-money-bill-wave"></i>
        </button>
    </td>
</tr>
@endforeach

