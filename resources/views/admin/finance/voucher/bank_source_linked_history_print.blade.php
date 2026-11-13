<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Linked Voucher History — {{ $sourceLabel }}</title>
<style>
*{box-sizing:border-box}
body{font:13px Arial,sans-serif;color:#182638;margin:24px}
.sheet{max-width:1080px;margin:auto}
.toolbar{text-align:right;margin:0 auto 18px;max-width:1080px}
.toolbar button{padding:8px 15px;border:1px solid #aac2df;border-radius:5px;background:#fff;cursor:pointer}
header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #315787;padding-bottom:14px;margin-bottom:18px}
h1{font-size:20px;margin:0 0 6px}
.sub{color:#64748b}
.meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:16px 0 22px}
.meta div{padding:10px;border:1px solid #dce4ed;border-radius:5px}
.meta small{display:block;color:#66758a;margin-bottom:5px}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{border:1px solid #d2dce7;text-align:left;padding:9px 7px;overflow-wrap:anywhere;vertical-align:top}
th{background:#ebf2fa}
.amount{text-align:right;white-space:nowrap}
.note{color:#7a4d22}
footer{margin-top:22px;border-top:1px solid #dce4ed;padding-top:10px;color:#64748b;font-size:11px}
@media print{body{margin:0}.toolbar{display:none}header{break-after:avoid}thead{display:table-header-group}tr{break-inside:avoid}}
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print history</button></div>
<main class="sheet">
<header>
  <div><h1>Linked Voucher Allocation History</h1><div class="sub">{{ ucfirst($method) }} Management — {{ $sourceLabel }}</div></div>
  <div style="text-align:right"><b>Project #{{ $projectId }}</b><div class="sub">Source #{{ $sourceId }}</div></div>
</header>
<section class="meta" aria-label="Bank source">
  <div><small>Source method</small><b>{{ ucfirst($method) }}</b></div>
  <div><small>Source status</small><b>{{ $sourceStatus ?: '—' }}</b></div>
  <div><small>Source amount</small><b>Rs. {{ number_format((float) $sourceAmount, 2) }}</b></div>
</section>
<h2 style="font-size:16px">Linked vouchers</h2>
<table>
<thead><tr><th style="width:18%">Voucher</th><th style="width:13%" class="amount">Allocation (Rs.)</th><th style="width:15%">Approval</th><th style="width:20%">Allocated at</th><th>Release / Void evidence</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
  <td><b>{{ $row['voucher_number'] ?: ($row['voucher_type'].' / #'.$row['voucher_id']) }}</b></td>
  <td class="amount">{{ number_format((float) $row['amount'], 2) }}</td>
  <td>{{ $row['approval_status'] ?: 'Not tracked' }}</td>
  <td>{{ $row['allocated_at'] ?: '—' }}</td>
  <td>
  @if($row['released'])
    <b>Released</b>
    <div class="note">{{ $row['release_action'] ?: '—' }}</div>
    <div>{{ $row['released_at'] ?: '—' }}</div>
    <div>{{ $row['release_reason'] ?: '—' }}</div>
  @else
    Not released
  @endif
  </td>
</tr>
@empty
<tr><td colspan="5" style="text-align:center;color:#64748b">No voucher allocations are linked to this source.</td></tr>
@endforelse
</tbody>
@if(count($rows))
<tfoot><tr><th>Allocation history total</th><th class="amount">{{ number_format(array_sum(array_map(static fn ($row) => (float) $row['amount'], $rows)),2) }}</th><th colspan="3">Includes historical released allocations; not a current balance</th></tr></tfoot>
@endif
</table>
<footer>Read-only audit copy. Released or voided allocations remain visible as historical evidence. This report does not authorize posting or reuse of any bank source.</footer>
</main>
</body>
</html>
