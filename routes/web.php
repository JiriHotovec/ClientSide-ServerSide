<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManageController;
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

    Route::prefix('manage')->name('manage.')->group(function () {
        Route::get('/', [ManageController::class, 'overview'])->name('overview');
        Route::get('/devices', [ManageController::class, 'devices'])->name('devices');
        Route::get('/devices/create', [ManageController::class, 'createDevice'])->name('devices.create');
        Route::get('/devices/{device}/edit', [ManageController::class, 'editDevice'])->name('devices.edit');
        Route::get('/types', [ManageController::class, 'types'])->name('types');
        Route::get('/types/create', [ManageController::class, 'createType'])->name('types.create');
        Route::get('/types/{measurementType}/edit', [ManageController::class, 'editType'])->name('types.edit');
    });
});
