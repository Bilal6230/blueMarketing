<?php

namespace App\Helpers;

use App\Models\facing;
use App\Models\Project;
use App\Models\Road;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Models\Area;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class SettingHelper
{
    public static function getValue(string $key)
    {
        return Setting::where(['key' => $key])->first()->value;
    }

    public static function getName(string $key)
    {
        return Setting::where(['key' => $key])->first()->name;
    }

    public static function getType(string $key)
    {
        return Setting::where(['key' => $key])->first()->type;
    }

    public static function getExt(string $key)
    {
        return Setting::where(['key' => $key])->first()->ext;
    }

    public static function getStatus(int $key){
        if($key == 0)
        {
            return 'inactive';
        }
        elseif($key == 1)
        {
            return 'Active';
        }
    }

    public static function sale_status(int $key){
        if($key == 0)
        {
            return 'Open';
        }
        elseif($key == 1)
        {
            return 'Sold';
        }
    }

    

    public static function get_active_project($project_id = null) {
        $query = Project::where('is_active', 1);
        
        if ($project_id !== null) {
            $query->where('id', $project_id);
        }
        
        return $query->get();
    }

    public static function get_active_roads() {
        return Road::where(['is_active' => 1])->orderBy('name', 'DESC')->get();
    }

    public static function get_active_facing() {
        return Facing::where(['is_active' => 1])->get();
    }

    public static function get_active_zone() {
        return Zone::where(['is_active' => 1])->get();
    }

    public static function get_active_area() {
        return Area::where(['is_active' => 1])->get();
    }

    function getUnitType(){

        return ['1' => 'January',
                '2' => 'February',
                '3' => 'March',
                '4' => 'April',
                '5' => 'May',
                '6' => 'June',
                '7' => 'July',
                '8' => 'August',
                '9' => 'September',
                '10'=> 'October',
                '11'=> 'November',
                '12'=> 'December',
                ];


    }

    public static function getUnitTypes($plot_type=null){

        /**
        1- Square Feet
        2- Marla
        3- Kanal

        **/
        $companies = ["","Square Feet", "Marla", "Kanal"];

        if($plot_type){
            return $companies[$plot_type];
        }

        return $companies;
    }

    public static function getgender($gender=null){

        /**
        1- Male
        0- Female

        **/
        $companies = ["","Male","Female"];

        if($gender){
            return $companies[$gender];
        }

        return $companies;
    }

    public static function getPlotType($gender=null){

        /**
        1- Residenational
        2- Commercial

        **/
        $companies = ["","Residenational","Commercial"];

        if($gender){
            return $companies[$gender];
        }

        return $companies;
    }

    public static function getPlotTypeShort($id=null){

        /**
        1- Residenational
        2- Commercial

        **/
        $companies = ["","R","C"];

        if($id){
            return $companies[$id];
        }

        return $companies;
    }

    public static function getRoadSide($short_code){
        //return $short_code;
        $profile_status = Road::where(['is_active' => 1])->findOrFail($short_code);
        return $profile_status->name;
        //return $profile_status[$short_code-1]->name;

    }

    public static function getUsersList(){
        return User::get();

    }

    public static function getCallStatus($gender=null){

        /**
        1- Invalide Number
        2- Interested
        3- Schedule Later
        4- Not Interested
        5- Sale Done
        6- New Lead
        7- Town Visit
        8- Outside Metting 

        **/
        $companies = ["","Invalide Number","Interested","Schedule Later", "Not Interested", "Sale Done", "New Lead" , 'Town Visit', 'Outside Metting' ];

        if($gender){
            return $companies[$gender];
        }

        return $companies;
    }

    public static function getCustomerType($gender=null){

        /**
        1- Interested
        2- Strong Party
        3- Final Touchup
        

        **/
        $companies = ["","Interested","Strong Party","Final Touchup" ];

        if($gender){
            return $companies[$gender];
        }

        return $companies;
    }

    public static function getColorClass($gender=null){

        /**
        1- Invalide Number
        2- Interested
        3- Schedule Later
        4- Not Interested
        5- Sale Done
        6- New
        7- Town Visit
        8- Dead Customer

        **/
        $companies = ["","badge-danger","badge-parpal","badge-warning", "badge-dark", "badge-success", "badge-info", "badge-info", "badge-danger" ];

        if($gender){
            return $companies[$gender];
        }

        return $companies;
    }

    public static function getColorCard($gender=null){

        /**
        1- Invalide Number
        2- Interested
        3- Schedule Later
        4- Not Interested
        5- Sale Done
        6- New
        7- Town Visit
        8- Dead Customer

        **/
        $companies = ["","background-color:#F12F2FFF; color:white;","background-color:#6f42c1; color:white;","background-color:#ffc107; color:white;", "badge-dark", "badge-success", "background-color:#17a2b8; color:white;", "background-color:#17a2b8; color:white;", "badge-danger" ];

        if($gender){
            return $companies[$gender];
        }

        return $companies;
    }

    public static function getProjectColorClass($project_id=null): string {

        /**
        1- Invalide Number
        2- Interested
        3- Schedule Later
        4- Not Interested
        5- Sale Done
        6- New
        7- Town Visit

        **/
        $companies = ["","badge-parpal","btn-success","btn-danger", "badge-dark", "btn-success", "btn-info", "badge-info" ];

        if (!is_scalar($project_id) || $project_id === null || $project_id === '') {
            return 'btn-secondary';
        }

        $class = $companies[(int) $project_id] ?? 'btn-secondary';

        if (is_array($class)) {
            return implode(' ', array_filter($class));
        }

        return (string) ($class ?: 'btn-secondary');
    }

    public static function getLogtype($type=null){

        /**
        1- Call
        2- Visit
        3- Message


        **/
        $companies = ["","fa-phone-slash bg-red","fa-map bg-info","fa-messages" ];

        if($type){
            return $companies[$type];
        }

        return $companies;
    }

    public static function getAreaSide($short_code){
        //return $short_code;
        $profile_status = Area::where(['is_active' => 1])->findOrFail($short_code);
        return $profile_status->name;
        //return $profile_status[$short_code-1]->name;

    }
    public static function getSectorSide($short_code){
        $profile_status = Zone::where(['is_active' => 1])->findOrFail($short_code);
        return $profile_status->zone_name;
        //return $profile_status[$short_code-1]->name;

    }

    public static function getUserRole()
    {
        return Auth::user()->roles[0]->name;
    }

    public static function getformatedDate($date){
        if(!empty($date) && $date !='0000-00-00 00:00:00')
        {
            return date('d-M-Y h:i:s a',strtotime($date));
        }
    }

    public static function getShortDate($date){
        if(!empty($date) && $date !='0000-00-00 00:00:00')
        {
            return date('d-M-Y',strtotime($date));
        }
    }

    public static function get_voucher_number()
    {
        // Replace 'ledgers' with your actual table name
        $lastId = DB::table('ledgers')->orderBy('id', 'desc')->value('id');

        $newId = $lastId+1;
        $paddedValue = str_pad($newId, 7, '0', STR_PAD_LEFT);
        return $paddedValue;
    }

    public static function is_schedule($id = null)
    {
        $totalAmount = DB::table('booking_details')
        ->where('booking_id', $id)
        ->sum('amount');

        return $totalAmount;
    }

    public static function  formatAmount($value)
    {
        return 'Rs.' . number_format($value, 2);
    }

    public static function  roundformatAmount($value)
    {
        return 'Rs.' . number_format($value, 0);
    }

    public static function getTodayLeadWorkCount()
    {
        // Get today's date
        $today = Carbon::today();

        // Query Work model for today's work count
        return Work::whereDate('created_at', $today)->count();
    }

    public static function timeRemaining($futureDateTime)
    {
        // Parse the future date using Carbon
        $future = Carbon::parse($futureDateTime);
        $now = Carbon::now();

        // Check if the future time is in the past
        if ($future->isPast()) {
            return 'Time has already passed.';
        }

        // Calculate the difference
        $diffInMonths = $now->diffInMonths($future);
        $diffInDays = $now->diffInDays($future) % 30; // Days excluding complete months
        $diffInHours = $now->diffInHours($future) % 24; // Hours excluding complete days

        // Build the human-readable format
        $result = [];
        if ($diffInMonths > 0) {
            $result[] = "$diffInMonths months";
        }
        if ($diffInDays > 0) {
            $result[] = "$diffInDays days";
        }
        if ($diffInHours > 0) {
            $result[] = "$diffInHours hours";
        }

        // Join the parts with commas
        return implode(', ', $result);
    }
}
