@forelse($rowsByUser as $group)
    <section class="user-block">
        <div class="user-head"><strong>{{ $group['user']->name }}</strong><span>{{ count($group['rows']) }} record(s)</span></div>
        <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Mobile</th><th>Comment / Detail</th><th>{{ $mode === 'completed' ? 'Completed At' : 'Follow-up At' }}</th><th>Status</th>@if($mode === 'pending_overdue')<th>Late Days</th>@endif<th>Action</th></tr></thead><tbody>
        @foreach($group['rows'] as $row)
            <tr><td>{{ $row['name'] }}</td><td>{{ $row['mobile'] ?: '—' }}</td><td>{{ $row['comment'] ?: '—' }}</td><td>{{ $row['follow_up'] }}</td><td><span class="status">{{ $row['status'] ?: '—' }}</span></td>@if($mode === 'pending_overdue')<td class="late">{{ (int)$row['late_days'] }} day(s)</td>@endif<td><a class="customer" href="{{ url('/admin/crm/lead/work') }}?id={{ $row['lead_id'] }}&status=1&user={{ $group['user']->id }}">View</a></td></tr>
        @endforeach
        </tbody></table></div>
    </section>
@empty
    <div class="empty">No matching records found for this user.</div>
@endforelse
