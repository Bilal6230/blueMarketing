<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjectController;


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



Route::get('/test', function () {
    return "API is working!";
});
