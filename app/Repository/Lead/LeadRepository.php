<?php

namespace App\Repository\Lead;
use App\Models\Lead;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\Profiles\Profile;
use App\Models\Users\User;
use Carbon\Carbon;

class LeadRepository {

    public static function getLeadsList($user_id = null, $filter = null, $status = null){

         //Lead::where('user_id', $user_id);
         $leads =  Lead::where('is_active', 1);
         if(!is_null($user_id)){
            $profile =  $leads->whereRelation('users', 'user_id', $user_id )->whereIn('follow_status',[2,6,3,7,5,8]);
        }

        if(!is_null($filter)){
            if($filter == "schedule")
            {
                $profile =  $leads->where('follow_up', '<', now()->toDateTimeString());
            }
            elseif($filter == "today")
            {
                $profile =  $leads->whereDate('created_at', Carbon::today());
            }
        }

        // Eager load the project relationship to get the project name
        $leads = $leads->with('project:id,project') // Eager load only the 'id' and 'project' columns from the Project model
        ->get();

        // Modify the collection to include project name
        $leads->map(function ($lead) {
            $lead->project_name = $lead->project ? $lead->project->project : null; // Add project name to each lead
            return $lead;
        });

        return $leads;

        // $profile = $leads->get();

        // return $profile;
    }

    public static function getLeadsToday($project_id = null, $filter = null, $user_id = null)
    {
        // Base query for active leads
        $leadsQuery = Lead::where('is_active', 1);

        // Filter by project ID if provided
        if (!is_null($project_id)) {
            $leadsQuery = $leadsQuery->where('project_id', $project_id);
        }

        // Apply filter based on `schedule` or `today`
        if (!is_null($filter)) {
            if ($filter === "schedule") {
                $leadsQuery = $leadsQuery->where('follow_up', '<', now()->toDateTimeString());
            } elseif ($filter === "today") {
                $leadsQuery = $leadsQuery->whereDate('created_at', Carbon::today());
            }
        }

        // Filter by user ID if provided
        if (!is_null($user_id)) {
            $leadsQuery = $leadsQuery->where('create_by', $user_id);
        }

        // Clone query to get the total count
        $totalLeadsCount = $leadsQuery->count();

        // Eager load the project relationship and get the filtered leads
        $leads = $leadsQuery->with('project:id,project')->get();

        // Add project name to each lead
        $leads->each(function ($lead) {
            $lead->project_name = $lead->project->project ?? null; // Safely handle null project
        });

        // Return both leads and total count
        return [
            'total_leads' => $totalLeadsCount,
            'leads' => $leads,
        ];
    }
    public static function getAllLeads($project_id = null)
    {
        // Base query for active leads
        $leadsQuery = Lead::whereDate('follow_up', '<=', Carbon::today());
        // Filter by project ID if provided
        if (!is_null($project_id)) {
            $leadsQuery = $leadsQuery->where('project_id', $project_id);
        }

        $totalLeadsCount=$leadsQuery->count();
        // Return both leads and total count
        return [
            'total_leads' => $totalLeadsCount,
        ];
    }
    


    public static function getLeadsByUsers2($project_id = null, $filter = null, $user_id = null)
    {
        // Base query to get leads with necessary relationships
        $leadsQuery = Lead::with(['users:id,name', 'project:id,project']) // Load user and project info
            ->where('is_active', 1); // Only active leads

        // Filter by project ID if provided
        if (!is_null($project_id)) {
            $leadsQuery = $leadsQuery->where('project_id', $project_id);
        }

        // Apply additional filter: "schedule" or "today"
        if (!is_null($filter)) {
            if ($filter === "schedule") {
                $leadsQuery = $leadsQuery->where('follow_up', '<', now()->toDateTimeString());
            } elseif ($filter === "today") {
                $leadsQuery = $leadsQuery->whereDate('created_at', now());
            }
        }

        // Filter by user ID if provided
        if (!is_null($user_id)) {
            $leadsQuery = $leadsQuery->whereHas('users', function ($query) use ($user_id) {
                $query->where('users.id', $user_id);
            });
        }

        // Get leads data
        $leads = $leadsQuery->get();

        // Group leads by user_id
        $groupedLeads = $leads->groupBy(function ($lead) {
            return optional($lead->users->first())->id; // Group by the first user's ID
        })->map(function ($userLeads, $userId) {
            return [
                'user_id' => $userId,
                'user_name' => optional($userLeads->first()->users->first())->name, // Get user's name
                'total_leads' => $userLeads->count(), // Count total leads for this user
                'leads' => $userLeads->map(function ($lead) {
                    $lead->project_name = $lead->project->project ?? 'No Project'; // Add project name
                    return $lead;
                }),
            ];
        });

        // Sort by user_id
        return $groupedLeads->sortBy('user_id')->values();
    }

    public static function getLeadsByUsers($project_id = null, $filter = null, $user_id = null, $fromDate = null, $toDate = null)
    {
        // Initialize the query with necessary relationships and basic condition
        $leadsQuery = Lead::with(['users:id,name', 'project:id,project'])
                        ->where('is_active', 1);

        // Apply project filter if provided
        if (!is_null($project_id)) {
            $leadsQuery->where('project_id', $project_id);
        }

        // Apply filter for specific conditions like "schedule" or "today"
        if (!is_null($filter)) {
            if ($filter === "schedule") {
                $leadsQuery->where('follow_up', '<', now()->toDateTimeString());
            } elseif ($filter === "today") {
                $leadsQuery->whereDate('created_at', now());
            }
        }

        // Apply user filter if provided
        if (!is_null($user_id)) {
            $leadsQuery->whereHas('users', function ($query) use ($user_id) {
                $query->where('users.id', $user_id);
            });
        }

        // Apply date filters if both 'fromDate' and 'toDate' are provided
        if (!is_null($fromDate) && !is_null($toDate)) {
            $leadsQuery->whereBetween('created_at', [$fromDate, $toDate]);
        }

        // Retrieve the leads
        $leads = $leadsQuery->get();

        // Group the leads by user ID
        $groupedLeads = $leads->groupBy(function ($lead) {
            return optional($lead->users->first())->id;
        })->map(function ($userLeads, $userId) {
            return [
                'user_id' => $userId,
                'user_name' => optional($userLeads->first()->users->first())->name,
                'total_leads' => $userLeads->count(),
                'leads' => $userLeads->map(function ($lead) {
                    $lead->project_name = $lead->project->project ?? 'No Project';
                    return $lead;
                }),
            ];
        });

        // Return the grouped leads sorted by user ID
        return $groupedLeads->sortBy('user_id')->values();
    }





    public static function getActiveList($user_id = null,$status = null , $id = null, $power = false){

            $profile =  Lead::where('is_active', 1);
            if(!is_null($status)){
            $profile =  $profile->where('follow_up', '<', now()->toDateTimeString());

            }
            if(!is_null($id)){
                $profile =  $profile->where('id', $id);

            }
            if(!is_null($user_id)){
                $profile =  $profile->whereRelation('users', 'user_id', $user_id );

            }

            $profile = $profile->get();


        return $profile;
    }

    public static function getLeadByNumber($number = null,$status = null)
    {

        $profile =  Lead::where('is_active', 1);

        if(!is_null($status)){
        $profile =  $profile->where('follow_up', '<', now()->toDateTimeString());

        }
        if(!is_null($number)){
            $profile =  $profile->where('phone_number', $number)->orWhere('mobile_number', $number);

        }

        $profile = $profile->first();
        return $profile;
    }



}
