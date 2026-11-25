<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ActivityAuditController extends Controller
{
    public function index(Request $request)
    {
        // Same server-side filter/sort/pagination contract as CR/DR.
        $filters = $request->validate([
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort_column' => ['nullable', 'in:id,date,actor_id,method,route,table,status,ip'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'column_date_from' => ['nullable', 'date_format:Y-m-d'],
            'column_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:column_date_from'],
            'column_actor_id' => ['nullable', 'string', 'max:100'],
            'column_method' => ['nullable', 'string', 'max:100'],
            'column_route' => ['nullable', 'string', 'max:100'],
            'column_table' => ['nullable', 'string', 'max:100'],
            'column_status' => ['nullable', 'string', 'max:100'],
            'column_ip' => ['nullable', 'string', 'max:100'],
            'column_values' => ['nullable', 'array'],
            'column_values.*' => ['array', 'max:100'],
            'column_values.*.*' => ['string', 'max:100'],
            'column_excluded' => ['nullable', 'array'],
            'column_excluded.*' => ['array', 'max:100'],
            'column_excluded.*.*' => ['string', 'max:100'],
        ]);
        $base = DB::table('activity_audits as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->select('a.*', 'u.name as actor_name')
            ->selectRaw("(SELECT GROUP_CONCAT(DISTINCT i.table_name ORDER BY i.table_name SEPARATOR ', ') FROM activity_audit_impacts i WHERE i.activity_audit_id = a.id) AS affected_tables");

        // Avoid raw user SQL; every sortable field has a fixed mapping.
        $fieldMap = [
            'id' => 'a.id',
            'date' => 'a.created_at',
            'actor_id' => 'a.actor_id',
            'method' => 'a.action',
            'route' => 'a.route_name',
            'status' => 'a.response_status',
            'ip' => 'a.ip_address',
        ];
        $filtered = clone $base;
        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $like = '%'.addcslashes($term, '%_\\\\').'%';
            $filtered->where(function ($q) use ($like) {
                $q->where('u.name', 'like', $like)
                    ->orWhere('a.actor_id', 'like', $like)
                    ->orWhere('a.action', 'like', $like)
                    ->orWhere('a.route_name', 'like', $like)
                    ->orWhere('a.request_path', 'like', $like)
                    ->orWhere('a.ip_address', 'like', $like);
            });
        }
        if (!empty($filters['column_date_from'])) $filtered->whereDate('a.created_at', '>=', $filters['column_date_from']);
        if (!empty($filters['column_date_to'])) $filtered->whereDate('a.created_at', '<=', $filters['column_date_to']);

        foreach (['actor_id','method','route','status','ip'] as $field) {
            if (!empty($filters['column_'.$field])) {
                $filtered->where($fieldMap[$field], 'like', '%'.addcslashes($filters['column_'.$field], '%_\\\\').'%');
            }
        }
        if (!empty($filters['column_table'])) {
            $needle = $filters['column_table'];
            $filtered->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('activity_audit_impacts as i')
                ->whereColumn('i.activity_audit_id', 'a.id')
                ->where('i.table_name', 'like', '%'.addcslashes($needle, '%_\\\\').'%'));
        }
        foreach (['method' => 'a.action', 'status' => 'a.response_status'] as $field => $column) {
            $includes = $filters['column_values'][$field] ?? [];
            $excludes = $filters['column_excluded'][$field] ?? [];
            if ($includes) $filtered->whereIn($column, $includes);
            if ($excludes) $filtered->whereNotIn($column, $excludes);
        }

        $total = DB::table('activity_audits')->count();
        $filteredTotal = (clone $filtered)->count();
        $sort = $filters['sort_column'] ?? 'date';
        $direction = $filters['sort_direction'] ?? 'desc';
        $start = (int) ($filters['start'] ?? 0);
        $length = (int) ($filters['length'] ?? 25);
        $filtered->orderBy($fieldMap[$sort] ?? ($sort === 'table' ? 'affected_tables' : 'a.created_at'), $direction)
            ->orderByDesc('a.id');
        $rows = $filtered->offset($start)->limit($length)->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows, 'recordsTotal' => $total, 'recordsFiltered' => $filteredTotal]);
        }

        $initialFilterOptions = [
            'method' => DB::table('activity_audits')->distinct()->orderBy('action')->pluck('action')->all(),
            'status' => DB::table('activity_audits')->distinct()->orderBy('response_status')->pluck('response_status')->map(fn ($v) => (string) $v)->all(),
        ];
        return view('admin.activity-audit.index', compact('initialFilterOptions'));
    }

    public function show(int $audit)
    {
        $event = DB::table('activity_audits as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->select('a.*', 'u.name as actor_name')->where('a.id', $audit)->first();
        abort_unless($event, 404);
        $impacts = DB::table('activity_audit_impacts')
            ->where('activity_audit_id', $audit)->orderBy('id')->get();
        return response()->json(['event' => $event, 'impacts' => $impacts]);
    }
}
