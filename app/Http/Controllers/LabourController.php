<?php

namespace App\Http\Controllers;

use App\Models\Labour;
use Illuminate\Http\Request;

class LabourController extends Controller
{
    public function index()
    {
        $selectedProjectId = getSelectedTown();
        $x['title'] = 'Labour';
        $x['labours'] = Labour::where('project_id', $selectedProjectId)->get();
        return view('admin.labours.index', $x);
    }

    public function create()
    {
        return view('labours.create');
    }

    public function store(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $validated =$request->validate([
            'name' => 'required|string|max:255',
            'cnic' => 'required|string|max:20|unique:labours,cnic',
            'phone' => 'nullable|string|max:20',
            'daily_wage' => 'required|numeric',
            'join_date' => 'required|date',
            'status' => 'required|in:active,inactive',
        ]);
        $validated['project_id'] = $selectedProjectId;

        Labour::create($validated);

        return redirect()->route('labours.index')->with('success', 'Labour created successfully.');
    }



    public function update(Request $request, Labour $labour)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cnic' => 'required|string|max:20|unique:labours,cnic,' . $labour->id,
            'phone' => 'nullable|string|max:20',
            'daily_wage' => 'required|numeric',
            'join_date' => 'required|date',
            'status' => 'required|in:active,inactive',
        ]);

        $labour->update($request->all());

        return redirect()->route('labours.index')->with('success', 'Labour updated successfully.');
    }
    public function updateStatus($id)
    {
        $labour = Labour::findOrFail($id);
        $labour->status = $labour->status === 'active' ? 'inactive' : 'active';
        $labour->save();

        return redirect()->route('labours.index')->with('success', 'Status updated successfully!');
    }


    public function destroy(Labour $labour)
    {
        $labour->delete();

        return redirect()->route('labours.index')->with('success', 'Labour deleted successfully.');
    }
}
