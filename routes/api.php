<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\V1\Mobile\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\MobileCrmController;
use App\Http\Controllers\Api\V1\Mobile\MobileDashboardController;
use App\Http\Controllers\Api\V1\Mobile\MobileProjectController;


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
    });
});



Route::get('/test', function () {
    return "API is working!";
});
