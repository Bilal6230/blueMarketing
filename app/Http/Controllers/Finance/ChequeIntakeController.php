<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class ChequeIntakeController extends Controller
{
    private function project(): int
    {
        $project = (int) getSelectedTown();
        abort_unless($project > 0 && DB::table('projects')->where('id', $project)->exists(), 403);
        return $project;
    }

    private function ready(): void
    {
        abort_unless(Schema::hasTable('finance_cheque_intakes'), 409, 'Cheque intake migration must be reviewed and applied first.');
    }

    public function index()
    {
        $this->ready();
        $project = $this->project();
        $rows = DB::table('finance_cheque_intakes')->where('project_id', $project)->orderByDesc('id')->paginate(25);
        $customers = DB::table('leads')->where('project_id', $project)
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        return view('admin.finance.cheque.intake', compact('rows', 'customers'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->can('create voucher'), 403);
        $this->ready();
        $project = $this->project();
        $data = $request->validate([
            'cheque_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\\/_-]+$/D'],
            'cheque_date' => ['required', 'date_format:Y-m-d'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^[0-9]{1,13}(?:\\.[0-9]{1,2})?$/D', 'not_in:0,0.0,0.00'],
            'bank_name' => ['required', 'string', 'max:150'],
            'branch' => ['nullable', 'string', 'max:150'],
            'received_from' => ['required', 'string', 'max:150'],
            'customer_id' => ['required', 'integer', Rule::exists('leads', 'id')->where('project_id', $project)],
            'purpose' => ['required', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $number = strtoupper(trim($data['cheque_number']));
        $bank = trim($data['bank_name']);
        // The unique DB index also protects concurrent requests.
        $exists = DB::table('finance_cheque_intakes')->where('project_id', $project)
            ->where('bank_name', $bank)->where('cheque_number', $number)->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['cheque_number' => 'This bank and cheque number already exist for this project.']);
        }
        $path = null;
        try {
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('finance/cheque-intakes/'.$project, 'local');
            }
            DB::table('finance_cheque_intakes')->insert([
                'project_id' => $project,
                'customer_id' => (int) $data['customer_id'],
                'cheque_number' => $number,
                'cheque_date' => $data['cheque_date'],
                'received_date' => $data['received_date'],
                'amount' => $data['amount'],
                'bank_name' => $bank,
                'branch' => $data['branch'] ?? null,
                'received_from' => trim($data['received_from']),
                'purpose' => trim($data['purpose']),
                'remarks' => $data['remarks'] ?? null,
                'attachment_path' => $path,
                'status' => 'received',
                'created_by' => (int) $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
        // An intake is NOT a customer-ledger source and MUST NOT post to ledgers.
        return redirect()->route('finance.cheques.index')->with('success', 'Cheque received. No bank or accounting voucher has been posted.');
    }
}
