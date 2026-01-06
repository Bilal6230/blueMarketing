<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index()
    {
        $selectedProjectId = getSelectedTown();
        $x['title'] = 'Stock';
        $x['stocks'] = Stock::where('project_id', $selectedProjectId)->get();
        return view('admin.stock.index', $x);
    }

    public function create()
    {
        return view('stocks.create');
    }

    public function store(Request $request)
    {
        $selectedProjectId = getSelectedTown();

        $validated = $request->validate([
            'stock_name' => 'required|string',
            'total_remaining' => 'required|integer',
            'quantity' => 'required|integer',
            'type' => 'required|in:purchase,sale',
            'total_used' => 'required|integer',
        ]);

        // Merge project_id into validated data
        $validated['project_id'] = $selectedProjectId;

        Stock::create($validated);

        return redirect()->route('stocks.index')->with('success', 'Stock added successfully.');
    }


    public function edit(Stock $stock)
    {
        return view('stocks.edit', compact('stock'));
    }

    public function update(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'stock_name' => 'required|string',
            'total_remaining' => 'required|integer',
            'quantity' => 'required|integer',
            'type' => 'required|in:purchase,sale',
            'total_used' => 'required|integer',
        ]);

        $stock->update($validated);
        return redirect()->route('stocks.index')->with('success', 'Stock updated successfully.');
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();
        return redirect()->route('stocks.index')->with('success', 'Stock deleted successfully.');
    }
}
