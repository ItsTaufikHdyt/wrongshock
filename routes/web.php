<?php

use App\Filament\UserPanel\Pages\UserDashboard;
use App\Http\Controllers\RegisterController;
use App\Models\WasteItem;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// API routes for fetching districts and sub-districts
Route::get('/api/districts', [RegisterController::class, 'district']);
Route::get('/api/subdistricts', [RegisterController::class, 'subDistrict']);

Route::get('/', function () {
    $user = request()->user();
    $userPanel = Filament::getPanel('userPanel');

    return view('index', [
        'wasteItems' => Schema::hasTable('waste_items')
            ? WasteItem::query()
                ->select(['id', 'category', 'output', 'unit', 'price'])
                ->orderBy('category')
                ->orderBy('id')
                ->limit(8)
                ->get()
            : collect(),
        'loginUrl' => $userPanel->getLoginUrl(),
        'registrationUrl' => Route::has('register') ? route('register') : null,
        'memberDashboardUrl' => $user?->canAccessPanel($userPanel)
            ? UserDashboard::getUrl(panel: 'userPanel')
            : null,
    ]);
})->name('home');

Route::get('/register', function () {
    return view('register');
})->name('register');

Route::post('/storeRegister', [RegisterController::class, 'register'])
    ->middleware('throttle:6,1')
    ->name('user.register');
