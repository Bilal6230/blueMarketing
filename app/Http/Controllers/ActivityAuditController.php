<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ActivityAuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'method' => ['nullable', 'in:POST,PUT,PATCH,DELETE'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = DB::table('activity_audits as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->select('a.*', 'u.name as actor_name')
            ->when($filters['actor_id'] ?? null, fn ($q, $id) => $q->where('a.actor_id', $id))
            ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('a.action', $method))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('a.created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('a.created_at', '<=', $to));

        $events = $query->orderByDesc('a.id')->paginate(25)->withQueryString();
        return view('admin.activity-audit.index', compact('events', 'filters'));
    }
}
