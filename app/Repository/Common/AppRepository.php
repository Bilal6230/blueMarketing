<?php

namespace App\Repository\Common;
use App\Models\Lead;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\Profiles\Profile;
use App\Models\Users\User;
use Carbon\Carbon;

class AppRepository {

    public static function getHeadList($user_id = null, $filter = null, $status = null){

         //Lead::where('user_id', $user_id);
         dd('asdasd');
         //$data_list = ProjectHeadSubhead::with('headAccounting')->where(['project_id' => $request->projectID])->get();

         $data_list = ProjectHeadSubhead::where('project_id', $projectId)
            ->distinct()
            ->pluck('head_accounting_id');

         $leads =  Lead::where('is_active', 1);
         if(!is_null($user_id)){
            $profile =  $leads->whereRelation('users', 'user_id', $user_id )->whereIn('follow_status',[2,6,3,7]);
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

        $profile = $leads->get();

        return $profile;
    }

    public static function create_error_log($controller_name, $function_name, Exception $ex, $app_user_id = NULL) {


        /*$do_no_log_codes = [-1, -2, -3, 901,902, 903, 904, 905, 906, 907, 908, 909,
            910,911,912,913,914,915,916,917,918,919,920,921,922,923,924,925,926,927,928,929,
            930,931,932,933,934,935];*/
         $do_no_log_codes = [-1, -2, -3];
 
         $code = $ex->getCode();
 
         if (in_array($code, $do_no_log_codes)) {
             $msg = $ex->getMessage();
         } else {
             if (is_null($app_user_id)) {
                 $app_user_id = 1;
             }
             $error_data = [
                 'browser' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'n/a',
                 'ip' => $_SERVER['REMOTE_ADDR'],
                 'user_id' => $app_user_id,
                 'message' => $ex->getMessage(),
                 'file' => $ex->getFile(),
                 'line_no' => $ex->getLine(),
                 'code' => $ex->getCode(),
                 'controller' => $controller_name,
                 'function' => $function_name,
                 'log_action' => 'C',
             ];
             //display_admin_debug($error_data,$app_user_id);
             $log = ErrorLog::create($error_data);
             $error_data['id'] = $log->id;
             //NyEL::create($error_data);
 
             if (!is_localhost()) {

             }
 
             $msg = "Support ticket &#35;$log->id is now registered in our system. We will resolve the issue as fast as possible and get back to you.";
         }
 
         return $msg;
    }

}



}
