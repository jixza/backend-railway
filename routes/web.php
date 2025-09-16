<?php

use App\Http\Controllers\Web\PatientController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patient.show');
// DISABLED: Route ini tidak aman - bisa diakses dengan patient ID langsung
// Gunakan /patient/token/{token} melalui API untuk akses yang aman
