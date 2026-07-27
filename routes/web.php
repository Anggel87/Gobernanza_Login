<?php

use App\Http\Controllers\GovernanceAuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('governance')->name('governance.')->group(function () {
    Route::get('/auth', [GovernanceAuthController::class, 'show'])->name('auth.show');
    Route::post('/auth/login', [GovernanceAuthController::class, 'login'])->name('auth.login');
});

require __DIR__.'/auth.php';
