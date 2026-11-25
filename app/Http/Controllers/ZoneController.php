<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $rows = Zone::query()->orderBy('id')->get()->map(fn (Zone $zone): array => [
            'id' => (int) $zone->id,
            'zone_name' => (string) $zone->zone_name,
            'is_active' => (int) $zone->is_active,
            'created_at' => optional($zone->created_at)->format('Y-m-d H:i:s'),
        ])->values()->all();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => $rows]);
        }

        return view('admin.zone.index', ['title' => 'Sectors List', 'sectorRows' => $rows]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_name' => ['required', 'string', 'max:255', Rule::unique('zones', 'zone_name')],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please correct the sector fields.', 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $zone = Zone::create([
                'zone_name' => $validator->validated()['zone_name'],
                'is_active' => $validator->validated()['is_active'],
                'create_by' => Auth::id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Sector created successfully.', 'id' => $zone->id], 201);
            }
            Alert::success('Notification', 'Sector saved successfully.')->toToast();
        } catch (\Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sector could not be created. Please try again.'], 500);
            }
            Alert::error('Notification', 'Sector could not be created.')->toToast();
        }

        return back();
    }

    public function show(Request $request)
    {
        $validated = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $zone = Zone::query()->findOrFail((int) $validated['id']);

        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Sector loaded.',
            'data' => $zone,
        ]);
    }

    public function update(Request $request)
    {
        // PUT /admin/zone has no route-model parameter. Resolve the submitted
        // ID rather than mutating an unbound Zone instance.
        $validatedId = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $zone = Zone::query()->findOrFail((int) $validatedId['id']);

        $validator = Validator::make($request->all(), [
            'zone_name' => ['required', 'string', 'max:255', Rule::unique('zones', 'zone_name')->ignore($zone->id)],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please correct the sector fields.', 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $zone->update([
                'zone_name' => $validator->validated()['zone_name'],
                'is_active' => $validator->validated()['is_active'],
                // Preserve the original creator; an edit is not creation.
            ]);

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Sector updated successfully.', 'id' => $zone->id]);
            }
            Alert::success('Notification', 'Sector updated successfully.')->toToast();
        } catch (\Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sector could not be updated. Please try again.'], 500);
            }
            Alert::error('Notification', 'Sector could not be updated.')->toToast();
        }

        return back();
    }

    public function destroy(Zone $zone)
    {
        // Historical screen sent "delete sector" to user.destroy, even though
        // sector deletion has no implemented lifecycle. Fail closed.
        abort(409, 'Sector deletion is not available.');
    }
}
