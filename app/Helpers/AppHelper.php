<?php

use Carbon\Carbon;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\JournalVoucher;
use App\Models\ProjectHeadSubhead;

use Illuminate\Support\Facades\Cache;
use function PHPUnit\Framework\isNull;

/**
 * @developedBy Ali Waqas
 * @date Jan 1, 2024
 * @version Version 1.0
 * @author Ali Waqas <aliwaqas.sdk@hotmail.com>
 */



function is_localhost()
{
    $whitelist = array(
        '127.0.0.1',
        '::1'
    );
    if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
        return TRUE;
    }
    return FALSE;
}

/*************** TextEditior **************************/
function Html2Text($txt)
{

    //$txt = strip_tags( (new \Html2Text\Html2Text($txt))->getText());
    //$txt = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $txt);
    $txt = strip_tags($txt);
    return ($txt);
}

function removeMultipleLineBreakWithOne($data)
{
    $searches = array("\r", "\n", "\r\n");
    return str_replace($searches, "", $data);
}

function te($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    die;
}

function getMonths()
{

    return [
        '1' => 'January',
        '2' => 'February',
        '3' => 'March',
        '4' => 'April',
        '5' => 'May',
        '6' => 'June',
        '7' => 'July',
        '8' => 'August',
        '9' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    ];
}
function getBeforeDecimal($string)
{

    $str_arr = explode('.', $string);
    return $str_arr[0];  // Before the Decimal point
    // echo $str_arr[1];  // After the Decimal point
}
function getAfterDecimal($string)
{

    $str_arr = explode('.', $string);
    // echo $str_arr[0];  // Before the Decimal point
    return $str_arr[1];  // After the Decimal point
}
function getMinutesPercentage($string)
{

    $str_arr = explode('.', $string);
    // echo $str_arr[0];  // Before the Decimal point
    if (isset($str_arr[1])) {
        $percentage = ($str_arr[1] * 100) / 60;
        $final_value = $str_arr[0] . '.' . $percentage;
    } else {
        $final_value = $str_arr[0] . '.' . '00';
    }
    return $final_value;  // After the Decimal point
}
function SeperateTime($time, $part)
{
    $time = explode('.', $time);

    if ($part == 'h') {
        return $time[0];
    } else {
        if (isset($time[1])) {
            return $time[1];
        } else {
            return '0';
        }
    }
}

function getAssignmentTime_min()
{

    return [0, 25, 5, 75];
}

function getAfterHyphen($string)
{

    $str_arr = explode('-', $string);
    // echo $str_arr[0];  // Before the Decimal point
    return $str_arr[1];  // After the Decimal point
}
function getMonthNumber($month)
{
    $months = ["January" => "1", "February" => "2", "March" => "3", "April" => "4", "May" => "5", "June" => "6", "July" => "7", "August" => "8", "September" => "9", "October" => "10", "November" => "11", "December" => "12"];
    foreach ($months as $key => $value) {
        if ($month == $key) {
            $month_num = $value;
        }
    }
    return $month_num;
}

function getMonthByNumber($month)
{
    $months = [
        "1" => "January",
        "2" => "February",
        "3" => "March",
        "4" => "April",
        "5" => "May",
        "6" => "June",
        "7" => "July",
        "8" => "August",
        "9" => "September",
        "10" => "October",
        "11" => "November",
        "12" => "December"
    ];

    foreach ($months as $key => $value) {
        if ($month == $key) {
            $month_num = $value;
        }
    }
    return $month_num;
}

function getHead($id)
{
    $heads = [
        "0" => "Please Update",
        "1" => "Assets",
        "2" => "Owner",
        "3" => "Recovery",
        "4" => "Expence",
        "5" => "Amanat Pyments"

    ];

    foreach ($heads as $key => $value) {
        if ($id == $key) {
            $month_num = $value;
        }
    }
    return $month_num;
}



function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
function validateMonth($month)
{
    $month = strtolower($month);
    $months = ["january" => "1", "february" => "2", "march" => "3", "april" => "4", "may" => "5", "june" => "6", "july" => "7", "august" => "8", "september" => "9", "october" => "10", "november" => "11", "december" => "12"];
    foreach ($months as $key => $value) {
        if ($month == $key) {
            return $value;
        }
    }
}




function make_writeable_dir($path)
{
    //echo($path);
    //die;
    if (!is_dir($path)) {
        mkdir($path);
    }
    if (!is_writable($path)) {
        chmod($path, 0777);
    }
}


function get_feedback_types($type_id = NULL)
{

    $op_arr = ['Submit a bug', 'I need help', 'I’ve got an idea'];

    if (!is_null($type_id)) {
        return $op_arr[$type_id];
    }
    return $op_arr;
}


function removeHtml($data)
{

    foreach ($data as $i => $row) {
        foreach ($row as $col => $val) {
            $data[$i][$col] = strip_tags($val);
        }
    }

    return $data;
}

function urtype($case = "u")
{

    $n = "CONSULTANT";
    if ($case == "c") {
        $n = ucfirst(strtolower($n));
    }

    return $n;
}

function numberFormat($n)
{

    return number_format((float) $n, 2, '.', ',');
}



function formatDateExperience($date)
{
    if (!empty($date) && $date != '0000-00-00 00:00:00') {

        return date('m-Y', strtotime($date));
    }
}

function formatDateExperience2($date)
{
    if (!empty($date) && $date != '0000-00-00 00:00:00') {

        return date('m.Y', strtotime($date));
    }
}

function formatDateExperience3($date)
{
    if (!empty($date) && $date != '0000-00-00 00:00:00') {

        return date('d F Y \a\t H:i ', strtotime($date));
    }
}
function formatDateExperience4($date)
{
    if (!empty($date) && $date != '0000-00-00 00:00:00') {

        return date('d-m-Y ', strtotime($date));
    }
}


if (!function_exists('getLastLedgerIdByType')) {
    function getLastLedgerIdByType($type, $id = null)
    {
        $query = App\Models\Ledger::where('type', $type);

        if ($id === null) {
            $query->latest('id');
        } else {
            $query->where('id', $id);
        }

        return $query->value('type_id');
    }
}
function getVocuherNumber($type)
{
    $numbers = Ledger::where('type', $type)
        ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', getSelectedTown()))
        ->where('voucher_number', '>', 0)
        ->orderBy('voucher_number')
        ->pluck('voucher_number')
        ->toArray();

    $nextNumber = 1; // default starting number

    if (!empty($numbers)) {
        $allNumbers = range(min($numbers), max($numbers));
        $missing = array_diff($allNumbers, $numbers);
        if (!empty($missing)) {
            // Get the smallest missing number
            $nextNumber = min($missing);
        } else {
            // If no missing numbers, continue from max
            $nextNumber = max($numbers) + 1;
        }
    }

    return $nextNumber;
}

function get_new_voucher_number($type, $id = null)
{
    // Replace 'ledgers' with your actual table name
    $lastId = getLastLedgerIdByType($type, $id);

    if (isNull($id)) {
        $newId = $lastId + 1;
    } else {
        $newId = $lastId;
    }
    $paddedValue = str_pad($newId, 7, '0', STR_PAD_LEFT);
    return $paddedValue;
}

function test()
{
    return "adas";
}

if (!function_exists('get_new_booking_number')) {
    function get_new_booking_number($id = null)
    {
        $query = App\Models\Booking::query();

        if ($id !== null) {
            $query->where('id', $id);
        } else {
            $query->latest('id');
        }

        $lastId = $query->value('id');

        if ($id === null) {
            $newId = $lastId + 1;
        } else {
            $newId = $lastId;
        }

        $paddedValue = str_pad($newId, 7, '0', STR_PAD_LEFT);
        return $paddedValue;
    }
}

if (!function_exists('getProjects')) {
    function getProjects()
    {
        return \App\Models\Project::get();
    }
}

function getSelectedTown_old()
{
    if (Cache::has('selected_action')) {
        return Cache::get('selected_action');
    } else {
        return null; // or any default value you want to assign
    }
}

function getSelectedTown()
{
    // Use the request() helper to access the current HTTP request
    $selectedAction = request()->cookie('selected_action');

    return $selectedAction ?? null; // Return the value or null if not found
}

if (!function_exists('getInstallmentOptions')) {
    function getInstallmentOptions()
    {
        return [
            'token' => 'Token',
            'booking' => 'Booking',
            'advance' => 'Advance',
            'position' => 'Position',
            'installemnt1' => 'Installment',
            'installemnt2' => 'Additional Installment',
            // Add more options here if needed
        ];
    }
}


function get_new_booking_voucher($type, $id = null)
{
    // Helper function to get the last ledger ID by plot
    $getLastLedgerIdByplot = function ($type, $id = null) {
        $query = App\Models\CustomerLedger::where('transaction_type', $type);

        if ($id === null) {
            $query->latest('id');
        } else {
            $query->where('id', $id);
        }

        return $query->value('type_id');
    };

    // Replace 'ledgers' with your actual table name
    $lastId = $getLastLedgerIdByplot($type, $id);

    $newId = is_null($id) ? $lastId + 1 : $lastId;

    $paddedValue = str_pad($newId, 7, '0', STR_PAD_LEFT);
    return $paddedValue;
}

function get_new_typeID($type, $id = null)
{
    // Helper function to get the last ledger ID by plot
    $getLastLedgerIdByplot = function ($type, $id = null) {
        $query = App\Models\CustomerLedger::where('transaction_type', $type);

        if ($id === null) {
            $query->latest('id');
        } else {
            $query->where('id', $id);
        }

        return $query->value('type_id');
    };

    // Replace 'ledgers' with your actual table name
    $lastId = $getLastLedgerIdByplot($type, $id);

    $newId = is_null($id) ? $lastId + 1 : $lastId;

    return $newId;
}

function getSumAmountForBooking($booking_id = null)
{
    // Get the sum of the amount column from the CustomerLedger model
    $sum = App\Models\BookingDetail::where('booking_id', $booking_id)->sum('amount');

    return $sum;
}

function getSumDueAmount($booking_id = null)
{
    // Get the sum of the amount column from the BookingDetail model where due_date <= today
    $sum = App\Models\BookingDetail::where('booking_id', $booking_id)
        ->where('due_date', '<=', Carbon::today())
        ->sum('amount');


    return $sum;
}

function getSumRecovery($plot_id = null, $value = null)
{
    // Get the sum of the amount column from the CustomerLedger model
    $sum = App\Models\CustomerLedger::where('plot_id', $plot_id)->where('is_active', 1)->sum($value);

    return $sum;
}


function getPakistanBanks()
{
    // Define an array of Pakistani banks
    $pakistanBanks = [
        ['id' => 1, 'name' => 'Bank Alfalah'],
        ['id' => 2, 'name' => 'Habib Bank Limited (HBL)'],
        ['id' => 3, 'name' => 'United Bank Limited (UBL)'],
        ['id' => 4, 'name' => 'National Bank of Pakistan'],
        ['id' => 5, 'name' => 'MCB Bank'],
        ['id' => 6, 'name' => 'Allied Bank Limited'],
        ['id' => 7, 'name' => 'Dubai Islamic Bank Pakistan'],
        ['id' => 8, 'name' => 'Faysal Bank'],
        ['id' => 9, 'name' => 'JS Bank'],
        ['id' => 10, 'name' => 'Bank of Punjab'],
        ['id' => 11, 'name' => 'Sindh Bank'],
        ['id' => 12, 'name' => 'Bank of Khyber'],
        ['id' => 13, 'name' => 'Zarai Taraqiati Bank Limited'],
        ['id' => 14, 'name' => 'Askari Bank'],
        ['id' => 15, 'name' => 'The Bank of Punjab'],
        ['id' => 16, 'name' => 'Habib Metropolitan Bank'],
        ['id' => 17, 'name' => 'Meezan Bank'],
        ['id' => 18, 'name' => 'Al Baraka Bank Pakistan'],
        ['id' => 19, 'name' => 'Burj Bank'],
        ['id' => 20, 'name' => 'Dawood Islamic Bank'],
        ['id' => 21, 'name' => 'Soneri Bank'],
        ['id' => 22, 'name' => 'Summit Bank'],
        ['id' => 23, 'name' => 'BankIslami Pakistan'],
        ['id' => 24, 'name' => 'UBL UK'],
        ['id' => 25, 'name' => 'Dubai Islamic Bank'],
        ['id' => 26, 'name' => 'Faysal Islamic Bank'],
        ['id' => 27, 'name' => 'Bank Al-Habib'],

        // Add more banks as needed
    ];

    return $pakistanBanks;
}

function getBankNameById($id)
{
    $banks = getPakistanBanks();
    foreach ($banks as $bank) {
        if ($bank['id'] == $id) {
            return $bank['name'];
        }
    }
    return null; // Return null if bank not found
}

function getPaymentTypeName($value)
{
    $paymentTypes = [
        '1' => 'Cash',
        '2' => 'Online',
        '3' => 'Check',
        // Add more payment types as needed
    ];

    return $paymentTypes[$value] ?? 'Unknown';
}

function getPaymentTypeDetails($value)
{
    $paymentTypes = [
        '1' => ['name' => 'Cash', 'badge' => 'badge-success'], // Green badge
        '2' => ['name' => 'Online', 'badge' => 'badge-primary'], // Blue badge
        '3' => ['name' => 'Check', 'badge' => 'badge-warning'], // Yellow badge
        // Add more payment types as needed
    ];

    return $paymentTypes[$value] ?? ['name' => 'Unknown', 'badge' => 'badge-secondary']; // Default gray badge
}

function approveStatus($value)
{
    $paymentTypes = [
        '0' => 'Pending',
        '1' => 'Approve',
        '2' => 'Reject',
        // Add more payment types as needed
    ];

    return $paymentTypes[$value] ?? 'Unknown';
}

function check_status()
{
    // Define an array of statuses with badge classes
    $statuslist = [
        ['id' => 0, 'name' => 'Pending', 'badge' => 'badge-warning'], // Yellow badge
        ['id' => 1, 'name' => 'Pass', 'badge' => 'badge-success'],    // Green badge
        ['id' => 2, 'name' => 'Memo', 'badge' => 'badge-info'],       // Blue badge
        ['id' => 3, 'name' => 'Cancel', 'badge' => 'badge-danger'],   // Red badge
        ['id' => 4, 'name' => 'Return', 'badge' => 'badge-secondary'], // Gray badge

        // Add more statuses as needed
    ];

    return $statuslist;
}

function check_status_id($id)
{
    $list = check_status();
    foreach ($list as $v) {
        if ($v['id'] === $id) {
            return $v['name'];
        }
    }
    return null; // Return null if bank not found
}

function getProjectDetails($projectId)
{
    // Retrieve the project by its ID
    $project = Project::find($projectId);

    // If the project exists, return the project name and address
    if ($project) {
        return [
            'project' => $project->project,
            'address' => $project->address,
        ];
    }

    // Return null if no project is found
    return null;
}

// In your app/Helpers/AppHelper.php or a relevant helper file
function getHeadAccountNameById($creditAccountId)
{
    // Retrieve the ProjectHeadSubhead by its creditAccountId
    $account = ProjectHeadSubhead::with('headAccounting')->find($creditAccountId);

    // Check if account exists and return the name, else return a default message
    return $account && $account->headAccounting ? $account->headAccounting->name : 'Not Found';
}

function getSubAccountNameById($creditAccountId)
{
    // Retrieve the ProjectHeadSubhead by its creditAccountId
    $account = ProjectHeadSubhead::with('subheadAccounting')->find($creditAccountId);

    // Check if account exists and return the name, else return a default message
    return $account && $account->subheadAccounting ? $account->subheadAccounting->name : 'Not Found';
}


if (!function_exists('getLastJvId')) {
    /**
     * Get the last journal voucher ID.
     *
     * @return int|null
     */
    function getLastJvId()
    {
        $selectedProjectId = getSelectedTown();
        return JournalVoucher::where('project_id', $selectedProjectId)->withTrashed()->max('id');
    }
}
if (!function_exists('getLastJvVNumber')) {
    /**
     * Get the last journal voucher ID.
     *
     * @return int|null
     */
    function getLastJvVNumber()
    {
        $selectedProjectId = getSelectedTown();
        return JournalVoucher::where('project_id', $selectedProjectId)->withTrashed()->latest()->value('voucher_number');
    }
}


function get_jv_number($id = null)
{
    $paddedValue = str_pad($id, 7, '0', STR_PAD_LEFT);
    return $paddedValue;
}


if (!function_exists('loadAttendanceWeek')) {
    function loadAttendanceWeek($weekInput)
    {
        try {
            if($weekInput) {
                $start = Carbon::parse($weekInput);
            }
            else{
                $start = now();
            }
        } catch (\Exception $e) {
            $start = now();
        }

        /**
         * Force the week start to Friday
         * Carbon::setWeekStartsAt() affects global state — avoid that.
         * Instead, manually shift to nearest Friday.
         */
        $start = $start->copy()->startOfWeek(Carbon::FRIDAY);

        // End of week should be Thursday (6 days after Friday)
        $end = $start->copy()->addDays(6);

        // Generate 7 days Friday → Thursday
        $days = [];
        $day = $start->copy();

        while ($day <= $end) {
            $days[] = $day->format('Y-m-d');
            $day->addDay();
        }

        return [$start, $end, $days];
    }
}

if (!function_exists('numberToUrduWords')) {
    function numberToUrduWords($number)
    {
        $words = [
            0 => 'صفر',
            1 => 'ایک',
            2 => 'دو',
            3 => 'تین',
            4 => 'چار',
            5 => 'پانچ',
            6 => 'چھے',
            7 => 'سات',
            8 => 'آٹھ',
            9 => 'نو',
            10 => 'دس',
            11 => 'گیارہ',
            12 => 'بارہ',
            13 => 'تیرہ',
            14 => 'چودہ',
            15 => 'پندرہ',
            16 => 'سولہ',
            17 => 'سترہ',
            18 => 'اٹھارہ',
            19 => 'انیس',
            20 => 'بیس',
            21 => 'اکیس',
            22 => 'بائیس',
            23 => 'تئیس',
            24 => 'چوبیس',
            25 => 'پچیس',
            26 => 'چھبیس',
            27 => 'ستائیس',
            28 => 'اٹھائیس',
            29 => 'انتیس',

            30 => 'تیس',
            31 => 'اکتیس',
            32 => 'بتیس',
            33 => 'تینتیس',
            34 => 'چونتیس',
            35 => 'پینتیس',
            36 => 'چھتیس',
            37 => 'سینتیس',
            38 => 'اڑتیس',
            39 => 'انتالیس',

            40 => 'چالیس',
            41 => 'اکتالیس',
            42 => 'بیالیس',
            43 => 'تینتالیس',
            44 => 'چوالیس',
            45 => 'پینتالیس',
            46 => 'چھیالیس',
            47 => 'سینتالیس',
            48 => 'اڑتالیس',
            49 => 'انچاس',

            50 => 'پچاس',
            51 => 'اکیاون',
            52 => 'باون',
            53 => 'ترپن',
            54 => 'چون',
            55 => 'پچپن',
            56 => 'چھپن',
            57 => 'ستاون',
            58 => 'اٹھاون',
            59 => 'انسٹھ',

            60 => 'ساٹھ',
            61 => 'اکھتر',
            62 => 'باسٹھ',
            63 => 'تریسٹھ',
            64 => 'چونسٹھ',
            65 => 'پینسٹھ',
            66 => 'چھیاسٹھ',
            67 => 'سڑسٹھ',
            68 => 'اڑسٹھ',
            69 => 'انہتر',

            70 => 'ستر',
            71 => 'اکہتر',
            72 => 'بہتر',
            73 => 'تہتر',
            74 => 'چوہتر',
            75 => 'پچھتر',
            76 => 'چھیتر',
            77 => 'ستتر',
            78 => 'اٹھہتر',
            79 => 'انہتر',

            80 => 'اسی',
            81 => 'اکیاسی',
            82 => 'بیاسی',
            83 => 'تریاسی',
            84 => 'چوراسی',
            85 => 'پچاسی',
            86 => 'چھیاسی',
            87 => 'ستاسی',
            88 => 'اٹھاسی',
            89 => 'نواسی',

            90 => 'نوے',
            91 => 'اکانوے',
            92 => 'بانوے',
            93 => 'ترانوے',
            94 => 'چورانوے',
            95 => 'پچانوے',
            96 => 'چھیانوے',
            97 => 'ستانوے',
            98 => 'اٹھانوے',
            99 => 'ننانوے',

            100 => 'سو',
            1000 => 'ہزار',
            100000 => 'لاکھ',
            10000000 => 'کروڑ'
        ];

        if ($number < 100) {
            return $words[$number];
        }

        if ($number < 1000) {
            $hundreds = intval($number / 100);
            $remainder = $number % 100;
            return $words[$hundreds] . ' سو' . ($remainder ? ' ' . numberToUrduWords($remainder) : '');
        }

        foreach ([10000000 => 'کروڑ', 100000 => 'لاکھ', 1000 => 'ہزار'] as $value => $label) {
            if ($number >= $value) {
                $quot = intval($number / $value);
                $rem = $number % $value;

                return numberToUrduWords($quot) . " $label" . ($rem ? ' ' . numberToUrduWords($rem) : '');
            }
        }

        return '';
    }
}
