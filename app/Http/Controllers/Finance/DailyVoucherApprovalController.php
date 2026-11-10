<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DailyVoucherApproval;
use App\Models\DailyVoucherApprovalPolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DailyVoucherApprovalController extends Controller
{
    private const TYPES = ['CR', 'CP', 'JV'];

    public function index()
    {
        $this->authorizeManager();
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0 && Schema::hasTable('daily_voucher_approval_policies'), 409, 'Run the Daily Entries approval migration first.');
        $policies = DailyVoucherApprovalPolicy::query()->where('project_id', $projectId)->get()->keyBy('voucher_type');
        $approvers = User::query()->where('project_id', $projectId)->where('status_id', 1)->orderBy('name')->get(['id', 'name'])
            ->filter(fn (User $user) => \App\Services\Finance\VoucherApproverAccess::eligibleForAssignment($user,$projectId))->values();
        $isSuperAdmin = \App\Services\Finance\VoucherApproverAccess::isSuperAdmin(auth()->user());
        $queueQuery = DailyVoucherApproval::query()->with(['assignedApprover:id,name', 'submitter:id,name', 'events'])
            ->where('project_id', $projectId)->where('status', 'pending');
        if (!$isSuperAdmin) $queueQuery->where('assigned_approver_id', (int) auth()->id());
        $queue = $queueQuery->latest()->paginate(25);
        $historyQuery = DailyVoucherApproval::query()->with(['assignedApprover:id,name', 'submitter:id,name', 'events'])
            ->where('project_id', $projectId)->whereIn('status', ['approved', 'rejected']);
        if (!$isSuperAdmin) $historyQuery->where('assigned_approver_id', (int) auth()->id());
        $history = $historyQuery->latest('decided_at')->limit(50)->get();
        return view('admin.finance.voucher.approvals', compact('policies', 'approvers', 'queue', 'history'));
    }

    public function savePolicy(Request $request)
    {
        $this->authorizeManager();
        $projectId = (int) getSelectedTown();
        $data = $request->validate([
            'voucher_type' => ['required', 'in:CR,CP,JV'],
            'approval_required' => ['nullable', 'boolean'],
            'approver_user_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $required = $request->boolean('approval_required');
        $approverId = $data['approver_user_id'] ?? null;
        if ($required) {
            $approver = $approverId ? User::query()->where('project_id', $projectId)->where('status_id', 1)->whereKey($approverId)->first() : null;
            abort_unless($approver && \App\Services\Finance\VoucherApproverAccess::eligibleForAssignment($approver,$projectId), 422, 'Select an active authorized voucher approver from this project.');
            abort_if((int) $approverId === (int) auth()->id(), 422, 'The submitter cannot approve their own voucher.');
        } else {
            $approverId = null;
        }
        DailyVoucherApprovalPolicy::query()->updateOrCreate(
            ['project_id' => $projectId, 'voucher_type' => $data['voucher_type']],
            ['approval_required' => $required, 'approver_user_id' => $approverId, 'updated_by' => auth()->id()]
        );
        return back()->with('success', 'Voucher approval policy saved.');
    }

    public function decide(Request $request, DailyVoucherApproval $approval, string $decision)
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        $this->authorizeManager();
        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'min:2', 'max:1000']]);
        abort_unless((int) $approval->project_id === (int) getSelectedTown(), 404);
        abort_unless(in_array($approval->voucher_type, self::TYPES, true), 403);
        try {
            app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class)->decide(
                $approval, (int) auth()->id(), $decision === 'approve' ? 'approved' : 'rejected', $data['reason'] ?? null
            );
        } catch (\DomainException $e) {
            abort(409, match ($e->getMessage()) {
                'DAILY_VOUCHER_APPROVAL_ALREADY_PROCESSED' => 'This approval request has already been processed.',
                'DAILY_VOUCHER_APPROVER_NOT_AUTHORIZED' => 'You are not the assigned approver for this voucher.',
                'DAILY_VOUCHER_SOURCE_NOT_FOUND' => 'The voucher source could not be found in this project.',
                'DAILY_VOUCHER_APPROVER_INACTIVE' => 'Approver permission or active project access is missing.',
                default => 'The voucher approval could not be completed safely.',
            });
        }
        $message = $decision === 'approve' ? 'Voucher approved and existing guarded posting activated.' : 'Voucher rejected; posting remains inactive.';
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message,
                'data' => ['id' => $approval->id, 'status' => $decision === 'approve' ? 'approved' : 'rejected']])
                ->header('Cache-Control', 'private, no-store');
        }
        return back()->with('success', $message);
    }

    private function authorizeManager(): void
    {
        abort_unless(\App\Services\Finance\VoucherApproverAccess::mayApprove(auth()->user()), 403);
    }
}
