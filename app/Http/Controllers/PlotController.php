<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\HoldPlot;
use App\Models\Plot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;


use Spatie\Permission\Models\Role;

class PlotController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $x['title']     = 'Plots List';
        $x['data']      = Plot::where(['project_id' => getSelectedTown()])->get();
        $x['role']      = Role::get();
        // dd($x['data']);
        return view('admin.plots.index', $x);
    }
    public function holdPlots()
    {
        $x['title']     = "Hold's Plots List";
        $x['data']      = Plot::with('holdPlots')->whereHas('holdPlots')->where(['project_id' => getSelectedTown()])->get();
        $x['role']      = Role::get();
        // dd($x['data']);
        return view('admin.plots.hold_plots', $x);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $amount = str_replace(',', '', (string) $request->amount);
        $request->merge(['amount' => $amount]);

        $validator = Validator::make($request->all(), [
            'name'      => [
                                'required',
                                'string',
                                'max:255',
                                Rule::unique('plots')->where(function ($query) use ($request) {
                                    return $query->where('project_id', $request->project_id);
                                }),
                            ],
            'type'      => ['required', 'string',  'max:255'],
            'size'      => ['required', 'numeric',  'max:255'],
            'unit'      => ['required', 'string',  'max:255'],
            'amount'    => ['required', 'numeric', 'min:0'],
            'is_corner' => ['required'],
            'project_id' => ['required'],
            'road_id'   => ['required'],
            'facing_id' => ['required'],
            'is_active' => ['required'],

        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        try {
            $data = Plot::create([
                'name'          => $request->name,
                'type'         => $request->type,
                'size'         => $request->size,
                'unit'         => $request->unit,
                'amount'       => $amount,
                'is_corner'         => $request->is_corner,
                'project_id'         => $request->project_id,
                'road_id'         => $request->road_id,
                'facing_id'         => $request->facing_id,
                'description'         => $request->description,
                'is_active'     => $request->is_active,
                'create_by'     => Auth::user()->id

            ]);
            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function hold(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plot_id'      => ['required'],
            'reason'      => ['required', 'string'],

        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        try {
            $data = HoldPlot::create([
                'plot_id'         => $request->plot_id,
                'reason'     => $request->reason,
                'user_id'     => Auth::user()->id
            ]);
            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Plot  $plot
     * @return \Illuminate\Http\Response
     */
    public function edit(Plot $plot)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Plot  $plot
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Plot $plot)
    {
        $amount = str_replace(',', '', (string) $request->amount);
        $request->merge(['amount' => $amount]);

        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', 'string', 'max:255'],
            'size'      => ['required', 'numeric'],
            'unit'      => ['required', 'string'],
            'amount'    => ['required', 'numeric', 'min:0'],
            'is_corner' => ['required', 'boolean'],
            'project_id' => ['required', 'integer'],
            'road_id'   => ['required', 'integer'],
            'facing_id' => ['required', 'integer'],
            'description' => ['nullable', 'string'], // Adjust if this should be required
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        // Find the plot by ID
        $plot = Plot::find($request->id);
        if (!$plot) {
            // Handle the case where the plot doesn't exist
            Alert::error('Error', 'Plot not found!')->toToast()->toHtml();
            return back();
        }

        try {
            // Update the existing plot data
            $plot->update([
                'name'          => $request->name,
                'type'          => $request->type,
                'size'          => $request->size,
                'unit'          => $request->unit,
                'amount'        => $amount,
                'is_corner'     => (bool) $request->is_corner,
                'project_id'    => $request->project_id,
                'road_id'       => $request->road_id,
                'facing_id'     => $request->facing_id,
                'description'   => $request->description,
                'is_active'     => (bool) $request->is_active,
            ]);

            Alert::success('Notification', 'Data <b>' . $plot->name . '</b> updated successfully!')->toToast()->toHtml();
        } catch (\Throwable $th) {
            return back()->withErrors('An error occurred while updating the plot: ' . $th->getMessage())->withInput();
        }

        return back();
    }

    public function update_plot_number(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'plot_id' => 'required|integer|exists:plots,id', // Ensure plot_id exists in the database
            'plot_number' => 'required|string|max:255',     // Validate the plot number field
            'plot_size_update' => 'required|string|max:255',     // Validate the plot number field
        ]);

        try {
            // Find the plot by its ID
            $plot = Plot::find($validatedData['plot_id']);

            if ($plot) {
                // Update the plot number (name)
                $plot->name = $validatedData['plot_number'];
                $plot->size = $validatedData['plot_size_update'];
                $plot->save(); // Save the changes

                // Update plot_size in all related bookings
                Booking::where('plot_id', $plot->id)
                    ->where('cancel_status', '0')
                    ->update(['plot_size' => $validatedData['plot_size_update']]);

                // Return a successful response
                return response()->json([
                    'success' => true,
                    'message' => 'Plot number and size updated successfully!',
                    'plot' => $plot, // Return the updated plot details if needed
                ], 200);
            }

            // If no plot is found (unlikely due to validation)
            return response()->json([
                'success' => false,
                'message' => 'Plot not found!',
            ], 404);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the plot number.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        // Find the plot by ID where sold is not 1
        $plot = Plot::where('id', $request->id)->where('sold', '!=', 1)->first();

        // Check if the plot exists and is not sold
        if (!$plot) {
            Alert::error('Error', 'Plot not found or it is already sold!')->toToast()->toHtml();
            return back();
        }

        DB::beginTransaction();

        try {
            // Delete the plot
            $plot->delete();

            DB::commit();
            Alert::success('Notification', 'Plot <b>' . $plot->name . '</b> deleted successfully!')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollBack();
            Alert::error('Error', 'An error occurred while deleting the plot: ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }
    public function unHold(Request $request)
    {
        // Find the plot by ID where sold is not 1
        $plot = HoldPlot::where('plot_id', $request->id)->first();

        // Check if the plot exists and is not sold
        if (!$plot) {
            Alert::error('Error', 'Plot not found or it is already sold!')->toToast()->toHtml();
            return back();
        }

        DB::beginTransaction();

        try {
            // Delete the plot
            $plot->delete();

            DB::commit();
            Alert::success('Notification', 'Plot <b>' . $plot->name . '</b> deleted successfully!')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollBack();
            Alert::error('Error', 'An error occurred while deleting the plot: ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }

    public function inventory(Request $request)
    {
        $projectId = getSelectedTown();

        // Fetch all active plots for the selected project
        $plots = Plot::whereDoesntHave('holdPlots')->where([
            'project_id' => $projectId,
            'is_active' => 1
        ])->get();
        $totalPlots = Plot::where([
            'project_id' => $projectId,
            'is_active' => 1
        ])->get();
        $holdPlots = Plot::whereHas('holdPlots')->where([
            'project_id' => $projectId,
            'is_active' => 1
        ])->get();

        // Categorize plots
        $soldPlots = $plots->where('sold', 1);
        $unsoldPlots = $plots->where('sold', 0);
        $holdPlots = $holdPlots->where('sold', 0);

        // Categorize by type
        $soldResidential = $soldPlots->where('type', 1);
        $unsoldResidential = $unsoldPlots->where('type', 1);
        $holdResidential = $holdPlots->where('type', 1);
        $soldShops = $soldPlots->where('type', 2);
        $unsoldShops = $unsoldPlots->where('type', 2);
        $holdShops = $holdPlots->where('type', 2);

        // Calculate total plot size
        $totalSize = $totalPlots->sum('size');
        $soldSize = $soldPlots->sum('size');
        $unsoldSize = $unsoldPlots->sum('size');
        $holdSize = $holdPlots->sum('size');
        $totalInventoryValue = $totalPlots->sum('amount');
        $soldInventoryValue = $soldPlots->sum('amount');
        $unsoldInventoryValue = $unsoldPlots->sum('amount');
        $holdInventoryValue = $holdPlots->sum('amount');

        // Chart Data: Total Plots

        $chartData = [
            'total'  => $totalPlots->count(),
            'sold'   => $soldPlots->count(),
            'unsold' => $unsoldPlots->count(),
            'hold' => $holdPlots->count(),
        ];

        // Chart Data: Plot Size Distribution
        $sizeChartData = [
            'totalSize'  => $totalSize ?: 1, // Prevent division by zero
            'soldSize'   => $soldSize,
            'unsoldSize' => $unsoldSize,
            'holdSize' => $holdSize,
        ];

        // Chart Data: Residential (Sold vs. Unsold)
        $residentialChartData = [
            'sold'   => $soldResidential->count(),
            'unsold' => $unsoldResidential->count(),
            'hold' => $holdResidential->count(),
        ];
        // Chart Data: Shops (Sold vs. Unsold)
        $shopsChartData = [
            'sold'   => $soldShops->count(),
            'unsold' => $unsoldShops->count(),
            'hold' => $holdShops->count(),
        ];

        return view('admin.plots.inventory', compact(
            'soldPlots',
            'unsoldPlots',
            'holdPlots',
            'soldResidential',
            'unsoldResidential',
            'holdResidential',
            'soldShops',
            'unsoldShops',
            'holdShops',
            'chartData',
            'sizeChartData',
            'residentialChartData',
            'shopsChartData',
            'totalInventoryValue',
            'soldInventoryValue',
            'unsoldInventoryValue',
            'holdInventoryValue'
        ))->with('title', 'Plots Inventory');
    }

    public function showPlotHistory($id)
    {
        $plot = Plot::findOrFail($id);

        // Decode JSON history
        $plotHistory = json_decode($plot->plot_history, true) ?? [];

        return view('admin.plots.history', compact('plot', 'plotHistory'))->with('title', 'Plot Booking History');
    }
}
