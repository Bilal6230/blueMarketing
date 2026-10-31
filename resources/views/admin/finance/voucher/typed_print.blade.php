<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $source->transaction_type }} — source {{ $source->id }}</title>
<style>body{font-family:Arial,sans-serif;margin:32px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:8px;text-align:left}</style></head>
<body>
    <h1>{{ $source->transaction_type }} — source #{{ $source->id }}</h1>
    <p>Document date: {{ $source->date }} · Reference: {{ $source->reference }}</p>
    <p>{{ $source->description }}</p>
    <table><thead><tr><th>Posting date</th><th>Account</th><th>Detail</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
        @foreach($lines as $line)
            <tr><td>{{ $line['date'] }}</td><td>{{ $line['head_account_name'] }} / {{ $line['subhead_account_name'] }}</td>
            <td>{{ $line['is_reversal'] ? 'Reversal: ' : '' }}{{ $line['detail'] }}</td><td>{{ $line['amount_in'] }}</td><td>{{ $line['amount_out'] }}</td></tr>
        @endforeach
    </tbody></table>
    <p>Original entries and reversals are preserved separately.</p>
</body></html>
