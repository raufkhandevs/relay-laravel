<?php

use App\Data\UserData;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:api-tokens');

// Every authenticated route is throttled, not just the ones that felt expensive.
// An audit demonstrated 40 consecutive uploads and 40 consecutive downloads going
// through untouched: each upload writes to the bucket and is never reclaimed, each
// download mints a presigned URL. The cheapest identity in the system could do both
// in a shell loop.
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::delete('/tokens/current', [TokenController::class, 'destroy']);

    Route::get('/me', fn (Request $request) => UserData::fromModel($request->user()));

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');

    Route::get('/tickets/{ticket}/messages', [MessageController::class, 'index'])->whereNumber('ticket');
    // Writes get their own tighter ceiling on top of the group's. A message may carry
    // a file, so this is the one endpoint where a single request costs real storage.
    Route::post('/tickets/{ticket}/messages', [MessageController::class, 'store'])
        ->whereNumber('ticket')
        ->middleware('throttle:messages');

    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment');
});
