<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GrafikController;
use App\Http\Controllers\KesehatanController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PemakaianController;
use App\Http\Controllers\PengaturanController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('ringkasan');
    Route::get('/pemakaian', [PemakaianController::class, 'index'])->name('pemakaian');
    Route::get('/pemakaian/export', [PemakaianController::class, 'export'])->name('pemakaian.export');
    Route::get('/grafik', GrafikController::class)->name('grafik');
    Route::get('/kesehatan', KesehatanController::class)->name('kesehatan');
    Route::get('/alert', [AlertController::class, 'index'])->name('alert');
    Route::post('/alert/seen-all', [AlertController::class, 'seenAll'])->name('alert.seenall');
    Route::post('/alert/{alert}/seen', [AlertController::class, 'seen'])->name('alert.seen');
    Route::get('/log', LogController::class)->name('log');
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');
    Route::post('/pengaturan/umum', [PengaturanController::class, 'umum'])->name('pengaturan.umum');
    Route::post('/pengaturan/alert', [PengaturanController::class, 'alert'])->name('pengaturan.alert');
    Route::post('/pengaturan/sandi', [PengaturanController::class, 'sandi'])->name('pengaturan.sandi')->middleware('throttle:5,1');
});
