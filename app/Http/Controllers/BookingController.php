<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Plot;
use App\Models\Installment;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\User;
use App\Models\CustomerLedger;
use App\Models\HeadAccounting;
use App\Models\Ledger;
use App\Models\SubheadAccounting;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $projectId      = getSelectedTown();
        $x['title']     = 'Plots Sale List';
        $x['data']      = Booking::with('customer', 'plot', 'project','broker')->where('project_id', $projectId)->get();
        $x['role']      = Role::get();

        // Calculate the sum of the sale amount
        $x['totalSaleAmount'] = $x['data']->sum('total_price'); // Sum of the total_price field

        return view('admin.booking.index', $x);
    }


    public function recovery_list()
    {
        $x['title'] = 'Project Recovery Report';
        $today = Carbon::today()->toDateString();

        // Fetch data with required relations
        $x['data'] = Booking::with([
            'customer',
            'plot',
            'project',
            'broker',
            'bookingDetails' => function ($query) use ($today) {
                $query->whereDate('due_date', '<=', $today);
            }
        ])
        ->where('project_id', getSelectedTown())
        ->get();

        // Calculate the sum of Due Amounts and Received
        $x['sum_due_amount'] = $x['data']->sum(function ($booking) {
            return getSumDueAmount($booking->id); // Helper to calculate due amount
        });

        $x['sum_received'] = $x['data']->sum(function ($booking) {
            return getSumRecovery($booking->plot_id, 'amount_out'); // Helper to calculate received amount
        });

        $x['role'] = Role::get();

        return view('admin.reports.bookings.project_recovery', $x);
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function sale()
    {
        $x['title']     = 'New Booking invoice';
        $x['data']      = Plot::get();
        $x['role']      = Role::get();
        $x['installments'] = Installment::get();
        $projects = Project::where('id', getSelectedTown() )->get();
        $x['projects'] = $projects;
        // Retrieve the joined data from project_head_subheads and subhead_accountings
        $brokers = ProjectHeadSubhead::where('head_accounting_id', 6)
        ->join('subhead_accountings', 'project_head_subheads.subhead_accounting_id', '=', 'subhead_accountings.id')
        ->get();

        $x['brokers'] = $brokers;

        return view('admin.booking.sale', $x);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

     public function store(Request $request)
    {
        
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'customer_id' => 'required|exists:leads,id',
            'plot_id' => 'required|string',
            'plot_type' => 'required',
            'plot_size' => 'required',
            'plot_rate' => 'required',
            'total_price' => 'required',
            'booking_date' => 'required|date',
            'status' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $plotType = $request->plot_type;
        if ($plotType == 1) {
            $plotType = 'R-';
        } elseif ($plotType == 2) {
            $plotType = 'C-';
        }

        DB::beginTransaction();

        try {
            // Create the booking
            $booking = Booking::create([
                'project_id' => $request->input('project_id'),
                'customer_id' => $request->input('customer_id'),
                'plot_id' => $request->input('plot_id'),
                'plot_type' => $request->input('plot_type'),
                'plot_size' => $request->input('plot_size'),
                'plot_rate' => str_replace(',', '', $request->input('plot_rate')),
                'is_corner' => $request->input('is_corner') ?? 0,
                'is_park' => $request->input('is_park') ?? 0,
                'park_facing' => $request->input('park_facing') ?? 0,
                'carner_price' => $request->input('carner_price') ?? 0,
                'dicount_value' => $request->input('discount_value') ?? 0,
                'total_price' => str_replace(',', '', $request->input('total_price')),
                'broker_id' => $request->input('broker_id'),
                'booking_date' => $request->input('booking_date'),
                'status' => $request->input('status'),
                'user_id' => Auth::user()->id,
            ]);

            // Update the plot status
            Plot::where('id', $booking->plot_id)->update(['sold' => 1]);

            // Fetch plot and customer details
            $plotName = Plot::where('id', $booking->plot_id)->value('name');
            $customer = Lead::findOrFail($booking->customer_id);

            // Create customer ledger entry
            CustomerLedger::create([
                'customer_id' => $booking->customer_id,
                'transaction_type' => 'Bo',
                'type_id' => get_new_typeID('Bo'),
                'project_id' => $booking->project_id,
                'plot_id' => $booking->plot_id,
                'amount_in' => $booking->total_price,
                'amount_out' => 0,
                'description' => 'Booking for plot '.$plotType . $plotName,
                'date' => $booking->booking_date,
                'is_approve' => 1,
                'is_active' => 1,
            ]);

            // Create subhead accounting
            $subheadAccounting = SubheadAccounting::create([
                'name' => '(' .$plotType. $plotName . ') ' . $customer->first_name . ' ' . $customer->last_name,
                'is_active' => 1,
                'cnic' => $customer->nic_number ?? '00000',
                'phone' => $customer->phone_number ?? '00000',
                'create_by' => Auth::user()->id,
            ]);

            $pivot = ProjectHeadSubhead::create([
                'project_id' => $booking->project_id,
                'head_accounting_id' => 16,
                'subhead_accounting_id' => $subheadAccounting->id,
                'plot_id' => $booking->plot_id,
                'customer_id' => $booking->customer_id,
            ]);
            
            

            // Create ledger entry
            $lastId = getLastLedgerIdByType("BO");
            Ledger::create([
                'type' => 'BO',
                'type_id' => $lastId + 1,
                'project_head_subheads_id' => $pivot->id,
                'reference' => $booking->id,
                'amount_in' => 0.00,
                'amount_out' => $booking->total_price,
                'is_active' => 1,
                'date' => $booking->booking_date,
                'detail' => 'Booking for plot ' .$plotType . $plotName,
                'update_by' => Auth::user()->id,
                'create_by' => Auth::user()->id,
                'status' => 0,
            ]);
            
            // Ensure credit account exists
            $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', 16)
            ->where('project_id', $booking->project_id)
            ->where('subhead_accounting_id', 123)
            ->value('id');

            if (!$creditAccountId) {
                throw new \Exception('Credit account ID not found.');
            }

            Ledger::create([
                'type' => 'CR',
                'type_id' => $lastId + 2,
                'project_head_subheads_id' => $creditAccountId,
                'reference' => $booking->id,
                'amount_in' => $booking->total_price,
                'amount_out' => 0.00,
                'is_active' => 1,
                'date' => $booking->booking_date,
                'detail' => 'Booking for plot ' .$plotType. $plotName,
                'update_by' => Auth::user()->id,
                'create_by' => Auth::user()->id,
                'status' => 0,
            ]);

            DB::commit();

            Alert::success('Notification', 'Data saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Log::error('Error storing booking: ' . $th->getMessage());
            Alert::error('Notification', 'An error occurred: ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }

   

    public function show(Request $request)
    {
        $data_list = Plot::where(['id' => $request->id])->first();


        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Project by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }

    
    public function edit(Plot $plot)
    {
        //
    }

    
    public function update(Request $request, Plot $plot)
    {
        //
    }

 
    public function destroy(Request $request)
    {
        $data = [
            'is_active' => "0",
        ];
        DB::beginTransaction();
        try {
            $result = CustomerLedger::find($request->id);
            $result->update($data);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> Deleted')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> failed to delete: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function approve(Request $request)
    {
        // dd($request->input());
        $data = [
            'is_approve' => $request->is_approve,
        ];
        DB::beginTransaction();
        try {
            $result = CustomerLedger::find($request->id);
            $result->update($data);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> update')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> failed to update: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function getCustomers(Request $request)
    {
        // Retrieve project ID from the AJAX request
        $projectId = $request->input('project_id');

        // Query the database to get customers associated with the project
        $customers = Lead::where('project_id', $projectId)->get();

        // Return customers as JSON response
        return response()->json($customers);
    }
    public function getCustomersbyPlot_old(Request $request)
    {
        // Retrieve project ID from the AJAX request
        $projectId = $request->input('project_id');

        // Query the database to get customers associated with the project
        $customers  = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')->where('leads.project_id', $projectId)
        ->select('leads.*') // Select all columns from the leads table
        ->get();

        dd($customers);

        // Return customers as JSON response
        return response()->json($customers);
    }

    public function getCustomersbyPlot(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        // Retrieve project ID
        $projectId = $request->input('project_id');

        // Query the database to get unique customers associated with the project
        $customers = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $projectId)
            ->whereNull('bookings.deleted_at') // Exclude soft-deleted bookings
            ->select('leads.*') // Select all columns from the leads table
            ->distinct() // Ensure uniqueness by lead id
            ->get();

        // Check if customers are found
        if ($customers->isEmpty()) {
            return response()->json(['message' => 'No customers found'], 404);
        }

        // Return unique customers as JSON response
        return response()->json($customers);
    }


    public function getPlots(Request $request)
    {
        $plot_type = $request->input('plot_type');
        $projectId = $request->input('project_id');
        
        $customers = Plot::where('type', $plot_type)->where('project_id', $projectId)->where('sold', 0)->get();

        // Return customers as JSON response
        return response()->json($customers);
    }

    public function getCustomerPlots(Request $request)
    {
        $projectId = $request->input('project_id');
        $customerId = $request->input('customer_id');
        
        $customers = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
                        ->where('plots.project_id', $projectId)
                        ->where('bookings.customer_id', $customerId)
                        ->get();

            // Return customers as JSON response
        return response()->json($customers);
    }

    public function scheduleForm(Request $request, $id)
    {
        $booking_id     = $id;
        $x['title']     = 'Plots Sale List';
        $x['data']      = Booking::with('customer', 'plot', 'project')->findOrFail($booking_id);
        $x['role']      = Role::get();

        return view('admin.booking.schedule_form', $x);
    }

    public function PriceForm(Request $request, $id)
    {
        $booking_id     = $id;
        $x['title']     = 'Plots Price Update';
        $x['data']      = Booking::with('customer', 'plot', 'project')->findOrFail($booking_id);
        $x['role']      = Role::get();

        return view('admin.booking.price_form', $x);
    }


    public function storePaymentSchedule(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'total_price' => 'required|numeric',
            'instalment' => 'required|numeric|digits_between:1,2', // Set maximum 2 digits
            'payment_plan' => 'required|in:1,3,6,12',
            'start_date' => 'required|date',
            'installment_number.*' => 'required',
            'amount.*' => 'required|numeric',
            'due_date.*' => 'required|date',
        ]);

        // Delete existing booking details with the provided booking_id
        BookingDetail::where('booking_id', $request->input('booking_id'))->delete();

        // Store booking details data
        $bookingId = $request->input('booking_id');
        $totalPrice = $request->input('total_price');
        $instalment = $request->input('instalment');
        $paymentPlan = $request->input('payment_plan');
        $startDate = $request->input('start_date');
        $installmentNumbers = $request->input('installment_number');
        $amounts = $request->input('amount');
        $dueDates = $request->input('due_date');

        // Store initial booking details
        foreach ($installmentNumbers as $key => $installmentNumber) {
            BookingDetail::create([
                'booking_id' => $bookingId,
                'installment_details' =>  $installmentNumber,
                'amount' => $amounts[$key],
                'due_date' => $dueDates[$key],
            ]);
        }

        // Calculate remaining amount after deducting initial installments
        $installmentAmount = array_sum($amounts);
        $remaining = $totalPrice - $installmentAmount;

        // Calculate installment amount excluding decimals
        $installmentNewAmount = floor($remaining / $instalment);

        // Calculate total amount distributed among installments including decimals
        $totalDistributedAmount = $installmentNewAmount * $instalment;

        // Calculate the remaining decimal amount
        $remainingDecimals = $remaining - $totalDistributedAmount;
        // dd( $remainingDecimals);

        // Calculate next installment due date
        $nextDueDate = $startDate;

        // Store booking details data for new installments
        for ($i = 1; $i <= $instalment; $i++) {
            // dd($instalment);
            // Calculate due date for this installment
            $dueDate = $nextDueDate;

            // If it's not the last installment, calculate next due date
            if ($i < $instalment) {
                $nextDueDate = date('Y-m-d', strtotime("+" . $paymentPlan . " months", strtotime($nextDueDate)));
            }

            // Determine the amount for this installment
            $amount = $installmentNewAmount;

            // If it's the last installment, add the remaining amount including decimals
            if ($i == $instalment) {
                $amount += $remainingDecimals;
            }

            // Create booking detail
            BookingDetail::create([
                'booking_id' => $bookingId,
                'installment_details' => 'Installment ' . $i,
                'amount' => $amount,
                'due_date' => $dueDate,
            ]);
        }

        // Redirect back with success message
        return redirect()->route('booking.plot.index')->with('success', 'Booking details created successfully.');

        // return back()->with('success', 'Booking details created successfully.');
    }




    public function cash_in()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Receive Plot Payment';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'PPR';
        $x['class']      =   'cash-in';
        $x['bg_voucher'] = 'info-cash-in';

        $data = CustomerLedger::with('customer_list','plot_list')->where('transaction_type', 'PPR')->where('is_active', '1')->where('project_id', getSelectedTown())->get();
        
        $x['data'] = $data;
        
        $projects = Project::where('id', getSelectedTown() )->get();
        $x['projects'] = $projects;
        
        return view('admin.booking.receive', $x);
    }

    public function customer_report_form(Request $request)
    {
        $x['title']     = 'Customer Report';
        // $x['data']      = Booking::with('customer', 'plot', 'project')->findOrFail(1);
        $x['role']      = Role::get();
        // dd("asda");

        return view('admin.reports.bookings.customer_report', $x);
    }

    
    public function deposit(Request $request)
    {
        // dd($request->input());
        // Validate the incoming request data
        $validatedData = $request->validate([
            'project_id' => 'required',
            'customer_id' => 'required',
            'plot_id' => 'required',
            'plot_id' => 'required',
            'amount' => 'required',
            'detail' => 'required',
            'payment_type' => 'required',
            'reference' =>'required',
          
        ]);
        ;

        $action = $request->input('action');
        $customer_id = $request->input('customer_id');
        $x['today'] = date("d-m-Y");
        
        try{
            switch($action)
            {
                
                case 'deposit':
                    // Create entry in customer ledger
                    // dd($request->input());
                    $payment_type = $request->input('payment_type');
                    if($payment_type ==  1)
                    {
                        $t_number = $bank_id =   null;
                    }
                    else
                    {  
                        $t_number = $request->input('t_number');
                        $bank_id = $request->input('bank_id');

                    }
                    $plotName = Plot::where('id', $request->input('plot_id'))->value('name');
                    $data = CustomerLedger::create([
                        'customer_id' => $customer_id,
                        'transaction_type' => 'PPR',
                        'type_id' => get_new_typeID('PPR'),
                        'reference' => $request->input('reference'),
                        'project_id' => $request->input('project_id'),
                        'plot_id' => $request->input('plot_id'),
                        'amount_in' => 0,
                        'amount_out' => str_replace(',', '', $request->input('amount')),
                        'description' => $request->input('detail'),
                        'date' => $request->input('date'), // Assuming booking date is the transaction date
                        'payment_type' => $request->input('payment_type'),
                        't_number' => $t_number,
                        'bank_id' => $bank_id,
                        'is_active' => 1,
                        'is_approve' => 0,
                        'passing_date' => $request->input('passing_date'),
                    ]);


                    // Ensure credit account exists
                    $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', 16)
                    ->where('project_id', $request->input('project_id'))
                    ->where('plot_id', $request->input('plot_id'))
                    ->where('customer_id', $customer_id)
                    ->value('id');

                    if (!$creditAccountId) {
                        throw new \Exception('Credit account ID not found.');
                    }

                    $plotName = Plot::where('id', $request->input('plot_id'))->value('name');

                    $lastId = getLastLedgerIdByType("CR");
        
                    Ledger::create([
                        'type' => 'CR',
                        'type_id' => $lastId + 1,
                        'project_head_subheads_id' => $creditAccountId,
                        'reference' => $request->input('voucher'),
                        'amount_in' => str_replace(',', '', $request->input('amount')),
                        'amount_out' => 0.00,
                        'is_active' => 1,
                        'date' => $request->input('date'), // Assuming booking date is the transaction date
                        'detail' => '(Cash slip#' .$request->input('reference').') '.$request->input('detail'),
                        'update_by' => Auth::user()->id,
                        'create_by' => Auth::user()->id,
                        'status' => 0,
                    ]);


                    Alert::success('Notification', 'Data <b></b> Save successfully ')->toToast()->toHtml();


                break;
            }

        }catch(\Exception $e){
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
        return back();

    }

    public function customer_report_display(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'customer_id' => 'required',
            'action' => 'required',
          
        ]);

        $action = $request->input('action');
        $customer_id = $request->input('customer_id');
        $x['today'] = date("d-m-Y");

        try{
            switch($action)
            {
                case 'customer_report':
                    $x['title']     = 'Customer Report';
                    $x['data']      = CustomerLedger::with('customer_list', 'plot_list', 'project_list')
                                    ->where('customer_id', $customer_id)->where('is_active', 1)->where('is_approve', 1)
                                    ->when($request->input('plot_id'), function ($query) use ($request) {
                                        return $query->where('plot_id', $request->input('plot_id'));
                                    })
                                    ->orderBy('plot_id')
                                    ->orderBy('id')
                                    ->get();

                    // dd( $x['data']);
                    return view('admin.reports.bookings.customer', $x);

                break;

                case 'booking_file':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',
                      
                    ]);

                    $plot_id = $request->input('plot_id');
                    $x['title']     = 'Payment Plan';
                    $x['data']      = $booking = Booking::with('customer', 'plot', 'project', 'bookingDetails')
                                        ->where('customer_id', $customer_id)
                                        ->where('plot_id', $plot_id)
                                        ->first();

                    // dd($x['data']);
                    return view('admin.reports.bookings.booking', $x);
                    

                break;

                case 'recovery_report':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',
                      
                    ]);

                    $today = Carbon::today()->toDateString();
                    

                    $plot_id = $request->input('plot_id');
                    $x['title']     = 'Customer Recovery Report';
                    $x['data']       = Booking::with(['customer', 'plot', 'project', 'bookingDetails' => function ($query) use ($today) {
                                            $query->whereDate('due_date', '<=', $today);
                                        }])
                                        ->where('customer_id', $customer_id)
                                        ->where('plot_id', $plot_id)
                                        ->first();

                    // dd($x['data']);
                    $customerLedgers = CustomerLedger::where('customer_id', $customer_id)->where('is_active' , 1)
                        ->when($request->input('plot_id'), function ($query) use ($request) {
                            return $query->where('plot_id', $request->input('plot_id'));
                        })
                        ->orderBy('plot_id')
                        ->orderBy('id')
                        ->get();

                    $sumAmountOut = $customerLedgers->sum('amount_out');
                    // dd($sumAmountOut);

                    $x['recovery']    = $sumAmountOut;
                    $x['total_recovery']    = $sumAmountOut;

                    return view('admin.reports.bookings.customer_recovery', $x);
                    

                break;

                case 'recovery_report_details':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',
                    ]);
                
                    $today = Carbon::today()->toDateString();
                    $plot_id = $request->input('plot_id');
                    
                    $x['title'] = 'Customer Payment Report';
                    $booking = Booking::with(['customer', 'plot', 'project', 'bookingDetails' => function ($query) use ($today) {
                                            $query->whereDate('due_date', '<=', $today);
                                        }])
                                        ->where('customer_id', $customer_id)
                                        ->where('plot_id', $plot_id)
                                        ->first();
                    $x['data'] = $booking;
                
                    $customerLedgers = CustomerLedger::where('customer_id', $customer_id)
                                        ->where('is_active', 1)
                                        ->where('transaction_type', "PPR")
                                        ->when($request->input('plot_id'), function ($query) use ($request) {
                                            return $query->where('plot_id', $request->input('plot_id'));
                                        })
                                        ->orderBy('plot_id')
                                        ->orderBy('id')
                                        ->get();
                    $bookingDetails = BookingDetail::where('booking_id', $booking->id)->whereDate('due_date', '<=', $today)->get();
                    
                    $customer_ledger_record = [];


                    // Combine data from $customerLedgers and $bookingDetails
                    

                    foreach ($bookingDetails as $detail) {
                        $customer_ledger_record[] = [
                            'date' => $detail->due_date, // Assuming `due_date` is the date field in the `BookingDetail` model
                            'details' => $detail->installment_details, // Provide a description for booking details
                            'amount_in' => $detail->amount, // Assuming `amount` is a column in the `BookingDetail` model
                            'amount_out' => 0, // Assuming there is no `amount_out` in `BookingDetail`
                        ];
                    }

                    foreach ($customerLedgers as $ledger) {
                        $customer_ledger_record[] = [
                            'date' => $ledger->date, // Assuming `date` is a column in the `CustomerLedger` model
                            'details' => 'Slip:'.$ledger->reference.' '.$ledger->description, // Assuming `details` is a column in the `CustomerLedger` model
                            'amount_in' => $ledger->amount_in, // Assuming `amount_in` is a column in the `CustomerLedger` model
                            'amount_out' => $ledger->amount_out, // Assuming `amount_out` is a column in the `CustomerLedger` model
                        ];
                    }

                    // Sort the combined array by date
                    usort($customer_ledger_record, function ($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });

                    
                    return view('admin.reports.bookings.customer_recovery_by_details', array_merge($x, ['customer_ledger_record' => $customer_ledger_record]));
                
                break;

                case 'customer_ledger':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',
                    ]);
                
                    $today = Carbon::today()->toDateString();
                    $plot_id = $request->input('plot_id');
                    
                    $x['title'] = 'Customer Payment Report';
                    $booking = Booking::with(['customer', 'plot', 'project', 'bookingDetails' => function ($query) use ($today) {
                                            $query->whereDate('due_date', '<=', $today);
                                        }])
                                        ->where('customer_id', $customer_id)
                                        ->where('plot_id', $plot_id)
                                        ->first();
                    $x['data'] = $booking;
                
                    $customerLedgers = CustomerLedger::where('customer_id', $customer_id)
                                        ->where('is_active', 1)
                                        ->where('transaction_type', "PPR")
                                        ->when($request->input('plot_id'), function ($query) use ($request) {
                                            return $query->where('plot_id', $request->input('plot_id'));
                                        })
                                        ->orderBy('plot_id')
                                        ->orderBy('id')
                                        ->get();
                    $bookingDetails = BookingDetail::where('booking_id', $booking->id)->get();
                    
                    $customer_ledger_record = [];


                    // Combine data from $customerLedgers and $bookingDetails
                    

                    foreach ($bookingDetails as $detail) {
                        $customer_ledger_record[] = [
                            'date' => $detail->due_date, // Assuming `due_date` is the date field in the `BookingDetail` model
                            'details' => $detail->installment_details, // Provide a description for booking details
                            'amount_in' => $detail->amount, // Assuming `amount` is a column in the `BookingDetail` model
                            'amount_out' => 0, // Assuming there is no `amount_out` in `BookingDetail`
                        ];
                    }

                    foreach ($customerLedgers as $ledger) {
                        $customer_ledger_record[] = [
                            'date' => $ledger->date, // Assuming `date` is a column in the `CustomerLedger` model
                            'details' => 'Slip:'.$ledger->reference.' '.$ledger->description, // Assuming `details` is a column in the `CustomerLedger` model
                            'amount_in' => $ledger->amount_in, // Assuming `amount_in` is a column in the `CustomerLedger` model
                            'amount_out' => $ledger->amount_out, // Assuming `amount_out` is a column in the `CustomerLedger` model
                        ];
                    }

                    // Sort the combined array by date
                    usort($customer_ledger_record, function ($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });

                    
                    return view('admin.reports.bookings.customer_recovery_by_details', array_merge($x, ['customer_ledger_record' => $customer_ledger_record]));
                
                break;
            }

        }catch(\Exception $e){
            return back()->withErrors(['msg' => $e->getMessage()]);
        }

    }

    public function fatch_voucher(Request $request)
    {
        $data_list = CustomerLedger::with('customer_list','plot_list')->where(['id' => $request->id])->first();



        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Project by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }


    public function booking_price_update(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'plot_size' => 'required|numeric',
            'new_rate' => 'required|numeric',
            'discount_price' => 'required|numeric',
            'total_new_value' => 'required|numeric',
        ]);

        try {
            // Find the booking by ID
            $booking = Booking::findOrFail($validatedData['booking_id']);

            // Update the booking details
            $booking->plot_rate = $validatedData['new_rate'];
            $booking->dicount_value = $validatedData['discount_price'];
            $booking->total_price = $validatedData['total_new_value'];

            // Save the updated booking
            $booking->save();

            // Return a success response in JSON format
            return response()->json([
                'status' => 'success',
                'message' => 'Booking price updated successfully!',
                'data' => $booking,
            ], 200);

        } catch (\Exception $e) {
            // Return an error response in JSON format
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while updating the booking price. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy_booking(Request $request)
    {
        $booking = Booking::findOrFail($request->id);
        
        // Get the plot linked to this booking
        $plot = Plot::find($booking->plot_id);

        if ($plot) {
            // Save current booking data to plot_history
            $history = json_decode($plot->plot_history, true) ?? []; // Get existing history or empty array
            $history[] = [
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'plot_id' => $booking->plot_id,
                'plot_size' => $booking->plot_size,
                'plot_rate' => $booking->plot_rate,
                'total_price' => $booking->total_price,
                'broker_id' => $booking->broker_id,
                'booking_date' => $booking->booking_date,
                'name' => $booking->customer->first_name . ' ' . $booking->customer->last_name,
                'father_name' => $booking->customer->father_name,
                'cnic' => $booking->customer->nic_number,
                'phone_number' => $booking->customer->phone_number,
                'deleted_at' => now(),
            ];

            // Save updated history back to plot
            $plot->update([
                'sold' => 0, // Reset sold status
                'plot_history' => json_encode($history),
            ]);
        }

        // Perform soft delete on booking
        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully.']);
    }





    

}
