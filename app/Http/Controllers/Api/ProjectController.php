<?php
namespace App\Http\Controllers\Api;

use App\Models\Booking;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\CustomerLedger;
use Illuminate\Support\Facades\DB;
use App\Models\ProjectHeadSubhead;




class ProjectController extends Controller
{
    public function bookingDetails(Request $request)
    {
        $request->validate([
            'project_id' => 'required',
            'searchvalue' => 'required|string',
        ]);

        $today = Carbon::today()->toDateString();

        $bookings = Booking::with(['customer', 'plot', 'project'])
            ->where('project_id', $request->project_id)
            ->whereHas('plot', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->searchvalue . '%');
            })
            ->get()
            ->map(function ($booking) use ($today, $request) {
                // Calculate due amount from related bookingDetails
                $dueAmount = $booking->bookingDetails()
                    ->whereDate('due_date', '<=', $today)
                    ->sum('amount');

                // Determine plot type
                $type = $booking->plot_type == 2 ? 'commercial' : 'residential';

                // Extract only required fields from customer
                $customer = optional($booking->customer);
                $project = optional($booking->project);
                $plot = optional($booking->plot);
                $filteredCustomer = [
                    'first_name'     => $customer->first_name ?? "",
                    'last_name'      => $customer->last_name ?? "",
                    'father_name'    => $customer->father_name ?? "",
                    'nic_number'     => $customer->nic_number ?? "",
                    'phone_number'   => $customer->phone_number ?? "",
                    'mobile_number'  => $customer->mobile_number ?? "",
                    'home_address'   => $customer->home_address ?? "",
                    'office_address' => $customer->office_address ?? "",
                ];

                $projectData = [
                    'project'     => $project->project,
                    'address'      => $project->address,
                    'logo'    => $project->logo,
             
                ];

                $plotData = [
                    'name'   => $plot->name,
                    'size'   => $plot->size,
                    'unit'   => $plot->unit ?? "",
                    'is_corner'   => $plot->is_corner,
                    'plot_history'   => $plot->plot_history,
                    'sold'   => $plot->sold,
             
                ];

                $bookingData = [
                    'plot_size'         => $booking->plot_size,
                    'plot_rate'         => $booking->plot_rate,
                    'unit'              => $booking->unit ?? '2',
                    'is_corner'         => $booking->is_corner,
                    'carner_price'      => $booking->carner_price,
                    'park_facing'       => $booking->park_facing,
                    'total_price'       => $booking->total_price,
                    'dicount_value'     => $booking->dicount_value,
                    'sold'              => $booking->sold,
                    'booking_date'      => $booking->booking_date,
                    'status'            => $booking->status,

             
                ];

                $customerLedgerSum = CustomerLedger::where('customer_id', $booking->customer_id)
                    ->where('is_active', 1)
                    ->when($booking->plot_id, function ($query) use ($booking) {
                        return $query->where('plot_id', $booking->plot_id);
                    })
                    ->sum('amount_out');

                return [
                    'booking_id'   => $booking->id,
                    'plot_id'      => $booking->plot_id,
                    'project_id'   => $booking->project_id,
                    'broker_id'    => $booking->broker_id,
                    'customer_id'  => $booking->customer_id,
                    'profile_image' => $booking->profile_image ?? 'no_photo.jpg',
                    'dueAmount'   => $dueAmount,
                    'ledger_sum'   => $customerLedgerSum, // <-- 🔥 New field here
                    'type'         => $type,
                    'booking'      => $bookingData,
                    'project'      => $projectData,
                    'plot'         => $plotData,
                    'customer'     => $filteredCustomer,
                ];
            });
            

        return response()->json([
            'message' => 'Booking details retrieved successfully.',
            'data'    => $bookings,
        ]);
    }


  

    public function bookingList(Request $request)
    {
        $today = Carbon::today()->toDateString();

        // Start the query builder
        $query = Booking::with([
            'customer',
            'plot',
            'project',
            // 'bookingDetails' => function ($query) use ($today) {
            //     $query->whereDate('due_date', '<=', $today);
            // }
        ]);

        // Optional filters based on request parameters
        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->has('plot_id')) {
            $query->where('plot_id', $request->input('plot_id'));
        }

        if ($request->has('plot_type')) {
            $query->where('plot_type', $request->input('plot_type'));
        }

        if ($request->has('is_corner')) {
            $query->where('is_corner', $request->input('is_corner'));
        }

        if ($request->has('is_park')) {
            $query->where('is_park', $request->input('is_park'));
        }

        if ($request->has('broker_id')) {
            $query->where('broker_id', $request->input('broker_id'));
        }

        // Filter by booking date range (date_from and date_to)
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = Carbon::parse($request->input('date_from'))->startOfDay();
            $dateTo = Carbon::parse($request->input('date_to'))->endOfDay();

            // Include records where booking_date is between date_from and date_to, or matches either date_from or date_to
            $query->where(function($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('booking_date', [$dateFrom, $dateTo])
                    ->orWhere('booking_date', $dateFrom)
                    ->orWhere('booking_date', $dateTo);
            });
        }

        // Fetch the filtered data
        $data = $query->get();

        // Add sum_received and sum_due_amount for each booking
        $data = $data->map(function ($booking) {
            $booking->received = getSumRecovery($booking->plot_id, 'amount_out'); // existing helper
            $booking->dueAmount = getSumDueAmount($booking->id); // existing helper
            return $booking;
        });

        // Calculate the sum of Due Amounts and Received
        $sumDueAmount = $data->sum(function ($booking) {
            return getSumDueAmount($booking->id); // Your existing helper
        });

        $sumReceived = $data->sum(function ($booking) {
            return getSumRecovery($booking->plot_id, 'amount_out'); // Your existing helper
        });

        $totalSaleAmount = $data->sum('total_price'); 

        // Retrieve unique customers associated with the given project_id
        $customers = Booking::with('customer')
        ->where('project_id', $request->input('project_id')) // Filter by project_id
        ->get()
        ->pluck('customer') // Get only the customer relationships
        ->filter() // Remove nulls (in case a booking has no linked customer)
        ->unique('id') // Ensure each customer appears only once
        ->map(function ($customer) {
            return [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'relate' => $customer->relate,
                'father_name' => $customer->father_name,
                'phone_number' => $customer->phone_number,
            ];
        })
        ->values();

        $brokers = ProjectHeadSubhead::where('head_accounting_id', 6)
        ->join('subhead_accountings', 'project_head_subheads.subhead_accounting_id', '=', 'subhead_accountings.id')
        ->get();
        

        return response()->json([
            'message' => 'Recovery list retrieved successfully.',
            'data' => $data,
            'totalSale' => $totalSaleAmount,
            'sum_due_amount' => $sumDueAmount,
            'sum_received' => $sumReceived,
            'brokersList' => $brokers,
            'customersList' => $customers,
        ]);
    }

    public function bookingByid(Request $request)
    {
        $request->validate([
            'project_id' => 'required',
            'booking_id' => 'required',
        ]);
        $today = Carbon::today()->toDateString();

        // Start the query builder
        $query = Booking::with([
            'customer',
            'plot',
            'project',
            'bookingDetails' => function ($query) use ($today) {
                $query->whereDate('due_date', '<=', $today);
            }
        ]);

        // Optional filters based on request parameters
        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('booking_id')) {
            $query->where('id', $request->input('booking_id'));
        }

        // Filter by booking date range (date_from and date_to)
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = Carbon::parse($request->input('date_from'))->startOfDay();
            $dateTo = Carbon::parse($request->input('date_to'))->endOfDay();

            // Include records where booking_date is between date_from and date_to, or matches either date_from or date_to
            $query->where(function($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('booking_date', [$dateFrom, $dateTo])
                    ->orWhere('booking_date', $dateFrom)
                    ->orWhere('booking_date', $dateTo);
            });
        }

        // Fetch the filtered data
        $data = $query->get();

        

        // Calculate the sum of Due Amounts and Received
        $sumDueAmount = $data->sum(function ($booking) {
            return getSumDueAmount($booking->id); // Your existing helper
        });

        $sumReceived = $data->sum(function ($booking) {
            return getSumRecovery($booking->plot_id, 'amount_out'); // Your existing helper
        });

        $totalSaleAmount = $data->sum('total_price'); 

        return response()->json([
            'message' => 'Recovery list retrieved successfully.',
            'data' => $data,
            'totalSale' => $totalSaleAmount,
            'sum_due_amount' => $sumDueAmount,
            'sum_received' => $sumReceived,
        ]);
    }


}
