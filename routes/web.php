<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DastiCashController;
use App\Http\Controllers\EmployeeTaskController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\LabourController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function () {
    return view('welcome');
})->name('index');
Auth::routes([
    'register' => false,
    'reset' => false,
    'confirm' => false
]);



Route::middleware(['auth'])->get('/home', [DashboardController::class, 'index'])->name('home');

Route::prefix('admin')->middleware(['auth', 'check.user.status', 'finance.action', \App\Http\Middleware\EnforceVoucherAvailability::class, \App\Http\Middleware\RecordActivityAudit::class])->group(function () {
    // Every authenticated user may edit only their own basic profile. No
    // user-management permission, role assignment or project change involved.
    Route::get('my-profile', [\App\Http\Controllers\MyProfileController::class, 'edit'])->name('my-profile.edit');
    Route::post('my-profile', [\App\Http\Controllers\MyProfileController::class, 'update'])->middleware('throttle:12,1')->name('my-profile.update');
    Route::get('activity-audit', [\App\Http\Controllers\ActivityAuditController::class, 'index'])
        ->middleware('permission:read activity audit')->name('activity-audit.index');
    Route::get('activity-audit/{audit}', [\App\Http\Controllers\ActivityAuditController::class, 'show'])
        ->whereNumber('audit')->middleware('permission:read activity audit')->name('activity-audit.show');
    Route::get('attendance', [AttendanceController::class, 'index'])->middleware('permission:read attendance')->name('attendance.index');
    Route::post('attendance/device', [AttendanceController::class, 'saveDevice'])->middleware('permission:update setting')->name('attendance.device.save');
    Route::get('attendance/device/{device}/settings', [AttendanceController::class, 'deviceSettings'])->whereNumber('device')->middleware('permission:update setting')->name('attendance.device.settings');
    Route::delete('attendance/device/{device}', [AttendanceController::class, 'deleteDevice'])->whereNumber('device')->middleware('permission:update setting')->name('attendance.device.delete');
    Route::post('attendance/mapping', [AttendanceController::class, 'saveMapping'])->middleware('permission:update setting')->name('attendance.mapping.save');
    Route::delete('attendance/mapping/{mapping}', [AttendanceController::class, 'deleteMapping'])->whereNumber('mapping')->middleware('permission:update setting')->name('attendance.mapping.delete');
    Route::get('employee-tasks', [EmployeeTaskController::class, 'index'])->middleware('permission:read employee task')->name('employee-tasks.index');
    Route::post('employee-tasks', [EmployeeTaskController::class, 'store'])->middleware('permission:create employee task')->name('employee-tasks.store');
    Route::put('employee-tasks/{employeeTask}', [EmployeeTaskController::class, 'update'])->middleware('permission:update employee task')->name('employee-tasks.update');
    Route::patch('employee-tasks/{employeeTask}/status', [EmployeeTaskController::class, 'status'])->middleware('permission:read employee task|update employee task')->name('employee-tasks.status');
    Route::patch('employee-task-notifications/{notification}', [EmployeeTaskController::class, 'readNotification'])->middleware('permission:read employee task')->name('employee-tasks.notifications.read');
    Route::delete('employee-tasks/{employeeTask}', [EmployeeTaskController::class, 'destroy'])->middleware('permission:delete employee task')->name('employee-tasks.destroy');
    // Public website editor: same authentication and settings permission as the existing admin.
    Route::get('landing-page', [\App\Http\Controllers\LandingPageAdminController::class, 'index'])
        ->middleware('permission:read landing page')->name('landing-page.admin.index');
    Route::put('landing-page', [\App\Http\Controllers\LandingPageAdminController::class, 'update'])
        ->middleware(['permission:update landing page', 'throttle:30,1'])->name('landing-page.admin.update');
    Route::post('landing-page/contact', [\App\Http\Controllers\LandingPageAdminController::class, 'updateContact'])
        ->middleware(['permission:update landing page', 'throttle:30,1'])->name('landing-page.admin.contact');
    Route::get('landing-page/preview', [\App\Http\Controllers\LandingPageAdminController::class, 'preview'])
        ->middleware('permission:read landing page')->name('landing-page.admin.preview');
    Route::post('landing-page/publish', [\App\Http\Controllers\LandingPageAdminController::class, 'publish'])
        ->middleware(['permission:update landing page', 'throttle:10,1'])->name('landing-page.admin.publish');
    Route::post('landing-page/upload-image', [\App\Http\Controllers\LandingPageAdminController::class, 'uploadImage'])
        ->middleware(['permission:update landing page', 'throttle:10,1'])->name('landing-page.admin.upload-image');
    Route::get('/', [DashboardController::class, 'index'])->name('admin');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('punch', [DashboardController::class, 'punch']);
    Route::get('punch', [DashboardController::class, 'index']);
    Route::get('dashboard/print-leads-by-users', [DashboardController::class, 'printLeadsByUsersReport'])->name('dashboard.leads_by_users_report');
    Route::get('dashboard/crm-snapshot', [DashboardController::class, 'crmSnapshot'])->name('dashboard.crm_snapshot');

    Route::get('crm/today-lead-work-report', [DashboardController::class, 'todayLeadWorkReport'])->name('crm.todayLeadWorkReport');




    Route::controller(UserController::class)->group(function () {
        Route::get('user', 'index')->middleware(['permission:read user'])->name('user.index');
        Route::post('/user/status-toggle', 'toggleStatus')->middleware('permission:update user')->name('user.status.toggle');
        Route::post('user', 'store')->middleware(['permission:create user'])->name('user.store');
        Route::post('user/show', 'show')->middleware(['permission:read user'])->name('user.show');
        Route::put('user', 'update')->middleware(['permission:update user'])->name('user.update');
        Route::delete('user', 'destroy')->middleware(['permission:delete user'])->name('user.destroy');
    });

    Route::get('employee-definitions', [App\Http\Controllers\EmployeeDefinitionController::class, 'index'])
        ->middleware(['permission:read user'])->name('employee-definitions.index');
    Route::post('employee-definitions', [App\Http\Controllers\EmployeeDefinitionController::class, 'storeDefinition'])
        ->middleware(['permission:create user'])->name('employee-definitions.store');
    Route::put('employee-definitions/{definitionId}', [App\Http\Controllers\EmployeeDefinitionController::class, 'updateDefinition'])
        ->middleware(['permission:update user'])->name('employee-definitions.update');
    Route::post('employee-profiles', [App\Http\Controllers\EmployeeDefinitionController::class, 'saveProfile'])
        ->middleware(['permission:create user|update user'])->name('employee-profiles.store');
    Route::get('employee-attendance', [App\Http\Controllers\EmployeeAttendanceSnapshotController::class, 'index'])
        ->middleware('permission:read attendance')->name('employee-attendance.index');
    Route::post('employee-attendance/prepare', [App\Http\Controllers\EmployeeAttendanceSnapshotController::class, 'prepare'])
        ->middleware('permission:create attendance')->name('employee-attendance.prepare');
    Route::patch('employee-attendance/{recordId}', [App\Http\Controllers\EmployeeAttendanceSnapshotController::class, 'review'])
        ->middleware('permission:update attendance')->name('employee-attendance.review');
    Route::get('employee-payroll/preview', [App\Http\Controllers\EmployeePayrollPreviewController::class, 'index'])
        ->middleware('permission:read payroll')->name('employee-payroll.preview');
    Route::post('employee-payroll/snapshots', [App\Http\Controllers\EmployeePayrollPreviewController::class, 'snapshot'])
        ->middleware('permission:create payroll')->name('employee-payroll.snapshot');
    Route::post('employee-payroll/snapshots/{runId}/approve', [App\Http\Controllers\EmployeePayrollPreviewController::class, 'approve'])
        ->middleware('permission:approve payroll')->name('employee-payroll.approve');
    Route::get('employee-payroll/snapshots/{runId}', [App\Http\Controllers\EmployeePayrollPreviewController::class, 'showRun'])
        ->middleware('permission:read payroll')->name('employee-payroll.run');
    Route::post('employee-payroll/snapshots/{runId}/payments', [App\Http\Controllers\EmployeePayrollPreviewController::class, 'linkPayment'])
        ->middleware('permission:approve payroll')->name('employee-payroll.payment.link');

    Route::controller(RoleController::class)->group(function () {
        Route::get('role', 'index')->middleware(['permission:read role'])->name('role.index');
        Route::post('role', 'store')->middleware(['permission:create role'])->name('role.store');
        Route::post('role/show', 'show')->middleware(['permission:read role'])->name('role.show');
        Route::put('role', 'update')->middleware(['permission:update role'])->name('role.update');
        Route::delete('role', 'destroy')->middleware(['permission:delete role'])->name('role.destroy');
    });

    Route::controller(PermissionController::class)->group(function () {
        Route::get('permission', 'index')->middleware(['permission:read permission'])->name('permission.index');
        Route::post('permission', 'store')->middleware(['permission:create permission'])->name('permission.store');
        Route::post('permission/show', 'show')->middleware(['permission:read permission'])->name('permission.show');
        Route::put('permission', 'update')->middleware(['permission:update permission'])->name('permission.update');
        Route::delete('permission', 'destroy')->middleware(['permission:delete permission'])->name('permission.destroy');
        Route::get('permission/reload', 'reloadPermission')->middleware(['permission:create permission'])->name('permission.reload');
    });

    Route::get('module', [ModuleController::class, 'index'])->middleware(['permission:read module'])->name('module.index');

    Route::get('filemanager', [FileManagerController::class, 'index'])->middleware(['permission:filemanager'])->name('filemanager');

    Route::controller(SettingController::class)->group(function () {
        Route::get('setting', 'index')->middleware(['permission:read setting'])->name('setting.index');
        Route::post('setting', 'store')->middleware(['permission:create setting'])->name('setting.store');
        Route::post('setting/show', 'show')->middleware(['permission:read setting'])->name('setting.show');
        Route::put('setting', 'update')->middleware(['permission:update setting'])->name('setting.update');
        Route::post('setting/bank-account', 'saveProjectBankAccount')->middleware(['permission:update setting'])->name('setting.bank-account.save');
        Route::post('setting/cash-account', 'saveProjectCashAccount')->middleware(['permission:update setting'])->name('setting.cash-account.save');
        Route::get('setting/account/{type}/{id}', 'accountSettingsDetail')->where(['type'=>'bank|cash','id'=>'[0-9]+'])->middleware(['permission:update setting'])->name('setting.account.detail');
        Route::post('setting/account/{type}/{id}/status', 'changeProjectAccountStatus')->where(['type'=>'bank|cash','id'=>'[0-9]+'])->middleware(['permission:update setting'])->name('setting.account.status');
        Route::post('setting/payment-method-labels', 'savePaymentMethodLabels')->middleware(['permission:update setting'])->name('setting.payment-method-labels.save');
        Route::post('setting/voucher-display', 'saveVoucherDisplaySettings')->middleware(['permission:update setting'])->name('setting.voucher-display.save');
        Route::get('setting/voucher-prefix-preview', 'previewVoucherPrefix')->middleware(['permission:update setting'])->name('setting.voucher-prefix.preview');
        Route::post('setting/voucher-availability', 'saveVoucherAvailability')->middleware(['permission:update setting'])->name('setting.voucher-availability.save');
        Route::post('setting/custom-payment-method', 'saveCustomPaymentMethod')->middleware(['permission:update setting'])->name('setting.custom-payment-method.save');
        Route::delete('setting', 'destroy')->middleware(['permission:delete setting'])->name('setting.destroy');
    });


    Route::controller(App\Http\Controllers\ProjectController::class)->group(function () {
        Route::get('project', 'index')->middleware(['permission:read project'])->name('project.index');
        Route::post('project', 'store')->middleware(['permission:create project'])->name('project.store');
        Route::post('project/show', 'show')->middleware(['permission:read project'])->name('project.show');
        Route::put('project', 'update')->middleware(['permission:update project'])->name('project.update');
        Route::delete('project', 'destroy')->middleware(['permission:delete project'])->name('project.destroy');

        Route::get('project/zone/add', 'add_zone')->middleware(['permission:read project'])->name('project.add_zone');
        Route::post('select_town', 'selectTown')->middleware(['permission:create project'])->name('project.select_town');

    });

    Route::controller(App\Http\Controllers\ZoneController::class)->group(function () {
        Route::get('zone', 'index')->middleware(['permission:read sector'])->name('project.zone.index');
        Route::post('zone', 'store')->middleware(['permission:create sector'])->name('project.zone.store');
        Route::post('zone/show', 'show')->middleware(['permission:read sector'])->name('project.zone.show');
        Route::put('zone', 'update')->middleware(['permission:update sector'])->name('project.zone.update');
        Route::delete('zone', 'destroy')->middleware(['permission:delete sector'])->name('project.zone.destroy');
    });

    Route::controller(App\Http\Controllers\AreaController::class)->group(function () {
        Route::get('area', 'index')->middleware(['permission:read area'])->name('area.index');
        Route::post('area', 'store')->middleware(['permission:create area'])->name('area.store');
        Route::post('area/show', 'show')->middleware(['permission:read area'])->name('area.show');
        Route::put('area', 'update')->middleware(['permission:update area'])->name('area.update');
        Route::delete('area', 'destroy')->middleware(['permission:delete area'])->name('area.destroy');
    });

    Route::controller(App\Http\Controllers\PlotController::class)->group(function () {
        Route::get('plot', 'index')->middleware(['permission:read plot'])->name('project.plot.index');
        Route::post('plot', 'store')->middleware(['permission:create plot'])->name('project.plot.store');
        Route::post('plot/show', 'show')->middleware(['permission:read plot'])->name('project.plot.show');
        Route::put('plot', 'update')->middleware(['permission:update plot'])->name('project.plot.update');
        Route::delete('plot', 'destroy')->middleware(['permission:delete plot'])->name('project.plot.destroy');
        Route::get('hold_plot', 'holdPlots')->middleware(['permission:read plot'])->name('project.plot.hold_plot');
        Route::post('hold', 'hold')->middleware(['permission:create plot'])->name('project.plot.hold');
        Route::delete('unhold', 'unHold')->middleware(['permission:delete plot'])->name('project.plot.unhold');

        Route::post('plot/number/update', 'update_plot_number')->middleware('permission:update plot number')->name('update_plot_number.store');
        Route::get('plot/project/inventory', 'inventory')->middleware(['permission:view inventory'])->name('booking.plot.inventory');

        Route::get('plot/{id}/history', 'showPlotHistory')->middleware('permission:read plot')->name('project.plot.history');



    });

    Route::controller(App\Http\Controllers\LeadController::class)->group(function () {
        Route::get('crm/lead', 'index')->middleware(['permission:read lead'])->name('crm.lead.index');
        Route::post('crm/lead', 'store')->middleware(['permission:create lead'])->name('crm.lead.store');
        Route::post('crm/lead/show', 'show')->middleware(['permission:read lead'])->name('crm.lead.show');
        Route::put('crm/lead', 'update')->middleware(['permission:update lead'])->name('crm.lead.update');
        Route::delete('crm/lead', 'destroy')->middleware(['permission:delete lead'])->name('crm.lead.destroy');
        Route::post('crm/request-edit', 'requestEditBtn')->middleware(['permission:lead search'])->name('request.edit.btn');

        Route::get('crm/lead/assign/', 'assign')->middleware(['permission:read lead'])->name('crm.lead.assign');
        Route::get('crm/lead/work/', 'details')->middleware('permission:read lead')->name('lead.work');
        Route::post('work/lead', 'logUpdate')->middleware(['permission:create lead'])->name('lead.work.store');
        Route::post('crm/lead/search/', 'search')->middleware('permission:lead search')->name('lead.search');
    });

    Route::controller(App\Http\Controllers\LeadController::class)->group(function () {
        Route::get('approvals/leads', 'approvalIndex')->name('approvals.leads.index');
        Route::post('approvals/leads/{pending}/approve', 'approvePendingAssignment')->name('approvals.leads.approve');
        Route::post('approvals/leads/{pending}/reject', 'rejectPendingAssignment')->name('approvals.leads.reject');
    });

    Route::controller(App\Http\Controllers\ReportController::class)->group(function () {
        Route::get('report/leads', 'index')->middleware(['permission:lead report'])->name('report.lead.index');
        Route::get('report/users', 'users_report')->middleware(['permission:lead report'])->name('report.users');
        Route::get('report/check', 'check_report')->middleware(['permission:cheque report'])->name('report.check');
        Route::post('report/check', 'check_report')->middleware(['permission:cheque report'])->name('report.check.post');
        Route::put('report/check', 'bank_posting_check')->middleware(['permission:pass cheque'])->name('report.check.bank.posting');
        Route::get('reports/check-history/{id}', 'checkHistory')->middleware('permission:cheque report')->name('admin.reports.check_history');


    });

    Route::controller(App\Http\Controllers\AccountingController::class)->group(function () {

        //accounting routes

        Route::post('accounting', 'store')->middleware(['permission:create accounting'])->name('accounting.store');


        Route::post('get_accounts_by_project', 'get_accounts_by_project')->middleware(['permission:read voucher'])->name('get_accounts_by_project');
        Route::post('get_subaccounts_by_project', 'get_subaccounts_by_project')->middleware(['permission:read voucher'])->name('get_subaccounts_by_project');
        Route::post('get_account', 'get_account')->middleware(['permission:read voucher'])->name('get_account');


        //head accounts routes

        Route::get('finance/accounting/head_account', 'head_index')->middleware(['permission:read accounting'])->name('accounting.head_index');
        Route::get('finance/accounting/groups', 'account_groups_index')->middleware(['permission:read accounting'])->name('accounting.groups');
        Route::post('finance/accounting/groups', 'account_group_store')->middleware(['permission:create accounting'])->name('accounting.group_store');
        Route::put('finance/accounting/groups/{group}', 'account_group_update')->middleware(['permission:update accounting'])->name('accounting.group_update');
        Route::post('finance/accounting/groups/{group}/assign-head', 'account_group_assign_head')->middleware(['permission:update accounting'])->name('accounting.group_assign_head');
        Route::post('finance/accounting/groups/{group}/merge', 'account_group_merge_apply')->middleware(['permission:update accounting'])->name('accounting.group_merge_apply');
        Route::post('finance/accounting/head_account', 'head_store')->middleware(['permission:create accounting'])->name('accounting.head_store');
        Route::post('finance/accounting/head_account/show', 'head_show')->middleware(['permission:read accounting'])->name('accounting.head_show');
        Route::put('finance/accounting/head_account', 'head_update')->middleware(['permission:update accounting'])->name('accounting.head_update');
        Route::delete('finance/accounting/head_account', 'head_destroy')->middleware(['permission:delete accounting'])->name('accounting.head_destroy');

        //sub head accounts routes

        Route::get('finance/accounting/child_account', 'subhead_index')->middleware(['permission:read accounting'])->name('accounting.subhead_index');
        Route::post('finance/accounting/subhead_account', 'subhead_store')->middleware(['permission:create accounting'])->name('accounting.subhead_store');
        Route::post('finance/accounting/subhead_account/show', 'subhead_show')->middleware(['permission:read accounting'])->name('accounting.subhead_show');
        Route::put('finance/accounting/subhead_account', 'subhead_update')->middleware(['permission:update accounting'])->name('accounting.subhead_update');
        Route::delete('finance/accounting/subhead_account', 'subhead_destroy')->middleware(['permission:delete accounting'])->name('accounting.subhead_destroy');

        Route::get('finance/accounting/category', 'category_index')->middleware(['permission:read accounting'])->name('accounting.category_index');
        Route::get('finance/accounting/accountant', 'Accountant')->middleware(['permission:read accounting'])->name('accounting.Accountant');
        Route::get('finance/accounting/{user}/ledgers', 'AccountantLedgers')->middleware(['permission:read accounting'])->name('accounting.Accountant.ledgers');
        Route::post('finance/accounting/category', 'category_store')->middleware(['permission:create accounting'])->name('accounting.category_store');
        Route::get('finance/accounting/category/edit/{id}', 'category_edit')->middleware(['permission:read accounting'])->name('accounting.category_edit');
        Route::post('finance/accounting/category/update/{id}', 'category_update')->middleware(['permission:update accounting'])->name('accounting.category_update');
        Route::delete('finance/accounting/category/delete/{id}', 'category_delete')->middleware(['permission:delete accounting'])->name('accounting.category_delete');
        // Route::post('accounting/category', 'category_store')->middleware(['permission:create accounting'])->name('accounting.category_store');
        // Route::post('accounting/category/show', 'category_show')->middleware(['permission:read accounting'])->name('accounting.category_show');
        // Route::put('accounting/category', 'category_update')->middleware(['permission:update accounting'])->name('accounting.category_update');
        // Route::delete('accounting/category', 'category_destroy')->middleware(['permission:delete accounting'])->name('accounting.category_destroy');


        //checks accounts route

        Route::get('finance/reports/details', 'details_index')->middleware(['permission:report party_ledger'])->name('finance.reports.details_index');
        Route::post('finance/reports/details', 'details_party_ledger')->middleware(['permission:report party_ledger'])->name('finance.reports.details_index');


    });

    // Phase 02.1: online receipt/payment source capture only; no bank voucher posting.
    Route::get('finance/online-transactions', [\App\Http\Controllers\Finance\OnlineIntakeController::class,'index'])
        ->name('finance.online.index');
    Route::get('finance/online-transactions/data', [\App\Http\Controllers\Finance\OnlineIntakeController::class,'data'])
        ->name('finance.online.data');
    Route::get('finance/online-transactions/{id}/proof', [\App\Http\Controllers\Finance\OnlineIntakeController::class,'proof'])
        ->whereNumber('id')->name('finance.online.proof');
    Route::get('finance/online-transactions/eligible', [\App\Http\Controllers\Finance\OnlineIntakeController::class,'eligible'])
        ->name('finance.online.eligible');
    Route::get('finance/bank-source/{method}/{source}/linked-vouchers/print', [\App\Http\Controllers\Finance\BankSourceLinkedVoucherPrintController::class, 'show'])
        ->where(['method' => 'online|cheque', 'source' => '[0-9]+'])
        ->name('finance.bank_source.linked_print');
    Route::get('finance/online-transactions/{id}/history', [\App\Http\Controllers\Finance\OnlineIntakeController::class,'history'])
        ->whereNumber('id')->name('finance.online.history');
    Route::post('finance/online-transactions/{id}/confirm', [\App\Http\Controllers\Finance\OnlineIntakeController::class,'confirm'])
        ->whereNumber('id')->middleware(['throttle:20,1'])->name('finance.online.confirm');
    Route::post('finance/online-transactions', [\App\Http\Controllers\Finance\OnlineIntakeController::class,'store'])
        ->middleware(['throttle:20,1'])->name('finance.online.store');

    Route::get('finance/bank-management/{method}/{id}/edit',
        [\App\Http\Controllers\Finance\BankManagementMutationController::class, 'show'])
        ->where(['method'=>'online|cheque','id'=>'[0-9]+'])->name('finance.bank-management.edit');
    Route::put('finance/bank-management/{method}/{id}',
        [\App\Http\Controllers\Finance\BankManagementMutationController::class, 'update'])
        ->where(['method'=>'online|cheque','id'=>'[0-9]+'])->name('finance.bank-management.update');
    Route::delete('finance/bank-management/{method}/{id}',
        [\App\Http\Controllers\Finance\BankManagementMutationController::class, 'destroy'])
        ->where(['method'=>'online|cheque','id'=>'[0-9]+'])->name('finance.bank-management.delete');

    Route::post('finance/bank-management/{method}/{id}/correction/preview',
        [\App\Http\Controllers\Finance\BankLinkedCorrectionController::class,'preview'])
        ->where(['method'=>'online|cheque','id'=>'[0-9]+'])
        ->middleware(['throttle:30,1'])->name('finance.bank-management.correction.preview');
    Route::post('finance/bank-management/{method}/{id}/correction/confirm',
        [\App\Http\Controllers\Finance\BankLinkedCorrectionController::class,'confirm'])
        ->where(['method'=>'online|cheque','id'=>'[0-9]+'])
        ->middleware(['throttle:10,1'])->name('finance.bank-management.correction.confirm');

    Route::get('finance/bank-management/correction-requests/pending',
        [\App\Http\Controllers\Finance\BankLinkedCorrectionController::class,'pending'])
        ->name('finance.bank-management.correction.pending');
    Route::post('finance/bank-management/correction-requests/{requestId}/decide',
        [\App\Http\Controllers\Finance\BankLinkedCorrectionController::class,'decide'])
        ->whereNumber('requestId')->middleware(['throttle:15,1'])
        ->name('finance.bank-management.correction.decide');

    Route::get('finance/voucher/passed-sources', [\App\Http\Controllers\Finance\PassedBankSourcePickerController::class,'index'])
        ->name('finance.voucher.passed_sources');

    // Phase 01: receipt-only cheque intake; deliberately separate from cash voucher posting.
    Route::get('finance/cheques', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'index'])
        ->name('finance.cheques.index');
    Route::get('finance/cheques/data', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'data'])
        ->name('finance.cheques.data');
    Route::get('finance/cheques/{id}/slip', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'slip'])
        ->whereNumber('id')->name('finance.cheques.slip');
    Route::post('finance/cheques/{id}/action', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'act'])
        ->whereNumber('id')->middleware(['throttle:20,1'])->name('finance.cheques.action');
    Route::get('finance/cheques/{id}/history', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'history'])
        ->whereNumber('id')->name('finance.cheques.history');
    Route::get('finance/cheques/{id}/memos/{event}', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'memo'])
        ->whereNumber('id')->whereNumber('event')->name('finance.cheques.memo');
    Route::post('finance/cheques/{id}/finalize', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'finalize'])
        ->whereNumber('id')->middleware(['throttle:20,1'])->name('finance.cheques.finalize');
    Route::post('finance/cheques', [\App\Http\Controllers\Finance\ChequeIntakeController::class, 'store'])
        ->middleware(['throttle:20,1'])->name('finance.cheques.store');

    Route::get('finance/voucher/cash-only/attachment/{attachment}', [\App\Http\Controllers\Finance\CashOnlyVoucherController::class, 'attachment'])
        ->whereNumber('attachment')->middleware('permission:read voucher|voucher approver')->name('finance.voucher.cash-only.attachment');
    Route::post('finance/voucher/cash-only/draft', [\App\Http\Controllers\Finance\CashOnlyVoucherController::class, 'draft'])
        ->middleware(['throttle:20,1'])->name('finance.voucher.cash-only.draft');
    Route::post('finance/voucher/cash-only', [\App\Http\Controllers\Finance\CashOnlyVoucherController::class, 'store'])
        ->middleware(['throttle:20,1'])->name('finance.voucher.cash-only.store');

    Route::controller(App\Http\Controllers\Finance\VoucherController::class)->group(function () {

        //accounting routes
        Route::post('finance/check_new_voucher_number', 'checkNewVoucherNumber')->middleware(['permission:read voucher'])->name('check_new_voucher_number');
        Route::get('finance/voucher', 'index')->middleware(['permission:read voucher'])->name('finance.voucher.index');
        Route::get('finance/voucher/pending_updates', 'pendingIndex')->middleware(['permission:read voucher'])->name('finance.voucher.pending_updates_index');
        Route::get('approvals/finance', 'financeApprovalIndex')->middleware(['permission:read voucher'])->name('approvals.finance.index');
        Route::post('approvals/finance/{pending}/approve', 'approveFinancePending')->name('approvals.finance.approve');
        Route::post('approvals/finance/{pending}/reject', 'rejectFinancePending')->name('approvals.finance.reject');
        Route::get('finance/voucher/in', 'cash_in')->name('finance.voucher.in');
        Route::get('finance/voucher/out', 'cash_out')->name('finance.voucher.out');
        Route::get('finance/voucher/in/{method}', 'cash_in')->where('method', 'cash|online|cheque')->name('finance.voucher.in.method');
        Route::get('finance/voucher/out/{method}', 'cash_out')->where('method', 'cash|online|cheque')->name('finance.voucher.out.method');
        Route::get('finance/voucher/pending-bank-payments', 'pendingBankPayments')->middleware(['permission:read voucher'])->name('finance.voucher.pending_bank_payments');
        Route::post('finance/voucher/pending-bank-payments/{customerLedger}/status', 'updatePendingBankPaymentStatus')->middleware(['permission:pass cheque'])->name('finance.voucher.pending_bank_payments.status');
        Route::get('finance/voucher/bank/{direction}', 'bankVoucherWorkspace')->where('direction', 'receipt|payment')->name('finance.voucher.bank.workspace');
        Route::get('finance/voucher/bank/{direction}/sources', 'bankVoucherSources')->where('direction', 'receipt|payment')->name('finance.voucher.bank.sources');
        Route::get('finance/voucher/bank/{direction}/history', 'bankVoucherHistory')->where('direction', 'receipt|payment')->name('finance.voucher.bank.history');
        Route::get('finance/voucher/bank/{direction}/{postingBatch}/print', 'printBankVoucher')->where(['direction' => 'receipt|payment', 'postingBatch' => '[0-9]+'])->name('finance.voucher.bank.print');
        Route::get('finance/voucher/bank-transfer/{kind}', 'transferVoucherWorkspace')->where('kind', 'deposit|withdrawal')->name('finance.voucher.transfer.workspace');
        Route::get('finance/voucher/bank-transfer/{kind}/next-number', 'transferVoucherNextNumber')
            ->where('kind','deposit|withdrawal')->name('finance.voucher.transfer.next-number');
        Route::get('finance/voucher/bank-transfer/{kind}/sources', 'transferVoucherSources')->where('kind', 'deposit|withdrawal')->name('finance.voucher.transfer.sources');
        Route::get('finance/voucher/bank-transfer/{kind}/history', 'transferVoucherHistory')->where('kind', 'deposit|withdrawal')->name('finance.voucher.transfer.history');
        Route::post('finance/voucher/bank-transfer/{kind}', 'postTransferVoucher')->where('kind', 'deposit|withdrawal')->name('finance.voucher.transfer.post');
        Route::get('finance/voucher/bank-transfer/{kind}/{postingBatch}/details', 'transferVoucherDetails')->where(['kind'=>'deposit|withdrawal','postingBatch'=>'[0-9]+'])->name('finance.voucher.transfer.details');
        Route::put('finance/voucher/bank-transfer/{kind}/{postingBatch}', 'updateTransferVoucher')->where(['kind'=>'deposit|withdrawal','postingBatch'=>'[0-9]+'])->name('finance.voucher.transfer.update');
        Route::delete('finance/voucher/bank-transfer/{kind}/{postingBatch}', 'deleteTransferVoucher')->where(['kind'=>'deposit|withdrawal','postingBatch'=>'[0-9]+'])->name('finance.voucher.transfer.delete');
        Route::post('finance/voucher/bank-transfer/{kind}/{postingBatch}/reverse', 'reverseTransferVoucher')
            ->where(['kind' => 'deposit|withdrawal', 'postingBatch' => '[0-9]+'])
            ->middleware(['throttle:10,1'])->name('finance.voucher.transfer.reverse');
        Route::get('finance/voucher/bank-transfer/{kind}/{postingBatch}/print', 'printTransferVoucher')->where(['kind' => 'deposit|withdrawal', 'postingBatch' => '[0-9]+'])->name('finance.voucher.transfer.print');
        Route::get('finance/voucher/plot-receipt', 'plotReceiptWorkspace')->name('finance.voucher.plot-receipt.workspace');
        Route::get('finance/voucher/plot-next-number/{series}', 'plotVoucherNextNumber')
            ->where('series','PPR|PP')->name('finance.voucher.plot-next-number');
        Route::get('finance/voucher/plot-receipt/options', 'plotReceiptOptions')->name('finance.voucher.plot-receipt.options');
        Route::get('finance/voucher/plot-receipt/history', 'plotReceiptHistory')->name('finance.voucher.plot-receipt.history');
        Route::post('finance/voucher/plot-receipt', 'postPlotReceipt')->name('finance.voucher.plot-receipt.post');
        Route::delete('finance/voucher/plot-receipt/{receiptId}', 'deletePlotReceipt')->whereNumber('receiptId')->name('finance.voucher.plot-receipt.delete');
        Route::patch('finance/voucher/plot-receipt/{receiptId}', 'updatePlotReceipt')->whereNumber('receiptId')->name('finance.voucher.plot-receipt.update');
        Route::patch('finance/voucher/plot-receipt/{receiptId}/narration', 'updatePlotReceiptNarration')->whereNumber('receiptId')->name('finance.voucher.plot-receipt.narration.update');
        Route::post('finance/voucher/plot-receipt/{receiptId}/reverse', 'reversePlotReceipt')->whereNumber('receiptId')
            ->middleware(['throttle:10,1'])->name('finance.voucher.plot-receipt.reverse');
        Route::get('finance/voucher/plot-receipt/{receiptId}', 'plotReceiptDetails')->whereNumber('receiptId')->name('finance.voucher.plot-receipt.details');
        Route::get('finance/voucher/plot-receipt/{receiptId}/plots/{plotId}/print', 'printPlotReceipt')->whereNumber('receiptId')->whereNumber('plotId')->name('finance.voucher.plot-receipt.print');
        Route::get('finance/voucher/plot-purchase', 'plotPurchaseWorkspace')->name('finance.voucher.plot-purchase.workspace');
        Route::get('finance/voucher/plot-purchase/options', 'plotPurchaseOptions')->name('finance.voucher.plot-purchase.options');
        Route::get('finance/voucher/plot-purchase/history', 'plotPurchaseHistory')->name('finance.voucher.plot-purchase.history');
        Route::post('finance/voucher/plot-purchase', 'postPlotPurchase')->name('finance.voucher.plot-purchase.post');
        Route::delete('finance/voucher/plot-purchase/{purchaseId}', 'deletePlotPurchase')->whereNumber('purchaseId')->name('finance.voucher.plot-purchase.delete');
        Route::patch('finance/voucher/plot-purchase/{purchaseId}', 'updatePlotPurchase')->whereNumber('purchaseId')->name('finance.voucher.plot-purchase.update');
        Route::patch('finance/voucher/plot-purchase/{purchaseId}/narration', 'updatePlotPurchaseNarration')->whereNumber('purchaseId')->name('finance.voucher.plot-purchase.narration.update');
        Route::post('finance/voucher/plot-purchase/{purchaseId}/reverse', 'reversePlotPurchase')->whereNumber('purchaseId')
            ->middleware(['throttle:10,1'])->name('finance.voucher.plot-purchase.reverse');
        Route::get('finance/voucher/plot-purchase/{purchaseId}/print', 'printPlotPurchase')->whereNumber('purchaseId')->name('finance.voucher.plot-purchase.print');
        Route::get('finance/voucher/plot-purchase/{purchaseId}', 'plotPurchaseDetails')->whereNumber('purchaseId')->name('finance.voucher.plot-purchase.details');
        Route::get('finance/voucher/data', 'cash_out_data')->middleware(['permission:read voucher'])->name('voucher.cash_out.data');
        Route::get('finance/voucher/data/in', 'cash_in_data')->middleware(['permission:read voucher'])->name('voucher.cash_in.data');
        Route::get('finance/voucher/data/in/{method}', 'cash_in_data')->where('method', 'cash|online|cheque')->middleware(['permission:read voucher'])->name('voucher.cash_in.method.data');
        Route::get('finance/voucher/data/out/{method}', 'cash_out_data')->where('method', 'cash|online|cheque')->middleware(['permission:read voucher'])->name('voucher.cash_out.method.data');
        Route::get('finance/voucher/draft', 'cash_draft')->name('finance.voucher.draft');
        Route::post('finance/voucher/{id}/approve', 'approve')->name('finance.voucher.approve');
        Route::post('finance/voucher/{id}/reject', 'reject')->name('finance.voucher.reject');
        Route::post('finance/voucher/{id}/approveadmin', 'approveAdmin')->name('finance.voucher.approveadmin');
        Route::post('finance/voucher/{id}/rejectadmin', 'rejectAdmin')->name('finance.voucher.rejectadmin');
        Route::post('finance/voucher/getcomment', 'GetComment')->middleware(['permission:read voucher'])->name('finance.voucher.getcomment');
        Route::get('finance/voucher/print/{id}', 'print')->name('finance.voucher.print');
        Route::get('finance/voucher/print/{id}/method/{method}', 'print')->where('method', 'cash|online|cheque')->name('finance.voucher.print.method');
        Route::get('finance/voucher/source/{customerLedger}/print', 'printTypedSource')->whereNumber('customerLedger')
            ->name('finance.voucher.source.print');
    });

    Route::controller(App\Http\Controllers\Finance\DailyVoucherDirectoryController::class)->prefix('finance/daily-vouchers')->name('finance.daily-vouchers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/data', 'data')->name('data');
        Route::get('/sources/{customerLedger}/print', 'printSource')->whereNumber('customerLedger')->name('source.print');
    });
    Route::post('finance/daily-vouchers/sources/{customerLedger}/reverse',
        [\App\Http\Controllers\Finance\DailyVoucherDirectoryController::class,'reverseTypedCashSource'])
        ->whereNumber('customerLedger')
        ->middleware(['throttle:10,1'])
        ->name('finance.daily-vouchers.sources.reverse');
    Route::get('finance/daily-vouchers/revise/{customerLedger}', [\App\Http\Controllers\Finance\RejectedCashVoucherController::class,'edit'])
        ->name('finance.daily-vouchers.revise.edit');
    Route::post('finance/daily-vouchers/revise/{customerLedger}', [\App\Http\Controllers\Finance\RejectedCashVoucherController::class,'store'])
        ->middleware(['throttle:20,1'])->name('finance.daily-vouchers.revise.store');
    Route::post('finance/daily-vouchers/bank-allocation/{customerLedger}/release',
        [\App\Http\Controllers\Finance\BankVoucherAllocationReleaseController::class,'store'])
        ->whereNumber('customerLedger')
        ->middleware(['throttle:10,1'])
        ->name('finance.daily-vouchers.bank-allocation.release');
    Route::get('finance/voucher-settings', [\App\Http\Controllers\Finance\VoucherSettingsController::class,'index'])
        ->middleware('permission:read setting')->name('finance.voucher-settings.index');
    Route::post('finance/voucher-settings', [\App\Http\Controllers\Finance\VoucherSettingsController::class,'save'])
        ->middleware(['permission:update setting','throttle:20,1'])->name('finance.voucher-settings.save');
    Route::post('finance/voucher-settings/reversal', [\App\Http\Controllers\Finance\VoucherSettingsController::class,'saveReversal'])
        ->middleware(['permission:update setting','throttle:20,1'])->name('finance.voucher-settings.reversal.save');

    Route::prefix('finance/daily-vouchers/approvals')->name('finance.daily-vouchers.approvals.')
        ->controller(App\Http\Controllers\Finance\DailyVoucherApprovalController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/policy', 'savePolicy')->name('policy');
            Route::post('/{approval}/{decision}', 'decide')->whereNumber('approval')->name('decide');
        });

    Route::controller(App\Http\Controllers\Finance\PaymentClearanceController::class)->group(function () {
        Route::get('finance/payment-clearance', 'index')->middleware(['permission:read voucher'])->name('finance.payment_clearance.index');
        Route::get('finance/payment-clearance/data', 'data')->middleware(['permission:read voucher'])->name('finance.payment_clearance.data');
    });

    Route::controller(DastiCashController::class)->prefix('dasticash')->name('dasticash.')->group(function () {
        Route::post('/store', 'store')->middleware(['permission:create dasticash'])->name('store');
        Route::get('/{id}/edit', 'edit')->middleware(['permission:update dasticash'])->name('edit');
        Route::put('/{id}', 'update')->middleware(['permission:update dasticash'])->name('update');
        Route::delete('/{id}', 'destroy')->middleware(['permission:delete dasticash'])->name('destroy');
    });

    // The labour master and attendance paths use existing real Spatie grants.
    // Labour payment/CP routes remain intentionally unchanged and ON HOLD.
    Route::get('labours', [LabourController::class,'index'])->middleware('permission:read labour')->name('labours.index');
    Route::get('labours/create', [LabourController::class,'create'])->middleware('permission:create labour')->name('labours.create');
    Route::post('labours', [LabourController::class,'store'])->middleware('permission:create labour')->name('labours.store');
    Route::get('labours/{labour}/edit', [LabourController::class,'edit'])->middleware('permission:update labour')->name('labours.edit');
    Route::put('labours/{labour}', [LabourController::class,'update'])->middleware('permission:update labour')->name('labours.update');
    Route::patch('labours/{labour}', [LabourController::class,'update'])->middleware('permission:update labour');
    Route::delete('labours/{labour}', [LabourController::class,'destroy'])->middleware('permission:delete labour')->name('labours.destroy');
    Route::get('/contractors', [App\Http\Controllers\ContractorController::class, 'index'])->middleware(['permission:read labour'])->name('contractors.index');
    Route::post('/contractors', [App\Http\Controllers\ContractorController::class, 'store'])->middleware(['permission:create labour'])->name('contractors.store');
    Route::put('/contractors/{contractor}', [App\Http\Controllers\ContractorController::class, 'update'])->middleware(['permission:update labour'])->whereNumber('contractor')->name('contractors.update');
    Route::patch('/contractors/{contractor}/status', [App\Http\Controllers\ContractorController::class, 'toggleStatus'])->middleware(['permission:update labour'])->whereNumber('contractor')->name('contractors.status');
    Route::get('/contractors/{contractor}/ledger', [App\Http\Controllers\ContractorController::class, 'ledger'])->middleware(['permission:read labour'])->whereNumber('contractor')->name('contractors.ledger');
    Route::post('/labours/sitestore', [LabourController::class, 'siteStore'])
        ->middleware('permission:create labour')->name('labours.sitestore');
    Route::get('/labours/attendance/week', [LabourController::class, 'loadAttendanceWeek'])
        ->middleware('permission:read attendance')->name('attendance.week.load');
    Route::get('/labour/{id}', [LabourController::class, 'labourHistory'])
        ->middleware('permission:read labour')->name('labour.history');
    Route::post('/labours/check/validate', [LabourController::class, 'checkValidate'])
        ->middleware('permission:read labour')->name('labours.check.validate');
    Route::patch('/labours/{labour}/status', [LabourController::class, 'updateStatus'])
        ->middleware('permission:update labour')->name('labours.updateStatus');
    Route::post('/labours/attendance', [LabourController::class, 'attendanceStore'])
        ->middleware('permission:create attendance')->name('labours.attendance');
    Route::post('/labours/report', [LabourController::class, 'attendanceReport'])
        ->middleware('permission:read attendance')->name('labours.report');
    Route::post('/attendance/labour-payment', [LabourController::class, 'labourPayment'])->name('labours.payment');
    Route::get('/labours/payment/{source}', [LabourController::class, 'paymentDetails'])
        ->whereNumber('source')->name('labours.payment.details');
    Route::put('/labours/payment/{source}', [LabourController::class, 'updatePayment'])
        ->whereNumber('source')->name('labours.payment.update');
    Route::delete('/labours/payment/{source}', [LabourController::class, 'deletePayment'])
        ->whereNumber('source')->name('labours.payment.delete');
    Route::post('/labours/update-rate', [LabourController::class, 'updateRate'])->middleware('permission:update labour')->name('labours.updateRate');
    Route::post('/labours/person/report', [LabourController::class, 'personAttendanceReport'])
        ->middleware('permission:read attendance')->name('labours.person.report');
    Route::post('/labours/create/voucher', [LabourController::class, 'createVoucher'])
        ->middleware('permission:create labour')->name('labours.create.voucher');
    Route::get('/labours/site-vouchers', [LabourController::class, 'siteVouchers'])
        ->middleware('permission:read labour')->name('labours.site.vouchers');
    Route::post('/labours/print/site/report', [LabourController::class, 'printSiteReport'])
        ->middleware('permission:read labour')->name('labours.print.site.report');
    Route::post('/labours/print/person/report', [LabourController::class, 'printPersonReport'])
        ->middleware('permission:read labour')->name('labours.print.person.report');
    Route::post('/labours/print/attendance/report', [LabourController::class, 'printAttendanceReport'])
        ->middleware('permission:read attendance')->name('labours.print.attendance.report');
    Route::get('stocks', [StockController::class, 'index'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':workspace,view')->name('stocks.index');

    // Meta (items/parties for dropdowns)
    Route::get('stocks/meta', [StockController::class, 'meta'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':workspace,view')->name('stocks.meta');

    // Counters (slip/bill numbers)
    Route::get('stocks/next-slip', [StockController::class, 'nextSlip'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':entries,create')->name('stocks.nextSlip');
    Route::get('stocks/next-bill', [StockController::class, 'nextBill'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':billing,create')->name('stocks.nextBill'); // ?type=purchase|sale

    // Create actions (AJAX)
    Route::post('stocks/items', [StockController::class, 'storeItem'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,create')->name('stocks.items.store');
    Route::get('stocks/items/export', [StockController::class, 'exportItems'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,view')->name('stocks.items.export');
    Route::post('stocks/items/import', [StockController::class, 'stageItemImport'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,create')->name('stocks.items.import.stage');
    Route::get('stocks/items/import/{batch}', [StockController::class, 'previewItemImport'])->whereNumber('batch')->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,view')->name('stocks.items.import.preview');
    Route::get('stocks/items/import-history', [StockController::class, 'itemImportHistory'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,view')->name('stocks.items.import.history');
    Route::post('stocks/items/import/{batch}/apply', [StockController::class, 'applyItemImport'])->whereNumber('batch')->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,edit')->name('stocks.items.import.apply');
    Route::get('stocks/items/import/{batch}/errors', [StockController::class, 'downloadItemImportErrors'])->whereNumber('batch')->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,view')->name('stocks.items.import.errors');
    Route::post('stocks/parties', [StockController::class, 'storeParty'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':parties,create')->name('stocks.parties.store');
    Route::post('stocks/entries', [StockController::class, 'storeEntry'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':entries,create')->name('stocks.entries.store');
    Route::post('stocks/bills', [StockController::class, 'storeBill'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':billing,create')->name('stocks.bills.store');
    Route::get('stocks/documents/{kind}/{id}', [StockController::class, 'stockDocumentDetails'])
        ->where(['kind'=>'entry|bill','id'=>'[0-9]+'])->name('stocks.documents.details');
    Route::put('stocks/documents/{kind}/{id}', [StockController::class, 'updateStockDocument'])
        ->where(['kind'=>'entry|bill','id'=>'[0-9]+'])->name('stocks.documents.update');
    Route::delete('stocks/documents/{kind}/{id}', [StockController::class, 'deleteStockDocument'])
        ->where(['kind'=>'entry|bill','id'=>'[0-9]+'])->name('stocks.documents.delete');
    Route::post('stocks/legacy-reconciliation', [StockController::class, 'reconcileLegacyStock'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':entries,edit')->name('stocks.legacy.reconcile');
    Route::post('stocks/finance/{kind}/{id}/approve', [StockController::class, 'approveFinance'])
        ->where('kind', 'stock_entry|stock_bill')->whereNumber('id')
        ->middleware(['permission:can approve'])->name('stocks.finance.approve');
    Route::put('stocks/items/{item}', [StockController::class, 'updateItem'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,edit')->name('stocks.items.update');
    Route::delete('stocks/items/{item}', [StockController::class, 'deleteItem'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,delete')->name('stocks.items.delete');
    Route::put('stocks/parties/{party}', [StockController::class, 'updateParty'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':parties,edit')->name('stocks.parties.update');
    Route::patch('stocks/parties/{party}/status', [StockController::class, 'updatePartyStatus'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':parties,edit')->name('stocks.parties.status');
    Route::delete('stocks/parties/{party}', [StockController::class, 'deleteParty'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':parties,delete')->name('stocks.parties.delete');

    // Lists (AJAX tables)
    Route::get('stocks/entries', [StockController::class, 'entries'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':entries,view')->name('stocks.entries.index');
    Route::get('stocks/bills', [StockController::class, 'bills'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':billing,view')->name('stocks.bills.index');
    Route::get('stocks/items', [StockController::class, 'items'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':items,view')->name('stocks.items.index');
    Route::get('stocks/parties', [StockController::class, 'parties'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':parties,view')->name('stocks.parties.index');

    // Stock report (date range + optional item filter)
    Route::get('stocks/report', [StockController::class, 'report'])->middleware(\App\Http\Middleware\AuthorizeStockAbility::class.':entries,view')->name('stocks.report');


    Route::controller(App\Http\Controllers\LedgerController::class)->group(function () {

        Route::post('get-subaccount-details', 'getSubaccountDetails')->middleware(['permission:read voucher'])->name('get-subaccount-details');

        Route::get('finance/reports/ledger', 'show_ledger')->middleware(['permission:report cashbook'])->name('finance.reports.show_ledger');
        Route::match(['get', 'post'], 'fetch-data-url', 'fetch_data_url')->middleware(['permission:master report'])->name('fetch-data-url');

        Route::post('accounting/ledger/show', 'show')->name('ledger.show');
        Route::post('accounting/customer/ledger/show', 'customerLedgerShow')->name('customer.ledger.show');
        Route::post('accounting/draft/ledger/show', 'draftShow')->name('draft.ledger.show');
        Route::post('accounting/ledger/store', 'store')->name('ledger.store');
        Route::post('accounting/ledger/store/method/{paymentType}', 'store')->where('paymentType', '1|2|3')->name('ledger.store.fixed_method');
        Route::post('accounting/ledger/save_as_draft', 'saveAsDraft')->name('ledger.save_as_draft');
        Route::post('accounting/ledger/save_as_draft/method/{paymentType}', 'saveAsDraft')->where('paymentType', '1|2|3')->name('ledger.save_as_draft.fixed_method');
        Route::put('accounting/ledger/update', 'update')->name('ledger.update');
        Route::put('accounting/ledger/update/method/{paymentType}', 'update')->where('paymentType', '1|2|3')->name('ledger.update.fixed_method');
        Route::put('accounting/draf/ledger/update', 'draftUpdate')->name('draft.ledger.update');
        Route::delete('accounting/ledger/destroy', 'destroy')->name('ledger.destroy');
        Route::delete('accounting/draft/ledger/destroy', 'draftDestroy')->name('draft.ledger.destroy');

        Route::get('finance/reports/ledger/party', 'show_ledger_party')->middleware(['permission:report party_report'])->name('finance.reports.show_ledger_party');
        Route::get('finance/reports/ledger/head', 'show_ledger_head')->middleware(['permission:report party_report'])->name('finance.reports.show_ledger_head');
        Route::get('finance/reports/trial-balance', 'trialBalance')->middleware(['permission:report party_report'])->name('finance.reports.trial_balance');
        Route::get('finance/reports/trial-balance/show', 'trialBalanceShow')->middleware(['permission:report party_report'])->name('finance.reports.trial_balance.show');
        Route::get('finance/reports/trial-balance/print', 'trialBalancePrint')->middleware(['permission:report party_report'])->name('finance.reports.trial_balance.print');

        Route::post('fetch-data-url-party', 'fetch_data_by_party')->middleware(['permission:master report'])->name('fetch-data_by_party');
        Route::post('fetch-data-url-head', 'fetch_data_by_head')->middleware(['permission:master report'])->name('fetch_data_by_head');




    });

    Route::controller(App\Http\Controllers\BookingController::class)->group(function () {
        Route::post('booking/cancel/{id}', 'cancel')->middleware(['permission:update booking'])->name('booking.cancel');
        Route::get('booking/plot', 'index')->middleware(['permission:read booking'])->name('booking.plot.index');
        Route::get('booking/plot/sale', 'sale')->middleware(['permission:create booking'])->name('booking.plot.sale');
        Route::get('booking/{id}/edit', 'editBooking')->middleware(['permission:update booking|update booking price'])->name('booking.edit');
        Route::put('booking/{id}', 'updateBooking')->middleware(['permission:update booking|update booking price'])->name('booking.update');
        Route::get('booking/plot/file-transfer/{id}', 'fileTransfer')->middleware(['permission:read booking'])->name('booking.plot.file-transfer');
        Route::post('booking/plot/sale', 'store')->middleware(['permission:create booking'])->name('bookings.store');
        Route::post('booking/plot/transfer', 'update')->middleware(['permission:update booking'])->name('bookings.transfer');
        Route::post('charge-type-store', 'chargeTypeStore')->middleware(['permission:create booking'])->name('charge-type.store');

        Route::get('booking/price/update/{id}', 'legacyPriceRedirect')->middleware(['permission:update booking price'])->name('booking.price.update');


        Route::get('booking/plot/schedule/{id}', 'scheduleForm')->middleware(['permission:read booking'])->name('booking.schedule.form');
        Route::post('booking/plot/schedule/store', 'storePaymentSchedule')->middleware(['permission:update booking'])->name('payment_schedule.store');
        Route::get('booking/plot/voucher/', 'cash_in')->middleware(['permission:read slip'])->name('payment_schedule.cash');
        Route::any('booking/plot/voucher/update/{id}', 'updateCashIn')->middleware(['permission:read slip'])->name('payment_schedule.cash.update');
        Route::get('booking/plot/voucher/print/{id}', 'printCashIn')->name('booking.customer.print');
        Route::get('extra_charge/', 'extraCharge')->name('payment_schedule.extra_charge');
        Route::get('finance/extra-charge/cash/{source}', 'extraChargeCashDetails')
            ->whereNumber('source')->name('finance.extra-charge.cash.details');
        Route::put('finance/extra-charge/cash/{source}', 'updateExtraChargeCash')
            ->whereNumber('source')->name('finance.extra-charge.cash.update');
        Route::delete('finance/extra-charge/cash/{source}', 'deleteExtraChargeCash')
            ->whereNumber('source')->name('finance.extra-charge.cash.delete');
        Route::get('booking/customer/report/form', 'customer_report_form')->middleware(['permission:view booking report'])->name('booking.customer.report.form');
        Route::post('booking/customer/report/display', 'customer_report_display')->middleware(['permission:view booking report'])->name('booking.customer.report.display');


        Route::post('/get-customers', 'getCustomers')->middleware(['permission:read plot'])->name('get-customers');
        Route::post('/get-customers-byplot', 'getCustomersbyPlot')->middleware(['permission:view booking report'])->name('get-customers-byplot');
        Route::post('/get-plots', 'getPlots')->middleware(['permission:read plot'])->name('get-plots');
        Route::post('/get-plots-list', 'getCustomerPlots')->middleware(['permission:read plot'])->name('get-plots-list');
        Route::post('/get-plot-customer', 'getPlotCustomer')->middleware(['permission:read plot'])->name('get-plot-customer');

        Route::post('booking/plot/voucher/', 'deposit')->middleware(['booking.deposit.action'])->name('booking.customer.deposit');
        Route::delete('booking/plot/destroy', 'destroy')->name('booking.customer.destroy');
        Route::post('booking/plot/show', 'fatch_voucher')->name('booking.voucher.show');
        Route::post('booking/plot/approve', 'approve')->name('booking.customer.approve');

        Route::get('booking/project/recovery_list', 'recovery_list')->middleware(['permission:read booking'])->name('booking.plot.recovery_list');

        Route::delete('booking/destroy/{id}', 'destroy_booking')->middleware(['permission:delete booking'])->name('booking.destroy');
        Route::get('booking/plot/destroy_list', 'destroy_list')->middleware(['permission:delete booking'])->name('booking.destroy_list');




    });

    Route::controller(App\Http\Controllers\JournalVoucherController::class)->group(function () {

        Route::get('sales-voucher', 'salesIndex')->name('sales.voucher.index');
        Route::get('sales-voucher/create', 'create')->name('sales.voucher.create');
        Route::post('sales-voucher/create', 'store')->name('sales.voucher.store');
        Route::get('journal-voucher', 'index')->name('journal.voucher.index');
        Route::get('journal-voucher/create', 'create')->name('journal.voucher.create');
        Route::post('journal-voucher/create', 'store')->name('journal.voucher.store');
        Route::get('journal-vouchers/{id}/edit', 'edit')->name('journal.voucher.edit');
        Route::put('journal-vouchers/{id}', 'update')->name('journal.voucher.update');
        Route::post('journal-vouchers/{id}/resubmit', 'update')->name('journal.voucher.resubmit');
        Route::delete('journal-vouchers/delete/{id}', 'destroy')->name('journal.voucher.delete');

        Route::get('/journal-voucher/print/{id}', 'print')->name('journal.voucher.print');




    });
    Route::controller(App\Http\Controllers\CommissionVoucherController::class)->group(function () {
        Route::get('commision-voucher', 'index')->name('commision.voucher.index');
        Route::get('commision-voucher/create', 'create')->name('commision.voucher.create');
        Route::post('commision-voucher/create', 'store')->name('commision.voucher.store');
        Route::post('commision-vouchers/{id}/approve', 'approve')->name('commision.voucher.approve');
        Route::get('commision-vouchers/{id}/edit', 'edit')->name('commision.voucher.edit');
        Route::put('commision-vouchers/{id}', 'update')->name('commision.voucher.update');
        Route::delete('commision-vouchers/delete/{id}', 'destroy')->name('commision.voucher.delete');

        Route::get('/commision-voucher/print/{id}', 'print')->name('commision.voucher.print');




    });

});
