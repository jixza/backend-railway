<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\TemporaryPatientToken;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TemporaryPatientTokenController extends Controller
{
    use \App\ApiResponse;

    protected $patientService;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }

    /**
     * Generate temporary token untuk patient
     */
    public function generateToken(Request $request, $patientId)
    {
        try {
            $user = Auth::user();
            
            // Validasi apakah patient ada
            $patient = Patient::find($patientId);
            if (!$patient) {
                return $this->errorResponse('Patient not found', 404, 404);
            }

            // Validasi request
            $validated = $request->validate([
                'expiration_minutes' => 'integer|min:5|max:1440', // 5 menit - 24 jam
            ]);

            $expirationMinutes = $validated['expiration_minutes'] ?? 60; // default 1 jam

            // Revoke token lama yang masih aktif untuk patient ini
            TemporaryPatientToken::revokeAllForPatient($patientId);

            // Buat token baru
            $token = TemporaryPatientToken::createForPatient(
                $patientId,
                $user->id,
                $expirationMinutes,
                $request->ip(),
                $request->userAgent()
            );

            // Generate URL untuk QR Code
            $baseUrl = rtrim(config('app.url'), '/');
            $qrUrl = $baseUrl . "/api/patient/token/{$token->token}";

            Log::info('Temporary token generated', [
                'patient_id' => $patientId,
                'token_id' => $token->id,
                'created_by' => $user->id,
                'expires_at' => $token->expires_at,
                'ip' => $request->ip()
            ]);

            return $this->successResponse('Temporary token generated successfully', [
                'token' => $token->token,
                'expires_at' => $token->expires_at,
                'expiration_minutes' => $expirationMinutes,
                'qr_url' => $qrUrl,
                'patient' => [
                    'id' => $patient->id,
                    'patient_id' => $patient->patient_id,
                    'full_name' => $patient->full_name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating temporary token', [
                'patient_id' => $patientId,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to generate token', 500, 500);
        }
    }

    /**
     * Access patient data menggunakan temporary token
     */
    public function accessPatientByToken($token)
    {
        try {
            Log::info('=== QR ACCESS START ===', [
                'token' => substr($token, 0, 10) . '...',
                'ip' => request()->ip()
            ]);

            // Step 1: Find valid token WITH patient relationship
            $tokenRecord = TemporaryPatientToken::with('patient')
                ->where('token', $token)
                ->where('is_used', false)
                ->where('expires_at', '>', now())
                ->first();
            
            Log::info('Token lookup result:', [
                'token_found' => $tokenRecord ? 'YES' : 'NO',
                'token_id' => $tokenRecord ? $tokenRecord->id : null,
                'patient_id_in_token' => $tokenRecord ? $tokenRecord->patient_id : null,
                'patient_loaded' => $tokenRecord && $tokenRecord->patient ? 'YES' : 'NO'
            ]);
            
            if (!$tokenRecord) {
                Log::warning('Invalid token accessed', ['token' => substr($token, 0, 10) . '...']);
                return response("
                <!DOCTYPE html>
                <html>
                <head><title>QR Error</title><meta charset='UTF-8'></head>
                <body style='font-family: Arial; padding: 20px; text-align: center; background: #ffe6e6;'>
                    <h1>❌ QR Code Tidak Valid</h1>
                    <p>Token sudah kedaluwarsa atau tidak valid</p>
                    <p>Silakan minta QR Code baru</p>
                    <p><small>Debug: Token not found or expired</small></p>
                </body>
                </html>", 401, ['Content-Type' => 'text/html']);
            }

            // Step 2: Get patient data - now use relationship since we fixed it
            $patient = $tokenRecord->patient;
            
            // Fallback to direct lookup if relationship still fails
            if (!$patient) {
                $patient = Patient::where('patient_id', $tokenRecord->patient_id)->first();
            }
            
            Log::info('Patient data check:', [
                'patient_exists' => $patient ? 'YES' : 'NO',
                'patient_id' => $patient ? $patient->patient_id : 'NULL',
                'patient_name' => $patient ? $patient->full_name : 'NULL',
                'lookup_method' => $tokenRecord->patient ? 'relationship' : 'direct'
            ]);
            
            if (!$patient) {
                Log::error('Patient not found with both methods', [
                    'token_patient_id' => $tokenRecord->patient_id,
                    'token_id' => $tokenRecord->id
                ]);
                
                return response("
                <!DOCTYPE html>
                <html>
                <head><title>Patient Error</title><meta charset='UTF-8'></head>
                <body style='font-family: Arial; padding: 20px; text-align: center; background: #ffe6e6;'>
                    <h1>❌ Data Pasien Tidak Ditemukan</h1>
                    <p>Terjadi kesalahan dalam mengambil data pasien</p>
                    <p><small>Debug: Patient ID {$tokenRecord->patient_id} not found with both methods</small></p>
                </body>
                </html>", 404, ['Content-Type' => 'text/html']);
            }

            // Step 3: Get patient medical records and related data
            $latestRecord = $patient->medicalRecords()->latest()->first();
            $medicalHistory = $patient->medicalRecords()
                ->with(['doctor.specialization'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($record) {
                    return [
                        'title' => 'Kunjungan Medis',
                        'doctor_name' => $record->doctor->name ?? 'Unknown',
                        'doctor_specialization' => $record->doctor->specialization->name ?? 'Unknown',
                        'date' => $record->created_at->format('d M Y'),
                        'prescription' => $record->prescription ?? 'Tidak ada resep',
                        'notes' => $record->notes ?? 'Tidak ada catatan khusus'
                    ];
                });

            // Calculate BMI and age
            $height = $latestRecord ? $latestRecord->height : null;
            $weight = $latestRecord ? $latestRecord->weight : null;
            $bmi = null;
            $bmiStatus = 'N/A';
            
            if ($height && $weight) {
                $heightM = $height / 100;
                $bmi = round($weight / ($heightM * $heightM), 2);
                
                if ($bmi < 18.5) $bmiStatus = 'Underweight';
                elseif ($bmi < 25) $bmiStatus = 'Normal';
                elseif ($bmi < 30) $bmiStatus = 'Overweight';
                else $bmiStatus = 'Obese';
            }

            $age = null;
            if ($patient->date_of_birth) {
                $age = \Carbon\Carbon::parse($patient->date_of_birth)->age;
            }

            // Get patient results and allergies
            $patientResults = $patient->patientResults()->get();
            $allergies = $patient->drugAllergies()->with('drug')->get()->map(function($allergy) {
                return [
                    'name' => $allergy->drug->name ?? 'Unknown Drug',
                    'reaction' => $allergy->reaction ?? 'Unknown reaction'
                ];
            });

            // Step 4: Return Blade view with complete patient data
            return view('patients.show', [
                'patientData' => [
                    'info' => [
                        'name' => $patient->full_name ?? 'Unknown Patient',
                        'initials' => strtoupper(substr($patient->full_name ?? 'N', 0, 1) . substr(explode(' ', $patient->full_name ?? 'A')[1] ?? 'A', 0, 1)),
                        'id' => $patient->patient_id,
                        'medical_record_number' => $patient->medical_record_number ?? '100000',
                        'national_id' => $patient->national_id ?? '1234124214',
                        'dob' => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->format('Y-m-d') : '2002-02-11',
                        'birth_place' => $patient->birth_place ?? 'Gunung Kidul',
                        'gender' => $patient->gender ?? 'male',
                        'age' => $age ?? 23,
                        'religion' => $patient->religion ?? 'Islam',
                        'occupation' => $patient->occupation ?? 'Laboran',
                        'education' => $patient->education ?? 'Strata 1',
                        'marital_status' => $patient->marital_status ?? 'married',
                        'address' => $patient->address ?? 'Bantul',
                        'phone' => $patient->phone_number ?? '089508095478'
                    ],
                    'latest_record' => $latestRecord ? [
                        'height' => $latestRecord->height ?? 180,
                        'weight' => $latestRecord->weight ?? 60,
                        'bmi' => $bmi ?? 18.52,
                        'bmi_status' => $bmiStatus,
                        'standard_blood_sugar' => $latestRecord->standard_blood_sugar ?? 50.00,
                        'fasting_blood_sugar' => $latestRecord->fasting_blood_sugar ?? 50.00,
                        'hba1c' => $latestRecord->hba1c ?? '-',
                        'irs1_variant' => 'CC'
                    ] : [
                        'height' => 180,
                        'weight' => 60,
                        'bmi' => 18.52,
                        'bmi_status' => 'Normal',
                        'standard_blood_sugar' => 50.00,
                        'fasting_blood_sugar' => 50.00,
                        'hba1c' => '-',
                        'irs1_variant' => 'CC'
                    ],
                    'medical_history' => $medicalHistory->isEmpty() ? [[
                        'title' => 'Medical Visit',
                        'doctor_name' => 'Imam Azhari',
                        'doctor_specialization' => 'Orthopedi',
                        'date' => 'Aug 15, 2025',
                        'prescription' => 'Metformin 500mg 2x sehari',
                        'notes' => 'Kontrol gula darah rutin'
                    ]] : $medicalHistory->toArray(),
                    'diagnoses' => [
                        'primary' => [
                            'name' => 'Diabetes Mellitus Type 2',
                            'description' => 'Confirmed diagnosis',
                            'code' => 'E10-E11'
                        ],
                        'other' => []
                    ],
                    'genetic_results' => [
                        [
                            'gene_name' => 'BG12',
                            'status' => 'peru diminati lanjut',
                            'variant' => 'N/A',
                            'description' => 'Genetic variant detected'
                        ]
                    ],
                    'allergies' => $allergies->isEmpty() ? [] : $allergies->toArray(),
                    'diabetes_diagnosis_date' => $patient->created_at
                ],
                'tokenInfo' => [
                    'created_at' => $tokenRecord->created_at,
                    'created_by' => $tokenRecord->createdBy->name ?? 'Unknown',
                    'expires_at' => $tokenRecord->expires_at,
                    'accessed_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in QR token access', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response("
            <!DOCTYPE html>
            <html>
            <head><title>System Error</title><meta charset='UTF-8'></head>
            <body style='font-family: Arial; padding: 20px; text-align: center; background: #ffe6e6;'>
                <h1>⚠️ Terjadi Kesalahan Sistem</h1>
                <p>Silakan coba lagi nanti atau hubungi administrator</p>
                <p><small>Error: " . $e->getMessage() . "</small></p>
            </body>
            </html>", 500, ['Content-Type' => 'text/html']);
        }
    }

    /**
     * Revoke token tertentu
     */
    public function revokeToken($token)
    {
        try {
            $user = Auth::user();
            
            $tokenRecord = TemporaryPatientToken::where('token', $token)
                ->where('created_by_user_id', $user->id)
                ->first();
            
            if (!$tokenRecord) {
                return $this->errorResponse('Token not found or you do not have permission to revoke it', 404, 404);
            }

            $tokenRecord->markAsUsed();

            Log::info('Token manually revoked', [
                'token_id' => $tokenRecord->id,
                'patient_id' => $tokenRecord->patient_id,
                'revoked_by' => $user->id
            ]);

            return $this->successResponse('Token revoked successfully');

        } catch (\Exception $e) {
            Log::error('Error revoking token', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to revoke token', 500, 500);
        }
    }

    /**
     * Lihat active tokens untuk user
     */
    public function getActiveTokens()
    {
        try {
            $user = Auth::user();
            
            $tokens = TemporaryPatientToken::with(['patient'])
                ->where('created_by_user_id', $user->id)
                ->valid()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'token' => substr($token->token, 0, 10) . '...', // Hide full token
                        'patient' => [
                            'id' => $token->patient->id,
                            'patient_id' => $token->patient->patient_id,
                            'full_name' => $token->patient->full_name
                        ],
                        'created_at' => $token->created_at,
                        'expires_at' => $token->expires_at,
                        'created_from_ip' => $token->created_from_ip
                    ];
                });

            return $this->successResponse('Active tokens retrieved successfully', $tokens);

        } catch (\Exception $e) {
            Log::error('Error getting active tokens', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to get active tokens', 500, 500);
        }
    }

    /**
     * Revoke semua active tokens untuk user
     */
    public function revokeAllTokens()
    {
        try {
            $user = Auth::user();
            
            $revokedCount = TemporaryPatientToken::where('created_by_user_id', $user->id)
                ->where('is_used', false)
                ->update(['is_used' => true, 'used_at' => now()]);

            Log::info('All tokens revoked by user', [
                'user_id' => $user->id,
                'revoked_count' => $revokedCount
            ]);

            return $this->successResponse('All tokens revoked successfully', [
                'revoked_count' => $revokedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error revoking all tokens', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to revoke all tokens', 500, 500);
        }
    }

    /**
     * Generate QR token for current user's patient data
     */
    public function generateQRToken(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get patient data for current user  
            $patient = $user->patient;
            if (!$patient) {
                return $this->errorResponse('Patient data not found for current user', 404, 404);
            }

            // Check if there's already an active token
            $existingToken = TemporaryPatientToken::where('patient_id', $patient->id)
                ->where('created_by_user_id', $user->id)
                ->where('is_used', false)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingToken) {
                // Return existing token
                $qrUrl = url('/api/patient/token/' . $existingToken->token);
                
                return $this->successResponse('QR token retrieved successfully', [
                    'qr_url' => $qrUrl,
                    'token' => $existingToken->token,
                    'expires_at' => $existingToken->expires_at,
                    'is_new' => false
                ]);
            }

            // Create new token
            $token = TemporaryPatientToken::createForPatient(
                $patient->id,
                $user->id,
                60, // 1 hour expiration
                $request->ip(),
                $request->userAgent()
            );

            $qrUrl = url('/api/patient/token/' . $token->token);

            Log::info('QR token generated for user patient', [
                'patient_id' => $patient->id,
                'token_id' => $token->id,
                'user_id' => $user->id
            ]);

            return $this->successResponse('QR token generated successfully', [
                'qr_url' => $qrUrl,
                'token' => $token->token,
                'expires_at' => $token->expires_at,
                'is_new' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating QR token', [
                'user_id' => Auth::user()->id ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to generate QR token', 500, 500);
        }
    }
}
