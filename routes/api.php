<?php

use App\Data\UserData;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:api-tokens');

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/tokens/current', [TokenController::class, 'destroy']);

    Route::get('/me', fn (Request $request) => UserData::fromModel($request->user()));

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');

    Route::get('/tickets/{ticket}/messages', [MessageController::class, 'index'])->whereNumber('ticket');
    Route::post('/tickets/{ticket}/messages', [MessageController::class, 'store'])->whereNumber('ticket');
});
