<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin SPA catch-all — serve the React shell for every /admin/* path.
Route::get('/admin/{any?}', fn () => view('admin.app'))->where('any', '.*');
