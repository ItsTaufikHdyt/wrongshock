<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;

// API routes for fetching districts and sub-districts
Route::get('/api/districts', [RegisterController::class, 'district']);
Route::get('/api/subdistricts', [RegisterController::class, 'subDistrict']);

Route::get('/', function () {
    return view('index');
});

Route::get('/register', function () {
    return view('register');
});

Route::post('/storeRegister', [RegisterController::class, 'register'])->name('user.register');