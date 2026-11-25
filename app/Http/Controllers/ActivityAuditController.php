<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ActivityAuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'method' => ['nullable', 'in:POST,PUT,PATCH,DELETE'],
            'route' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'between:100,599'],
            'table' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z_0-9]+$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $query = DB::table('activity_audits as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->select('a.*', 'u.name as actor_name')
            ->when($filters['actor_id'] ?? null, fn ($q, $id) => $q->where('a.actor_id', $id))
            ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('a.action', $method))
            ->when($filters['route'] ?? null, fn ($q, $route) => $q->where('a.route_name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $route).'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('a.response_status', $status))
            ->when($filters['table'] ?? null, fn ($q, $table) => $q->whereExists(function ($sub) use ($table) {
                $sub->selectRaw('1')->from('activity_audit_impacts as i')
                    ->whereColumn('i.activity_audit_id', 'a.id')->where('i.table_name', $table);
            }))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('a.created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('a.created_at', '<=', $to));

        $events = $query->orderByDesc('a.id')->paginate(25)->withQueryString();
        if ($request->expectsJson()) {
            return response()->json([
                'rows' => $events->items(),
                'total' => $events->total(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
            ]);
        }

        return view('admin.activity-audit.index', compact('events', 'filters'));
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
