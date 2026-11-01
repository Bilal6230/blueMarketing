@php
    $money = fn($cents) => $cents ? number_format($cents / 100, 2) : '-';
    $amountCells = function($totals) use ($money) {
        return '<td class="number previous-col">'.$money($totals['previous_debit']).'</td><td class="number previous-col">'.$money($totals['previous_credit']).'</td>'.
            '<td class="number within-col">'.$money($totals['period_debit']).'</td><td class="number within-col">'.$money($totals['period_credit']).'</td>'.
            '<td class="number closing-col">'.$money($totals['closing_debit']).'</td><td class="number closing-col">'.$money($totals['closing_credit']).'</td>';
    };
    $serial = $rowNumberStart ?? 0;
@endphp
<table class="table table-bordered table-sm trial-balance">
    <thead><tr><th rowspan="2" class="code-col">#</th><th rowspan="2" class="code-col">A/C No.</th><th rowspan="2" class="account-title">Title / Description of Account</th><th colspan="2" class="previous-col">Previous</th><th colspan="2" class="within-col">Within</th><th colspan="2" class="closing-col">Closing</th></tr>
    <tr><th class="previous-col">Debit</th><th class="previous-col">Credit</th><th class="within-col">Debit</th><th class="within-col">Credit</th><th class="closing-col">Debit</th><th class="closing-col">Credit</th></tr></thead>
    <tbody>
    @forelse($hierarchy['types'] as $type)
        <tr class="head-row"><th colspan="9">{{ strtoupper($type['name']) }}</th></tr>
        @foreach($type['heads'] as $head)
            <tr class="subhead-row"><th colspan="9">{{ strtoupper($head['name']) }}</th></tr>
            @foreach($head['accounts'] as $account)
                <tr class="account-row">
                    <td class="code-col">{{ ++$serial }}</td><td class="code-col">{{ $account['id'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    {!! $amountCells($account['amounts']) !!}
                </tr>
            @endforeach
            <tr class="subtotal"><th colspan="3">Total: {{ $head['name'] }}</th>{!! $amountCells($head['totals']) !!}</tr>
        @endforeach
        <tr class="head-total"><th colspan="3">Total: {{ $type['name'] }}</th>{!! $amountCells($type['totals']) !!}</tr>
    @empty
        <tr><td colspan="9" class="text-center">No transactions found for this date range.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><th colspan="3">{{ !empty($printMode) ? 'Grand Total' : 'Page Total' }}</th>{!! $amountCells($hierarchy['totals']) !!}</tr></tfoot>
</table>
