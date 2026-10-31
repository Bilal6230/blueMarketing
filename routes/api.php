<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\V1\Mobile\MobileAttendanceController;
use App\Http\Controllers\Api\V1\Mobile\MobileApprovalController;
use App\Http\Controllers\Api\V1\Mobile\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\MobileCrmController;
use App\Http\Controllers\Api\V1\Mobile\MobileDashboardController;
use App\Http\Controllers\Api\V1\Mobile\MobileProjectController;
use App\Http\Controllers\Api\V1\Mobile\MobileReportsController;
use App\Http\Controllers\Api\V1\Mobile\MobileStockController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/project/booking/detail', [ProjectController::class, 'bookingByid']);

Route::post('/project/booking/list', [ProjectController::class, 'bookingList']);


Route::middleware('form.token.auth')->group(function () {
    Route::post('/leads/active', [LeadController::class, 'activeLeads']);
    Route::post('/leads/summary', [LeadController::class, 'leadSummary']);
    Route::post('/project/booking/summary', [ProjectController::class, 'bookingDetails']);
});

Route::prefix('v1/mobile')->group(function () {
    Route::post('auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [MobileAuthController::class, 'me']);
        Route::post('auth/logout', [MobileAuthController::class, 'logout']);
        Route::get('projects', [MobileProjectController::class, 'index']);
        Route::get('dashboard', [MobileDashboardController::class, 'index']);

        Route::prefix('crm')->group(function () {
            Route::get('summary', [MobileCrmController::class, 'summary']);
            Route::get('leads', [MobileCrmController::class, 'index']);
            Route::post('leads', [MobileCrmController::class, 'store']);
            Route::get('leads/{lead}', [MobileCrmController::class, 'show']);
            Route::put('leads/{lead}', [MobileCrmController::class, 'update']);
            Route::post('leads/{lead}/follow-up', [MobileCrmController::class, 'followUp']);
            Route::get('leads/{lead}/history', [MobileCrmController::class, 'history']);
            Route::post('leads/{lead}/assign', [MobileCrmController::class, 'assign']);
        });

        Route::prefix('attendance')->group(function () {
            Route::get('staff/today', [MobileAttendanceController::class, 'staffToday']);
            Route::post('staff/check-in', [MobileAttendanceController::class, 'staffCheckIn']);
            Route::post('staff/check-out', [MobileAttendanceController::class, 'staffCheckOut']);
            Route::get('staff/history', [MobileAttendanceController::class, 'staffHistory']);

            Route::get('labour', [MobileAttendanceController::class, 'labourIndex']);
            Route::post('labour/mark', [MobileAttendanceController::class, 'markLabour']);
            Route::get('labour/report', [MobileAttendanceController::class, 'labourReport']);
            Route::get('labour/payment-summary', [MobileAttendanceController::class, 'labourPaymentSummary']);
        });

        Route::prefix('stock')->group(function () {
            Route::get('meta', [MobileStockController::class, 'meta']);
            Route::get('items', [MobileStockController::class, 'items']);
            Route::get('parties', [MobileStockController::class, 'parties']);
            Route::get('entries', [MobileStockController::class, 'entries']);
            Route::get('entries/{entry}', [MobileStockController::class, 'entryDetail'])
                ->whereNumber('entry');
            Route::post('entries/in', [MobileStockController::class, 'storeIn']);
            Route::post('entries/out', [MobileStockController::class, 'storeOut']);
            Route::get('report', [MobileStockController::class, 'report']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('inventory', [MobileReportsController::class, 'inventory']);
            Route::get('projects', [MobileReportsController::class, 'projects']);
            Route::get('projects/{project}', [MobileReportsController::class, 'projectDetail'])
                ->whereNumber('project');
            Route::get('projects/{project}/plots', [MobileReportsController::class, 'projectPlots'])
                ->whereNumber('project');
            Route::get('projects/{project}/bookings', [MobileReportsController::class, 'projectBookings'])
                ->whereNumber('project');
            Route::get('projects/{project}/recovery', [MobileReportsController::class, 'projectRecovery'])
                ->whereNumber('project');
            Route::get('projects/{project}/customers', [MobileReportsController::class, 'projectCustomers'])
                ->whereNumber('project');
        });

        Route::prefix('approvals')->group(function () {
            Route::get('summary', [MobileApprovalController::class, 'summary']);
            Route::get('/', [MobileApprovalController::class, 'index']);
            Route::get('{approval}/preview', [MobileApprovalController::class, 'preview'])
                ->whereNumber('approval');
            Route::post('{approval}/approve', [MobileApprovalController::class, 'approve'])
                ->whereNumber('approval');
            Route::post('{approval}/reject', [MobileApprovalController::class, 'reject'])
                ->whereNumber('approval');
            Route::get('{approval}', [MobileApprovalController::class, 'show'])
                ->whereNumber('approval');
        });
    });
});



Route::get('/test', function () {
    return "API is working!";
});

/*
|--------------------------------------------------------------------------
| V2 finance pilot endpoints — DISABLED BY DEFAULT
|--------------------------------------------------------------------------
| No V1 route is replaced. Routes only register on local/testing when the
| operator explicitly opts in. Each request independently requires Sanctum,
| a finance Gate, project membership and the V2 isolated DB connection guard.
| Posting additionally requires a separate OFF-by-default write flag.
*/
if (in_array(config('app.env'), ['local', 'testing'], true)
    && config('finance.v2_pilot_api_enabled', false)) {
    Route::middleware(['auth:sanctum', 'throttle:30,1'])
        ->prefix('v2/finance/projects/{projectId}')
        ->group(function (): void {
            $controller = \App\Http\Controllers\Api\V2\V2FinancePilotController::class;
            Route::get('postings', [$controller, 'lines'])->whereNumber('projectId');
            Route::get('sources/{kind}/{sourceId}/readiness', [$controller, 'sourceReadiness'])
                ->whereNumber('projectId')->whereNumber('sourceId')
                ->where('kind', 'journal|sales|cash|customer|commission|booking|labour|stock_entry|stock_bill');
            Route::get('voucher-register', [$controller, 'voucherRegister'])
                ->whereNumber('projectId');
            Route::get('summary', [$controller, 'summary'])->whereNumber('projectId');
            Route::get('trial-balance', [$controller, 'trialBalance'])->whereNumber('projectId');
            Route::get('trial-balance/export', [$controller, 'exportTrialBalance'])
                ->whereNumber('projectId');
            Route::get('accounts/{accountId}/statement/export', [$controller, 'exportAccountStatement'])
                ->whereNumber('projectId')->whereNumber('accountId');
            Route::get('accounts/{accountId}/statement', [$controller, 'accountStatement'])
                ->whereNumber('projectId')->whereNumber('accountId');
            Route::post('postings', [$controller, 'post'])->whereNumber('projectId');
            Route::post('reversals', [$controller, 'reverse'])->whereNumber('projectId');
        });
}
