<?php

use Illuminate\Support\Facades\Route;

// Public landing page (rendered by the same React SPA shell as /admin/*).
Route::get('/', fn () => view('admin.app'));

// Admin SPA catch-all — serve the React shell for every /admin/* path.
Route::get('/admin/{any?}', fn () => view('admin.app'))->where('any', '.*');
