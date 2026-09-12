<?php

use App\Http\Controllers\TicketWebController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// There is no marketing page: a signed-in visitor belongs in their tickets,
// a guest belongs at the login form. Named 'home' because Fortify's logout
// response and a couple of tests redirect here by that name.
Route::get('/', function (Request $request) {
    return redirect()->to($request->user() ? route('tickets.index') : route('login'));
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/tickets', [TicketWebController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketWebController::class, 'show'])->name('tickets.show')->whereNumber('ticket');
});

require __DIR__.'/settings.php';
