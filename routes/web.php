<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\TenantHomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Foundation', [
        'phase' => config('cyberaware.phase'),
        'modules' => config('cyberaware.modules'),
    ]);
});

Route::middleware('guest')->group(function () {
    Route::get('/invitations/{organisation}/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{organisation}/{token}', [InvitationController::class, 'store'])->middleware('throttle:5,1')->name('invitations.accept');
});

Route::middleware(['auth', 'verified', 'tenant'])->prefix('/org/{organisation}')->group(function () {
    Route::get('/', TenantHomeController::class)->name('tenant.home');
    Route::get('/members', [MembersController::class, 'index'])->middleware('permission:members.manage')->name('tenant.members');
    Route::post('/invitations', [InvitationController::class, 'create'])->middleware('permission:members.manage')->name('tenant.invitations.create');
});
