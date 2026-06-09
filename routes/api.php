<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\V1\Mobile\MobileAttendanceController;
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
    });
});



Route::get('/test', function () {
    return "API is working!";
});
