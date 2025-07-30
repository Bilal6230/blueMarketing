<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeadController extends Controller
{
    //$appUser = $request->authenticated_user;


    public function activeLeads(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'project_id' => 'required',
            'per_page' => 'sometimes|integer|min:1',
            'page' => 'sometimes|integer|min:1',
            'search' => 'sometimes|string',
            'sort_by' => 'sometimes|in:first_name,last_name,follow_up,created_at',
            'sort_order' => 'sometimes|in:asc,desc',
        ]);

        $user = User::find($request->user_id);

        $today = Carbon::now('Asia/Karachi')->startOfDay();
        $perPage = $request->input('per_page', 10);

        $query = $user->Leads()
            ->where('is_active', 1)
            ->where('project_id', $request->project_id)
            ->where('follow_up', '<=', $today)
            ->with('project');

        // 🔍 Search
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%$searchTerm%")
                ->orWhere('last_name', 'like', "%$searchTerm%")
                ->orWhere('phone_number', 'like', "%$searchTerm%")
                ->orWhere('mobile_number', 'like', "%$searchTerm%")
                ->orWhere('nic_number', 'like', "%$searchTerm%");
            });
        }

        // 🔃 Sort
        $sortBy = $request->input('sort_by', 'follow_up');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Get total for pagination
        $total = (clone $query)->count();

        // Paginated results
        $leads = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'date_time' => $today->toDateTimeString(),
            'total' => $total,
            'current_page' => $leads->currentPage(),
            'per_page' => $leads->perPage(),
            'last_page' => $leads->lastPage(),
            'data' => $leads->items(),
        ]);
    }

    public function leadSummary(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'project_id' => 'required',
            
        ]);

        $user = User::find($request->user_id);


        $today = Carbon::now('Asia/Karachi')->startOfDay();

        $leadsQuery = $user->Leads()
            ->where('is_active', 1)
            ->where('project_id', $request->project_id);

        $totalLeads = $leadsQuery->count();

        $pendingLeads = (clone $leadsQuery)
            ->where('follow_up', '<=', $today)
            ->count();

        $completeLeads = (clone $leadsQuery)
            ->where('follow_up', '>', $today)
            ->count();

        return response()->json([
            'status' => true,
            'leads' => $totalLeads,
            'pending_leads' => $pendingLeads,
            'complete_leads' => $completeLeads,
        ]);
    }

}
