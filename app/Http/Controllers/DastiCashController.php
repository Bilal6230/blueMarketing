<?php

namespace App\Http\Controllers;

use App\Models\Dasticash;
use Illuminate\Http\Request;

class DastiCashController extends Controller
{
    // Store new record
    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['project_id'] = getSelectedTown();

        $record = Dasticash::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Dasticash added successfully.',
            'data' => $record
        ]);
    }

    // Show edit form
    public function edit($id)
    {
        $record = Dasticash::findOrFail($id);
        return view('dasticash.edit', compact('record'));
    }

    // Update record
    public function update(Request $request, $id)
    {
        $record = Dasticash::findOrFail($id);

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $record->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Dasticash updated successfully.',
            'data' => $record
        ]);
    }

    // Delete record
    public function destroy($id)
    {
        $record = Dasticash::findOrFail($id);
        $record->delete();

        return redirect()->route('dashboard')->with('success', 'Record added successfully.');
    }
}
