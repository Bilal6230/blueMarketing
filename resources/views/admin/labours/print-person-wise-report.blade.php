<table id="personAll table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Mobile</th>
            <th>Designation</th>
            <th>Rate</th>
            <th>Days</th>
            <th>Over Time</th>
            <th>Ratings</th>
            <th>Amount</th>
            <th>Over All Balance</th>
            <th>Payments</th>
        </tr>
    </thead>
    <tbody id="">
        @foreach ($personWiseReports as $report)
            <tr class="labour-row">
                <td>{{ $report['name'] ?? '' }} ({{ substr($report['cnic'], -4) }})</td>
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
                <td><input type="number" class="form-control labour_amount"
                        name="labour_amount[]" data-id="{{ $report['id'] }}">
                </td>
            </tr>
        @endforeach
        <tr>
            <td colspan="8" style="text-align:right;">Total</td>
            <td>{{ $total_amount ?? '' }}</td>
            <td>
                <div class="">
                    Total Amount: <span id="totalVoucherAmount" style="color: #ff0d0d;"></span>
                </div>
                @if ($personWiseReports->count() > 0)
                    <button class="btn ghost" id="createLabourVoucher">Create Labour
                        Voucher</button>
                @endif
            </td>
        </tr>
    </tbody>
</table>
