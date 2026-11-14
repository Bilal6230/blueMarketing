{{-- Canonical finance directory header actions. Styling comes from finance-voucher-system.css.
     Extra, module-specific actions are passed in the default slot. --}}
@props(['approvals' => false, 'dashboard' => true])
<div {{ $attributes->class(['bm-header-actions', 'd-flex', 'align-items-center', 'flex-wrap']) }}>
    @if($approvals && \App\Http\Controllers\Finance\VoucherController::canAccessFinanceApprovalsForUser(auth()->user()))
        <a class="btn btn-outline-primary btn-sm bm-approval-link" href="{{ route('finance.daily-vouchers.approvals.index') }}">Approvals</a>
    @endif
    @if($dashboard)
        <a class="btn btn-outline-secondary btn-sm bm-dashboard-link" href="{{ route('dashboard') }}">Dashboard</a>
    @endif
    {{ $slot }}
</div>
