<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Foundation', [
        'phase' => config('cyberaware.phase'),
        'modules' => config('cyberaware.modules'),
    ]);
});
