<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\TemporaryPatientTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Debug route untuk test Railway deployment
Route::get('/debug', function () {
    return response()->json([
        'message' => 'API is working!',
        'app_url' => config('app.url'),
        'time' => now(),
        'routes_loaded' => 'success'
    ]);
});

// Health check endpoint untuk Railway cold start
Route::get('/health', function () {
    // Check database connection
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }
    
    // Check cache
    try {
        \Illuminate\Support\Facades\Cache::put('health_check', 'ok', 5);
        $cacheStatus = \Illuminate\Support\Facades\Cache::get('health_check') === 'ok' ? 'working' : 'failed';
    } catch (\Exception $e) {
        $cacheStatus = 'failed';
    }
    
    $isHealthy = $dbStatus === 'connected' && $cacheStatus === 'working';
    
    return response()->json([
        'status' => $isHealthy ? 'healthy' : 'unhealthy',
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => $dbStatus,
            'cache' => $cacheStatus,
        ],
        'uptime' => app()->hasBeenBootstrapped(),
        'environment' => app()->environment()
    ], $isHealthy ? 200 : 503);
});

// Public route for accessing patient data with temporary token
Route::get('/patient/token/{token}', [TemporaryPatientTokenController::class, 'accessPatientByToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    Route::get('/patient', [PatientController::class, 'show']);
    Route::put('/patient', [PatientController::class, 'update']);

    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{id}', [DoctorController::class, 'show']);

    Route::get('/medical-record', [MedicalRecordController::class, 'show']);

    // Temporary Patient Token Routes
    Route::prefix('patient/tokens')->group(function () {
        Route::post('/generate/{patientId}', [TemporaryPatientTokenController::class, 'generateToken']);
        Route::get('/active', [TemporaryPatientTokenController::class, 'getActiveTokens']);
        Route::delete('/revoke/{token}', [TemporaryPatientTokenController::class, 'revokeToken']);
        Route::delete('/revoke-all', [TemporaryPatientTokenController::class, 'revokeAllTokens']);
    });

    // Generate QR token for current user's patient data
    Route::post('/generate-qr-token', [TemporaryPatientTokenController::class, 'generateQRToken']);
});
