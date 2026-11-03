@php
 $tree = fn(array $patterns) => collect($patterns)->contains(fn($p) => request()->routeIs($p));
 $leadApprovalPermissionExists = \Spatie\Permission\Models\Permission::where('name','assign lead')->exists();
 $canLeadApprovals = $leadApprovalPermissionExists ? auth()->user()->can('assign lead') : (auth()->user()->can('update lead') && auth()->user()->hasAnyRole(['admin','superadmin','super-admin']));
 $canFinanceApprovals = \App\Http\Controllers\Finance\VoucherController::canAccessFinanceApprovalsForUser(auth()->user());
@endphp
<li class="nav-item"><a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}"><i class="nav-icon fas fa-home"></i><p>Dashboard</p></a></li>

<li class="nav-header">MAIN MENU</li>
@canany(['read setting','read project','read user','read role','read permission','read landing page','read accounting'])
<li class="nav-item {{ $tree(['setting.*','project.*','area.*','accounting.*','user.*','role.*','permission.*','landing-page.*','filemanager'])?'menu-open':'' }}"><a href="#" class="nav-link"><i class="nav-icon fas fa-cogs"></i><p>Definitions & Settings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview">
 @can('read landing page')<li class="nav-item"><a href="{{ route('landing-page.admin.index') }}" class="nav-link {{ request()->routeIs('landing-page.*')?'active':'' }}"><i class="far fa-circle nav-icon"></i><p>Landing Page Manager</p></a></li>@endcan
 @can('read project')<li class="nav-item"><a href="{{ route('project.index') }}" class="nav-link {{ request()->routeIs('project.index')?'active':'' }}"><i class="far fa-circle nav-icon"></i><p>Projects</p></a></li>@endcan
 @can('read sector')<li class="nav-item"><a href="{{ route('project.zone.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Sectors</p></a></li>@endcan
 @can('read area')<li class="nav-item"><a href="{{ route('area.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Areas</p></a></li>@endcan
 @can('read user')<li class="nav-item"><a href="{{ route('accounting.head_index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Main Accounts</p></a></li><li class="nav-item"><a href="{{ route('accounting.subhead_index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Party Accounts</p></a></li><li class="nav-item"><a href="{{ route('accounting.category_index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Joint Accounts</p></a></li>@endcan
 @can('read accounting')<li class="nav-item"><a href="{{ route('accounting.Accountant') }}" class="nav-link {{ request()->routeIs('accounting.Accountant*')?'active':'' }}"><i class="far fa-circle nav-icon"></i><p>Accountant</p></a></li>@endcan
 @can('read setting')<li class="nav-item"><a href="{{ route('setting.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>System Settings</p></a></li>@endcan
 @can('filemanager')<li class="nav-item"><a href="{{ route('filemanager') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>File Manager</p></a></li>@endcan
</ul></li>
@endcanany

@canany(['read voucher','read jv','read slip','add slip'])
<li class="nav-item {{ $tree(['finance.voucher.*','journal.voucher.*','sales.voucher.*','commision.voucher.*','payment_schedule.*'])?'menu-open':'' }}"><a href="#" class="nav-link"><i class="nav-icon fas fa-edit"></i><p>Daily Entries<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview">
 @can('read voucher')<li class="nav-item"><a href="{{ route('finance.voucher.in') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Cash In</p></a></li><li class="nav-item"><a href="{{ route('finance.voucher.out') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Cash Out</p></a></li><li class="nav-item"><a href="{{ route('finance.voucher.draft') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Draft Vouchers</p></a></li>@endcan
 @can('read jv')<li class="nav-item"><a href="{{ route('journal.voucher.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Journal Vouchers</p></a></li><li class="nav-item"><a href="{{ route('sales.voucher.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Sales Vouchers</p></a></li><li class="nav-item"><a href="{{ route('commision.voucher.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Commission Vouchers</p></a></li>@endcan
 @canany(['read slip','add slip'])<li class="nav-item"><a href="{{ route('payment_schedule.cash') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Receive Payment</p></a></li><li class="nav-item"><a href="{{ route('payment_schedule.extra_charge') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Extra Charge</p></a></li>@endcanany
</ul></li>
@endcanany

@if(auth()->user()->canAny(['read user','read role','read permission','read voucher']) || $canLeadApprovals || $canFinanceApprovals)
<li class="nav-item {{ $tree(['user.*','role.*','permission.*','approvals.*'])?'menu-open':'' }}"><a href="#" class="nav-link"><i class="nav-icon fas fa-user-shield"></i><p>Administration<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview">
 @can('read user')<li class="nav-item"><a href="{{ route('user.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Users</p></a></li>@endcan @can('read role')<li class="nav-item"><a href="{{ route('role.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Roles</p></a></li>@endcan @can('read permission')<li class="nav-item"><a href="{{ route('permission.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Permissions</p></a></li>@endcan
 @if($canLeadApprovals)<li class="nav-item"><a href="{{ route('approvals.leads.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Lead Approvals</p></a></li>@endif @if($canFinanceApprovals)<li class="nav-item"><a href="{{ route('approvals.finance.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Finance Change Approvals</p></a></li>@endif
</ul></li>
@endif

@canany(['report party_report','report cashbook','report party_ledger','lead report','cheque report','view inventory'])
<li class="nav-item {{ $tree(['finance.reports.*','report.*','booking.*report*'])?'menu-open':'' }}"><a href="#" class="nav-link"><i class="nav-icon fas fa-chart-bar"></i><p>Reports<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview">
 @can('report party_ledger')<li class="nav-item"><a href="{{ route('finance.reports.details_index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Party Ledger</p></a></li>@endcan @can('report cashbook')<li class="nav-item"><a href="{{ route('finance.reports.show_ledger') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Cash Book</p></a></li>@endcan
 @can('report party_report')<li class="nav-item"><a href="{{ route('finance.reports.trial_balance') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Trial Balance</p></a></li><li class="nav-item"><a href="{{ route('finance.reports.show_ledger_party') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Party Wise</p></a></li>@endcan
 @can('report party_report')<li class="nav-item"><a href="{{ route('finance.reports.show_ledger_head', ['name'=>'Head Wise','type'=>'Head']) }}" class="nav-link {{ request()->routeIs('finance.reports.show_ledger_head') && request('type')==='Head'?'active':'' }}"><i class="far fa-circle nav-icon"></i><p>Head Wise</p></a></li><li class="nav-item"><a href="{{ route('finance.reports.show_ledger_head', ['name'=>'Account type Balance Ledger','type'=>'account']) }}" class="nav-link {{ request()->routeIs('finance.reports.show_ledger_head') && request('type')==='account'?'active':'' }}"><i class="far fa-circle nav-icon"></i><p>Account Type Balance</p></a></li>@endcan
 @can('lead report')<li class="nav-item"><a href="{{ route('report.lead.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>CRM Leads Report</p></a></li>@endcan @can('cheque report')<li class="nav-item"><a href="{{ route('report.check') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Cheque Report</p></a></li>@endcan
 @can('view inventory')<li class="nav-item"><a href="{{ route('booking.plot.inventory') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Plot Inventory</p></a></li>@endcan
 @can('read plot')<li class="nav-item"><a href="{{ route('booking.plot.recovery_list') }}" class="nav-link {{ request()->routeIs('booking.plot.recovery_list')?'active':'' }}"><i class="far fa-circle nav-icon"></i><p>Project Recovery</p></a></li>@endcan
</ul></li>
@endcanany

@can('read lead')<li class="nav-item {{ $tree(['crm.*','lead.*'])?'menu-open':'' }}"><a href="#" class="nav-link"><i class="nav-icon fas fa-hands-helping"></i><p>CRM<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="{{ route('crm.lead.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>All Leads</p></a></li><li class="nav-item"><a href="{{ route('lead.work') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Start Work</p></a></li></ul></li>@endcan

@canany(['read plot','view inventory'])<li class="nav-item {{ $tree(['project.plot.*','booking.plot.*','booking.customer.*'])?'menu-open':'' }}"><a href="#" class="nav-link"><i class="nav-icon fas fa-building"></i><p>Sales & Plot Management<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview">@can('read plot')<li class="nav-item"><a href="{{ route('project.plot.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Plots</p></a></li><li class="nav-item"><a href="{{ route('project.plot.hold_plot') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Hold Plots</p></a></li><li class="nav-item"><a href="{{ route('booking.plot.index') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Plot Sales</p></a></li><li class="nav-item"><a href="{{ route('booking.customer.report.form') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Customer Report</p></a></li>@endcan @can('view inventory')<li class="nav-item"><a href="{{ route('booking.plot.inventory') }}" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Inventory</p></a></li>@endcan</ul></li>@endcanany

@can('stock module')<li class="nav-item"><a href="{{ route('stocks.index') }}" class="nav-link {{ request()->routeIs('stocks.*')?'active':'' }}"><i class="nav-icon fas fa-boxes"></i><p>Store & Inventory</p></a></li>@endcan
@can('labour module')<li class="nav-item"><a href="{{ route('labours.index') }}" class="nav-link {{ request()->routeIs('labours.*','labour.*')?'active':'' }}"><i class="nav-icon fas fa-hard-hat"></i><p>Labour Management</p></a></li>@endcan
<li class="nav-item"><a href="{{ route('employee-tasks.index') }}" class="nav-link {{ request()->routeIs('employee-tasks.*')?'active':'' }}"><i class="nav-icon fas fa-tasks"></i><p>Employee Task Management</p></a></li>
@can('read attendance')<li class="nav-item"><a href="{{ route('dashboard') }}#employee-attendance-summary" class="nav-link"><i class="nav-icon fas fa-user-clock"></i><p>Employee Attendance</p></a></li>@endcan

<li class="nav-header"></li><li class="nav-item"><a href="#" class="nav-link bg-danger" data-toggle="modal" data-target="#modal-logout" data-backdrop="static" data-keyboard="false"><i class="fas fa-sign-out-alt nav-icon"></i><p>Sign Out</p></a></li>
