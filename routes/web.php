<?php

use App\Http\Controllers\TicketWebController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('/tickets', [TicketWebController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketWebController::class, 'show'])->name('tickets.show')->whereNumber('ticket');
});

require __DIR__.'/settings.php';
