<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DastiCashController;
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

Route::prefix('admin')->middleware(['auth', 'check.user.status'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('punch', [DashboardController::class, 'punch']);
    Route::get('punch', [DashboardController::class, 'index']);
    Route::get('dashboard/print-leads-by-users', [DashboardController::class, 'printLeadsByUsersReport'])->name('dashboard.leads_by_users_report');

    Route::get('crm/today-lead-work-report', [DashboardController::class, 'todayLeadWorkReport'])->name('crm.todayLeadWorkReport');




    Route::controller(UserController::class)->group(function () {
        Route::get('user', 'index')->middleware(['permission:read user'])->name('user.index');
        Route::post('user', 'store')->middleware(['permission:create user'])->name('user.store');
        Route::post('user/show', 'show')->middleware(['permission:read user'])->name('user.show');
        Route::put('user', 'update')->middleware(['permission:update user'])->name('user.update');
        Route::delete('user', 'destroy')->middleware(['permission:delete user'])->name('user.destroy');
    });

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

        Route::post('plot/number/update', 'update_plot_number')->name('update_plot_number.store');
        Route::get('plot/project/inventory', 'inventory')->middleware(['permission:view inventory'])->name('booking.plot.inventory');

        Route::get('plot/{id}/history', 'showPlotHistory')->name('project.plot.history');



    });



    Route::controller(App\Http\Controllers\LeadController::class)->group(function () {
        Route::get('crm/lead', 'index')->middleware(['permission:read lead'])->name('crm.lead.index');
        Route::post('crm/lead', 'store')->middleware(['permission:create lead'])->name('crm.lead.store');
        Route::post('crm/lead/show', 'show')->middleware(['permission:read lead'])->name('crm.lead.show');
        Route::put('crm/lead', 'update')->middleware(['permission:update lead'])->name('crm.lead.update');
        Route::delete('crm/lead', 'destroy')->middleware(['permission:delete lead'])->name('crm.lead.destroy');
        Route::post('crm/request-edit', 'requestEditBtn')->middleware(['permission:delete lead'])->name('request.edit.btn');

        Route::get('crm/lead/assign/', 'assign')->middleware(['permission:read lead'])->name('crm.lead.assign');
        Route::get('crm/lead/work/', 'details')->name('lead.work');
        Route::post('work/lead', 'logUpdate')->middleware(['permission:create lead'])->name('lead.work.store');
        Route::post('crm/lead/search/', 'search')->name('lead.search');
    });

    Route::controller(App\Http\Controllers\ReportController::class)->group(function () {
        Route::get('report/leads', 'index')->middleware(['permission:lead report'])->name('report.lead.index');
        Route::get('report/users', 'users_report')->middleware(['permission:lead report'])->name('report.users');
        Route::get('report/check', 'check_report')->middleware(['permission:cheque report'])->name('report.check');
        Route::post('report/check', 'check_report')->middleware(['permission:cheque report'])->name('report.check.post');
        Route::put('report/check', 'bank_posting_check')->middleware(['permission:pass cheque'])->name('report.check.bank.posting');
        Route::get('reports/check-history/{id}', 'checkHistory')->name('admin.reports.check_history');


    });

    Route::controller(App\Http\Controllers\AccountingController::class)->group(function () {

        //accounting routes

        Route::post('accounting', 'store')->middleware(['permission:create accounting'])->name('accounting.store');


        Route::post('get_accounts_by_project', 'get_accounts_by_project')->middleware(['permission:read voucher'])->name('get_accounts_by_project');
        Route::post('get_subaccounts_by_project', 'get_subaccounts_by_project')->middleware(['permission:read voucher'])->name('get_subaccounts_by_project');
        Route::post('get_account', 'get_account')->middleware(['permission:read voucher'])->name('get_account');


        //head accounts routes

        Route::get('finance/accounting/head_account', 'head_index')->middleware(['permission:read accounting'])->name('accounting.head_index');
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
        Route::post('finance/accounting/category', 'category_store')->middleware(['permission:read accounting'])->name('accounting.category_store');
        Route::get('finance/accounting/category/edit/{id}', 'category_edit')->middleware(['permission:read accounting'])->name('accounting.category_edit');
        Route::post('finance/accounting/category/update/{id}', 'category_update')->middleware(['permission:read accounting'])->name('accounting.category_update');
        Route::delete('finance/accounting/category/delete/{id}', 'category_delete')->middleware(['permission:read accounting'])->name('accounting.category_delete');
        // Route::post('accounting/category', 'category_store')->middleware(['permission:create accounting'])->name('accounting.category_store');
        // Route::post('accounting/category/show', 'category_show')->middleware(['permission:read accounting'])->name('accounting.category_show');
        // Route::put('accounting/category', 'category_update')->middleware(['permission:update accounting'])->name('accounting.category_update');
        // Route::delete('accounting/category', 'category_destroy')->middleware(['permission:delete accounting'])->name('accounting.category_destroy');


        //checks accounts route

        Route::get('finance/reports/details', 'details_index')->middleware(['permission:report party_ledger'])->name('finance.reports.details_index');
        Route::post('finance/reports/details', 'details_party_ledger')->middleware(['permission:report party_ledger'])->name('finance.reports.details_index');


    });

    Route::controller(App\Http\Controllers\Finance\VoucherController::class)->group(function () {

        //accounting routes

        Route::post('finance/check_new_voucher_number', 'checkNewVoucherNumber')->middleware(['permission:read voucher'])->name('check_new_voucher_number');
        Route::get('finance/voucher', 'index')->middleware(['permission:read voucher'])->name('finance.voucher.index');
        Route::get('finance/voucher/pending_updates', 'pendingIndex')->middleware(['permission:read voucher'])->name('finance.voucher.pending_updates_index');
        Route::get('finance/voucher/in', 'cash_in')->middleware(['permission:read voucher'])->name('finance.voucher.in');
        Route::get('finance/voucher/out', 'cash_out')->middleware(['permission:read voucher'])->name('finance.voucher.out');
        Route::get('finance/voucher/data', 'cash_out_data')->middleware(['permission:read voucher'])->name('voucher.cash_out.data');
        Route::get('finance/voucher/data/in', 'cash_in_data')->middleware(['permission:read voucher'])->name('voucher.cash_in.data');
        Route::get('finance/voucher/draft', 'cash_draft')->middleware(['permission:read voucher'])->name('finance.voucher.draft');
        Route::post('finance/voucher/{id}/approve', 'approve')->middleware(['permission:read voucher'])->name('finance.voucher.approve');
        Route::post('finance/voucher/{id}/reject', 'reject')->middleware(['permission:read voucher'])->name('finance.voucher.reject');
        Route::post('finance/voucher/{id}/approveadmin', 'approveAdmin')->middleware(['permission:read voucher'])->name('finance.voucher.approveadmin');
        Route::post('finance/voucher/{id}/rejectadmin', 'rejectAdmin')->middleware(['permission:read voucher'])->name('finance.voucher.rejectadmin');
        Route::post('finance/voucher/getcomment', 'GetComment')->middleware(['permission:read voucher'])->name('finance.voucher.getcomment');
        Route::get('finance/voucher/print/{id}', 'print')->middleware(['permission:read voucher'])->name('finance.voucher.print');
    });

    Route::controller(DastiCashController::class)->prefix('dasticash')->name('dasticash.')->group(function () {
        Route::post('/store', 'store')->middleware(['permission:create dasticash'])->name('store');
        Route::get('/{id}/edit', 'edit')->middleware(['permission:update dasticash'])->name('edit');
        Route::put('/{id}', 'update')->middleware(['permission:update dasticash'])->name('update');
        Route::delete('/{id}', 'destroy')->middleware(['permission:delete dasticash'])->name('destroy');
    });

    Route::resource('labours', LabourController::class);
    Route::post('/labours/sitestore', [LabourController::class, 'siteStore'])
    ->name('labours.sitestore');
    Route::get('/labours/attendance/week', [LabourController::class, 'loadAttendanceWeek'])
    ->name('attendance.week.load');
    Route::post('/labours/check/validate', [LabourController::class, 'checkValidate'])
    ->name('labours.check.validate');
    Route::patch('/labours/{labour}/status', [LabourController::class, 'updateStatus'])
    ->name('labours.updateStatus');
    Route::post('/labours/attendance', [LabourController::class, 'attendanceStore'])
    ->name('labours.attendance');
    Route::post('/labours/report', [LabourController::class, 'attendanceReport'])
    ->name('labours.report');
    Route::post('/labours/person/report', [LabourController::class, 'personAttendanceReport'])
    ->name('labours.person.report');
    Route::post('/labours/create/voucher', [LabourController::class, 'createVoucher'])
    ->name('labours.create.voucher');
    Route::resource('stocks', StockController::class);


    Route::controller(App\Http\Controllers\LedgerController::class)->group(function () {

        Route::post('get-subaccount-details', 'getSubaccountDetails')->middleware(['permission:read voucher'])->name('get-subaccount-details');

        Route::get('finance/reports/ledger', 'show_ledger')->middleware(['permission:report cashbook'])->name('finance.reports.show_ledger');
        Route::match(['get', 'post'], 'fetch-data-url', 'fetch_data_url')->middleware(['permission:master report'])->name('fetch-data-url');

        Route::post('accounting/ledger/show', 'show')->name('ledger.show');
        Route::post('accounting/customer/ledger/show', 'customerLedgerShow')->name('customer.ledger.show');
        Route::post('accounting/draft/ledger/show', 'draftShow')->name('draft.ledger.show');
        Route::post('accounting/ledger/store', 'store')->middleware(['permission:create voucher'])->name('ledger.store');
        Route::post('accounting/ledger/save_as_draft', 'saveAsDraft')->middleware(['permission:create voucher'])->name('ledger.save_as_draft');
        Route::put('accounting/ledger/update', 'update')->middleware(['permission:update voucher'])->name('ledger.update');
        Route::put('accounting/draf/ledger/update', 'draftUpdate')->middleware(['permission:update voucher'])->name('draft.ledger.update');
        Route::delete('accounting/ledger/destroy', 'destroy')->middleware(['permission:delete voucher'])->name('ledger.destroy');
        Route::delete('accounting/draft/ledger/destroy', 'draftDestroy')->middleware(['permission:delete voucher'])->name('draft.ledger.destroy');

        Route::get('finance/reports/ledger/party', 'show_ledger_party')->middleware(['permission:report party_report'])->name('finance.reports.show_ledger_party');
        Route::get('finance/reports/ledger/head', 'show_ledger_head')->middleware(['permission:report party_report'])->name('finance.reports.show_ledger_head');

        Route::post('fetch-data-url-party', 'fetch_data_by_party')->middleware(['permission:master report'])->name('fetch-data_by_party');
        Route::post('fetch-data-url-head', 'fetch_data_by_head')->middleware(['permission:master report'])->name('fetch_data_by_head');




    });

    Route::controller(App\Http\Controllers\BookingController::class)->group(function () {
        Route::post('booking/cancel/{id}', 'cancel')->middleware(['permission:read plot'])->name('booking.cancel');
        Route::get('booking/plot', 'index')->middleware(['permission:read plot'])->name('booking.plot.index');
        Route::get('booking/plot/sale', 'sale')->middleware(['permission:read plot'])->name('booking.plot.sale');
        Route::get('booking/plot/file-transfer/{id}', 'fileTransfer')->middleware(['permission:read plot'])->name('booking.plot.file-transfer');
        Route::post('booking/plot/sale', 'store')->middleware(['permission:create plot'])->name('bookings.store');
        Route::post('booking/plot/transfer', 'update')->middleware(['permission:create plot'])->name('bookings.transfer');
        Route::post('charge-type-store', 'chargeTypeStore')->middleware(['permission:create plot'])->name('charge-type.store');

        Route::get('booking/price/update/{id}', 'PriceForm')->middleware(['permission:read plot'])->name('booking.price.update');
        Route::post('booking/price/update/store', 'booking_price_update')->name('payment_price_update.store');


        Route::get('booking/plot/schedule/{id}', 'scheduleForm')->middleware(['permission:read plot'])->name('booking.schedule.form');
        Route::post('booking/plot/schedule/store', 'storePaymentSchedule')->middleware(['permission:create plot'])->name('payment_schedule.store');
        Route::get('booking/plot/voucher/', 'cash_in')->middleware(['permission:read slip'])->name('payment_schedule.cash');
        Route::any('booking/plot/voucher/update/{id}', 'updateCashIn')->middleware(['permission:read slip'])->name('payment_schedule.cash.update');
        Route::get('extra_charge/', 'extraCharge')->middleware(['permission:read slip'])->name('payment_schedule.extra_charge');
        Route::get('booking/customer/report/form', 'customer_report_form')->middleware(['permission:view booking report'])->name('booking.customer.report.form');
        Route::post('booking/customer/report/display', 'customer_report_display')->middleware(['permission:view booking report'])->name('booking.customer.report.display');


        Route::post('/get-customers', 'getCustomers')->middleware(['permission:read plot'])->name('get-customers');
        Route::post('/get-customers-byplot', 'getCustomersbyPlot')->middleware(['permission:view booking report'])->name('get-customers-byplot');
        Route::post('/get-plots', 'getPlots')->middleware(['permission:read plot'])->name('get-plots');
        Route::post('/get-plots-list', 'getCustomerPlots')->middleware(['permission:read plot'])->name('get-plots-list');
        Route::post('/get-plot-customer', 'getPlotCustomer')->middleware(['permission:read plot'])->name('get-plot-customer');

        Route::post('booking/plot/voucher/', 'store')->middleware(['permission:create voucher'])->name('ledger.store');
        Route::post('booking/plot/voucher/', 'deposit')->middleware(['permission:create plot'])->name('booking.customer.deposit');
        Route::delete('booking/plot/destroy', 'destroy')->middleware(['permission:delete slip'])->name('booking.customer.destroy');
        Route::post('booking/plot/show', 'fatch_voucher')->middleware(['permission:can approve'])->name('booking.voucher.show');
        Route::post('booking/plot/approve', 'approve')->middleware(['permission:can approve'])->name('booking.customer.approve');

        Route::get('booking/project/recovery_list', 'recovery_list')->middleware(['permission:read plot'])->name('booking.plot.recovery_list');

        Route::delete('booking/destroy/{id}', 'destroy_booking')->middleware(['permission:delete booking'])->name('booking.destroy');
        Route::get('booking/plot/destroy_list', 'destroy_list')->middleware(['permission:delete booking'])->name('booking.destroy_list');




    });

    Route::controller(App\Http\Controllers\JournalVoucherController::class)->group(function () {

        Route::get('journal-voucher', 'index')->middleware(['permission:read jv'])->name('journal.voucher.index');
        Route::get('journal-voucher/create', 'create')->middleware(['permission:create jv'])->name('journal.voucher.create');
        Route::post('journal-voucher/create', 'store')->middleware(['permission:create jv'])->name('journal.voucher.store');
        Route::get('journal-vouchers/{id}/edit', 'edit')->middleware(['permission:edit jv'])->name('journal.voucher.edit');
        Route::put('journal-vouchers/{id}', 'update')->middleware(['permission:edit jv'])->name('journal.voucher.update');
        Route::delete('journal-vouchers/delete/{id}', 'destroy')->middleware(['permission:delete jv'])->name('journal.voucher.delete');

        Route::get('/journal-voucher/print/{id}', 'print')->middleware(['permission:print jv'])->name('journal.voucher.print');




    });
    Route::controller(App\Http\Controllers\CommissionVoucherController::class)->group(function () {
        Route::get('commision-voucher', 'index')->middleware(['permission:read jv'])->name('commision.voucher.index');
        Route::get('commision-voucher/create', 'create')->middleware(['permission:create jv'])->name('commision.voucher.create');
        Route::post('commision-voucher/create', 'store')->middleware(['permission:create jv'])->name('commision.voucher.store');
        Route::get('commision-vouchers/{id}/edit', 'edit')->middleware(['permission:edit jv'])->name('commision.voucher.edit');
        Route::put('commision-vouchers/{id}', 'update')->middleware(['permission:edit jv'])->name('commision.voucher.update');
        Route::delete('commision-vouchers/delete/{id}', 'destroy')->middleware(['permission:delete jv'])->name('commision.voucher.delete');

        Route::get('/commision-voucher/print/{id}', 'print')->middleware(['permission:print jv'])->name('commision.voucher.print');




    });

});
