<?php

namespace App\Http\Controllers;

use App\Models\DastiCash;
use Illuminate\Http\Request;

class DastiCashController extends Controller
{
    // Store new record
    public function store(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $validater = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);
        $validater['project_id'] = $selectedProjectId;
        DastiCash::create($validater);
        return redirect()->route('dashboard')->with('success', 'Record added successfully.');
    }

    // Show edit form
    public function edit($id)
    {
        $record = DastiCash::findOrFail($id);
        return view('dasticash.edit', compact('record'));
    }

    // Update record
    public function update(Request $request, $id)
    {
        $record = DastiCash::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $record->update($request->only('name', 'amount', 'description'));

        return redirect()->route('dashboard')->with('success', 'Record added successfully.');
    }

    // Delete record
    public function destroy($id)
    {
        $record = DastiCash::findOrFail($id);
        $record->delete();

        return redirect()->route('dashboard')->with('success', 'Record added successfully.');
    }
}
