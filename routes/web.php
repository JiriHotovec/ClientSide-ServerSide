<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PageController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'welcome'])->name('welcome');
Route::get('/devices', [PageController::class, 'publicDevices'])->name('devices.index');
Route::get('/devices/{device}', [PageController::class, 'publicDevice'])->name('devices.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:'.User::ROLE_USER)->group(function () {
        Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/devices/{device}', [PageController::class, 'dashboardPanel'])->name('dashboard.panel');
    });

    Route::prefix('manage')->group(function () {
        Route::get('/devices', [PageController::class, 'manage'])->name('manage.devices');
        Route::get('/devices/list', [PageController::class, 'manageList'])->name('manage.list');
        Route::get('/devices/form/{device?}', [PageController::class, 'manageForm'])->name('manage.form');
    });
});
