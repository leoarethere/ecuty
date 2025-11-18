<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// UBAH JADI SEPERTI INI
Route::get('/', function () {
    // Arahkan ke nama rute login panel 'admin' Anda
    return redirect()->route('filament.admin.auth.login');
});