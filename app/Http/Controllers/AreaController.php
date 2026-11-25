<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Support\SafeMasterRecordDeletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $rows = Area::query()->orderBy('id')->get()->map(fn (Area $area): array => [
            'id' => (int) $area->id,
            'name' => (string) $area->name,
            'is_active' => (int) $area->is_active,
            'created_at' => optional($area->created_at)->format('Y-m-d H:i:s'),
        ])->values()->all();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => $rows]);
        }

        return view('admin.area.index', ['title' => 'Area List', 'areaRows' => $rows]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('areas', 'name')],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please correct the area fields.', 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $validated = $validator->validated();
            $area = Area::create([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'],
                'create_by' => Auth::id(),
            ]);
            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Area created successfully.', 'id' => $area->id], 201);
            }
            Alert::success('Notification', 'Area saved successfully.')->toToast();
        } catch (\Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Area could not be created. Please try again.'], 500);
            }
            Alert::error('Notification', 'Area could not be created.')->toToast();
        }

        return back();
    }

    public function show(Request $request)
    {
        $validated = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $area = Area::query()->findOrFail((int) $validated['id']);

        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Area loaded.',
            'data' => $area,
        ]);
    }

    public function update(Request $request)
    {
        // Existing PUT /admin/area route has no {area} parameter.
        $id = (int) $request->validate(['id' => ['required', 'integer', 'min:1']])['id'];
        $area = Area::query()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('areas', 'name')->ignore($area->id)],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please correct the area fields.', 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $area->update([
                'name' => $validator->validated()['name'],
                'is_active' => $validator->validated()['is_active'],
                // A later edit must not overwrite the original creator.
            ]);
            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Area updated successfully.', 'id' => $area->id]);
            }
            Alert::success('Notification', 'Area updated successfully.')->toToast();
        } catch (\Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Area could not be updated. Please try again.'], 500);
            }
            Alert::error('Notification', 'Area could not be updated.')->toToast();
        }

        return back();
    }

    public function destroy(Request $request)
    {
        $id = (int) $request->validate(['id' => ['required', 'integer', 'min:1']])['id'];

        try {
            DB::transaction(function () use ($id): void {
                $area = Area::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                SafeMasterRecordDeletion::assertUnreferenced('areas', $id, 'area_id');
                $area->delete();
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            $message = 'Area cannot be deleted: '.$exception->getMessage();
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }
            return back()->withErrors(['area' => $message]);
        } catch (\Throwable $exception) {
            report($exception);
            $message = 'Area cannot be deleted because related records or database constraints prevent it.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }
            return back()->withErrors(['area' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Unlinked area deleted successfully.']);
        }

        return redirect()->route('area.index')->with('success', 'Area deleted.');
    }
}
