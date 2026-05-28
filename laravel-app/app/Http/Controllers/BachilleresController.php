<?php

namespace App\Http\Controllers;

use App\Models\Student;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class BachilleresController extends Controller
{
    private function defaultStudentPassword(): string
    {
        return (string) env('DEFAULT_STUDENT_PASSWORD', 'alumno123');
    }

    private function generateQrSvg(string $payload): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        return $writer->writeString($payload);
    }

    private function qrData(string $studentCode): array
    {
        $qrPayload = url('/qr/' . $studentCode);

        return [
            'qrPayload' => $qrPayload,
            'qrSvg' => $this->generateQrSvg($qrPayload),
        ];
    }

    private function ensureCoreAccounts(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasTable('scanners')) {
            return;
        }

        $now = now()->toDateTimeString();

        $adminUsername = (string) env('ADMIN_USERNAME', 'admin');
        $adminPassword = (string) env('ADMIN_PASSWORD', 'admin123');
        $admin = DB::table('admins')->whereRaw('LOWER(username) = LOWER(?)', [$adminUsername])->first();
        if (! $admin) {
            DB::table('admins')->insert([
                'username' => $adminUsername,
                'password_hash' => Hash::make($adminPassword),
                'created_at' => $now,
            ]);
        } elseif (! Hash::check($adminPassword, $admin->password_hash)) {
            DB::table('admins')->where('id', $admin->id)->update([
                'password_hash' => Hash::make($adminPassword),
            ]);
        }

        $scannerUsername = (string) env('SCANNER_USERNAME', 'scanner');
        $scannerPassword = (string) env('SCANNER_PASSWORD', 'scanner123');
        $scanner = DB::table('scanners')->whereRaw('LOWER(username) = LOWER(?)', [$scannerUsername])->first();
        if (! $scanner) {
            DB::table('scanners')->insert([
                'username' => $scannerUsername,
                'password_hash' => Hash::make($scannerPassword),
                'created_at' => $now,
            ]);
        } elseif (! Hash::check($scannerPassword, $scanner->password_hash)) {
            DB::table('scanners')->where('id', $scanner->id)->update([
                'password_hash' => Hash::make($scannerPassword),
            ]);
        }
    }

    private function studentLoginQuery(string $username)
    {
        return DB::table('students')
            ->where(function ($query) use ($username) {
                $query->whereRaw('LOWER(student_code) = LOWER(?)', [$username]);
                if (Schema::hasColumn('students', 'account_number')) {
                    $query->orWhereRaw('LOWER(account_number) = LOWER(?)', [$username]);
                }
            });
    }

    private function requireStudent()
    {
        if (session('role') !== 'student' || ! session('student_id')) {
            return redirect()->route('student.login');
        }

        return null;
    }

    private function requireAdmin()
    {
        if (session('role') !== 'admin' || ! session('admin_id')) {
            return redirect()->route('admin.login');
        }

        return null;
    }

    private function requireAdminOrScanner()
    {
        if (session('role') === 'admin' && session('admin_id')) {
            return null;
        }

        if (session('role') === 'scanner' && session('scanner_id')) {
            return null;
        }

        return redirect()->route('menu');
    }

    private function extractStudentCode(string $rawValue): string
    {
        $text = trim($rawValue);
        if ($text === '') {
            return '';
        }

        if (str_contains($text, '://')) {
            $parsed = parse_url($text);
            if (is_array($parsed)) {
                if (! empty($parsed['query'])) {
                    parse_str($parsed['query'], $query);
                    foreach (['code', 'student_code', 'alumno', 'id'] as $key) {
                        if (! empty($query[$key])) {
                            return strtoupper(trim((string) $query[$key]));
                        }
                    }
                }

                if (! empty($parsed['path'])) {
                    $segments = array_values(array_filter(explode('/', trim($parsed['path'], '/'))));
                    $candidate = strtoupper(trim((string) end($segments)));
                    if ($candidate !== '' && ! in_array($candidate, ['SCAN', 'CHECKIN', 'ALUMNO'], true)) {
                        return $candidate;
                    }
                }
            }
        }

        if (preg_match('/(?:ALUMNO|STUDENT(?:_CODE)?|CODIGO|CODE)\s*[:=\-]\s*([A-Z0-9\-_.]+)/i', $text, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        if (preg_match('/[A-Z0-9][A-Z0-9\-_.]{1,}/i', $text, $matches)) {
            return strtoupper(trim($matches[0]));
        }

        return '';
    }

    private function splitClassroomSection(string $classroom): array
    {
        $text = strtoupper(trim($classroom));
        if ($text === '') {
            return ['', ''];
        }

        $knownShifts = [
            'MATUTINO',
            'VESPERTINO',
            'NOCTURNO',
            'MIXTO',
            'INTERMEDIO',
            'TURNO MATUTINO',
            'TURNO VESPERTINO',
            'TURNO NOCTURNO',
        ];

        $normalized = preg_replace('/\s+/', ' ', $text) ?? $text;
        if (str_contains($normalized, ' - ')) {
            return array_map('trim', explode(' - ', $normalized, 2));
        }

        if (str_contains($normalized, ' / ')) {
            return array_map('trim', explode(' / ', $normalized, 2));
        }

        $parts = explode(' ', $normalized);
        if (count($parts) >= 2) {
            foreach ([2, 1] as $size) {
                $suffix = trim(implode(' ', array_slice($parts, -$size)));
                if (in_array($suffix, $knownShifts, true)) {
                    return [trim(implode(' ', array_slice($parts, 0, -$size))), $suffix];
                }
            }
        }

        return [$normalized, ''];
    }

    public function home()
    {
        return redirect()->route('menu');
    }

    public function menu(Request $request)
    {
        $this->ensureCoreAccounts();
        // Enforce concurrent sessions limit (20 active sessions within a recent window)
        if (Schema::hasTable('active_sessions')) {
            $activeWindow = now()->subMinutes(40);
            $activeCount = DB::table('active_sessions')->where('last_activity', '>=', $activeWindow)->count();
            if ($activeCount >= 20) {
                $error = 'Demasiadas sesiones activas en el sistema. Intenta nuevamente más tarde.';
                return view('home', compact('error'));
            }
        }
        if (session('role') === 'admin' && session('admin_id')) {
            return redirect()->route('admin.dashboard');
        }

        if (session('role') === 'student' && session('student_id')) {
            return redirect()->route('student.dashboard');
        }

        if (session('role') === 'scanner' && session('scanner_id')) {
            return redirect()->route('scan');
        }

        $error = null;

        if ($request->isMethod('post')) {
            $username = trim((string) $request->input('username', ''));
            $password = (string) $request->input('password', '');

            if ($username === '' || $password === '') {
                $error = 'Ingresa usuario y contrasena.';
            } else {
                $admin = DB::table('admins')->whereRaw('LOWER(username) = LOWER(?)', [$username])->first();
                if ($admin && Hash::check($password, $admin->password_hash)) {
                    session()->flush();
                    session([
                        'role' => 'admin',
                        'admin_id' => $admin->id,
                        'admin_username' => $admin->username,
                    ]);

                    // ensure active_sessions row exists for this session
                    if (Schema::hasTable('active_sessions')) {
                        DB::table('active_sessions')->updateOrInsert(
                            ['session_id' => $request->getSession()->getId()],
                            [
                                'role' => 'admin',
                                'user_id' => $admin->id,
                                'ip_address' => $request->ip(),
                                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                                'last_activity' => now(),
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }

                    return redirect()->route('admin.dashboard');
                }

                $student = $this->studentLoginQuery($username)->first();
                if ($student && ! empty($student->password) && Hash::check($password, $student->password)) {
                    session()->flush();
                    session([
                        'role' => 'student',
                        'student_id' => $student->id,
                    ]);

                    if (Schema::hasTable('active_sessions')) {
                        DB::table('active_sessions')->updateOrInsert(
                            ['session_id' => $request->getSession()->getId()],
                            [
                                'role' => 'student',
                                'user_id' => $student->id,
                                'ip_address' => $request->ip(),
                                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                                'last_activity' => now(),
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }

                    return redirect()->route('student.dashboard');
                }

                $scanner = DB::table('scanners')->whereRaw('LOWER(username) = LOWER(?)', [$username])->first();
                if ($scanner && Hash::check($password, $scanner->password_hash)) {
                    session()->flush();
                    session([
                        'role' => 'scanner',
                        'scanner_id' => $scanner->id,
                        'scanner_username' => $scanner->username,
                    ]);

                    if (Schema::hasTable('active_sessions')) {
                        DB::table('active_sessions')->updateOrInsert(
                            ['session_id' => $request->getSession()->getId()],
                            [
                                'role' => 'scanner',
                                'user_id' => $scanner->id,
                                'ip_address' => $request->ip(),
                                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                                'last_activity' => now(),
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }

                    return redirect()->route('scan');
                }

                $error = 'Usuario o contrasena incorrectos.';
            }
        }

        return view('home', compact('error'));
    }

    public function adminLogin()
    {
        return redirect()->route('menu');
    }

    public function studentLogin()
    {
        return redirect()->route('menu');
    }

    public function scannerLogin()
    {
        return redirect()->route('menu');
    }

    public function adminLogout()
    {
        if (Schema::hasTable('active_sessions')) {
            DB::table('active_sessions')->where('session_id', session()->getId())->delete();
        }
        session()->flush();
        return redirect()->route('menu');
    }

    public function studentLogout()
    {
        if (Schema::hasTable('active_sessions')) {
            DB::table('active_sessions')->where('session_id', session()->getId())->delete();
        }
        session()->flush();
        return redirect()->route('menu');
    }

    public function scannerLogout()
    {
        if (Schema::hasTable('active_sessions')) {
            DB::table('active_sessions')->where('session_id', session()->getId())->delete();
        }
        session()->flush();
        return redirect()->route('menu');
    }

    public function studentRegister(Request $request)
    {
        // Public registration is disabled in this deployment; redirect to menu.
        return redirect()->route('menu');
    }

    public function studentHome()
    {
        if (session('role') === 'admin' && session('admin_id')) {
            return redirect()->route('admin.dashboard');
        }

        if (session('role') === 'student' && session('student_id')) {
            return redirect()->route('student.dashboard');
        }

        return redirect()->route('menu');
    }

    public function studentDashboard(Request $request)
    {
        $access = $this->requireStudent();
        if ($access) {
            return $access;
        }

        $student = Student::find(session('student_id'));
        if (! $student) {
            session()->flush();
            return redirect()->route('menu');
        }

        $error = session('error') ?? null;
        $success = session('success') ?? null;

        if ($request->isMethod('post')) {
            $password = (string) $request->input('password', '');
            $confirmPassword = (string) $request->input('confirm_password', '');

            if ($password !== '' && $password !== $confirmPassword) {
                $error = 'Las contrasenas no coinciden.';
                session()->flash('error', $error);
                return redirect()->route('student.dashboard');
            }

            if ($password !== '') {
                $student->password = Hash::make($password);
                $student->save();

                // Persist that the password is no longer the default
                if (Schema::hasColumn('students', 'default_password')) {
                    DB::table('students')->where('id', $student->id)->update(['default_password' => false]);
                }

                session()->flash('success', 'Contraseña actualizada correctamente.');
                return redirect()->route('student.dashboard');
            }

            session()->flash('success', 'No se realizaron cambios.');
            return redirect()->route('student.dashboard');
        }

        // Prefer explicit flag if present in DB, otherwise fall back to hash comparison.
        $passwordNeedsUpdate = false;
        if (Schema::hasColumn('students', 'default_password')) {
            $passwordNeedsUpdate = (bool) $student->default_password;
        } else {
            if (! empty($student->password)) {
                $passwordNeedsUpdate = Hash::check($this->defaultStudentPassword(), $student->password);
            }
        }

        // Note: POST handling uses PRG and persists the flag in the DB, so here we just read the persisted state.

        return view('student.dashboard', array_merge([
            'student' => $student,
            'error' => $error,
            'success' => $success,
            'passwordNeedsUpdate' => $passwordNeedsUpdate,
        ], $this->qrData($student->student_code)));
    }

    public function studentProfile()
    {
        return redirect()->route('student.dashboard');
    }

    public function studentProfileUpdate()
    {
        return redirect()->route('student.dashboard');
    }

    public function studentChangePassword()
    {
        return redirect()->route('student.dashboard');
    }

    public function studentChangePasswordUpdate(Request $request)
    {
        return $this->studentDashboard($request);
    }

    public function adminDashboard(Request $request)
    {
        $access = $this->requireAdmin();
        if ($access) {
            return $access;
        }

        $this->ensureCoreAccounts();

        $studentCount = Student::count();
        $attendanceCount = DB::table('attendances')->count();
        $recentStudents = Student::orderByDesc('id')->limit(5)->get(['id', 'full_name', 'student_code', 'classroom']);

        $resetError = null;
        $resetSuccess = null;

        if ($request->isMethod('post') && $request->input('action') === 'reset_password') {
            $targetUser = strtoupper(trim((string) $request->input('recovery_user', '')));
            $newPassword = (string) $request->input('recovery_password', '');
            $confirmPassword = (string) $request->input('recovery_confirm', '');

            if ($targetUser === '' || $newPassword === '' || $confirmPassword === '') {
                $resetError = 'Completa todos los campos para restablecer la contraseña.';
            } elseif ($newPassword !== $confirmPassword) {
                $resetError = 'Las contrasenas no coinciden.';
            } else {
                $admin = DB::table('admins')->whereRaw('UPPER(username) = ?', [$targetUser])->first();
                $student = null;

                if (! $admin) {
                    $student = DB::table('students')->whereRaw('UPPER(student_code) = ?', [$targetUser])->first();
                    if (! $student && Schema::hasColumn('students', 'account_number')) {
                        $student = DB::table('students')->whereRaw('UPPER(account_number) = ?', [$targetUser])->first();
                    }
                }

                if ($admin) {
                    DB::table('admins')->where('id', $admin->id)->update([
                        'password_hash' => Hash::make($newPassword),
                    ]);
                    $resetSuccess = 'Contraseña de administrador restablecida correctamente.';
                } elseif ($student) {
                    DB::table('students')->where('id', $student->id)->update([
                        'password' => Hash::make($newPassword),
                    ]);
                    $resetSuccess = 'Contraseña de alumno restablecida correctamente.';
                } else {
                    $resetError = 'No se encontró ningún usuario con ese nombre o número de cuenta.';
                }
            }
        }

        return view('admin.dashboard', compact('studentCount', 'attendanceCount', 'recentStudents', 'resetError', 'resetSuccess'));
    }

    public function studentsIndex()
    {
        $access = $this->requireAdmin();
        if ($access) {
            return $access;
        }

        $students = Student::orderBy('full_name')->get();

        return view('students.index', compact('students'));
    }

    public function studentsStore(Request $request)
    {
        $access = $this->requireAdmin();
        if ($access) {
            return $access;
        }
        // Limit concurrent registrations (admin creating students) to 20 active processes
        if (Schema::hasTable('active_registrations')) {
            $recentRegs = DB::table('active_registrations')->where('created_at', '>=', now()->subMinutes(10))->count();
            if ($recentRegs >= 20) {
                return redirect()->route('students.index')->with('error', 'Demasiadas inscripciones en curso. Intenta más tarde.');
            }

            $regId = DB::table('active_registrations')->insertGetId([
                'session_id' => $request->getSession()->getId(),
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $regId = null;
        }

        // Only ask for account_number, classroom and an optional shift (turno).
        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'classroom' => ['required', 'string', 'max:255'],
            'shift' => ['nullable', 'string', 'max:255'],
        ];

        // If the DB has an account_number column, ensure uniqueness
        if (Schema::hasColumn('students', 'account_number')) {
            $rules['account_number'][] = 'unique:students,account_number';
        }

        $data = $request->validate($rules);


        $accountNumber = strtoupper(trim($data['account_number']));
        $group = strtoupper(trim($data['classroom']));
        $shift = strtoupper(trim((string) ($data['shift'] ?? '')));

        $classroom = $group;
        if ($shift !== '') {
            $classroom .= ' - ' . $shift;
        }

        // Derive a student code from the account number when possible; otherwise generate one.
        $studentCode = $accountNumber !== '' ? $accountNumber : ('S' . time() . substr(uniqid(), -4));
        $baseCode = $studentCode;
        $suffix = 1;
        while (DB::table('students')->whereRaw('UPPER(student_code) = ?', [strtoupper($studentCode)])->exists()) {
            $studentCode = $baseCode . '-' . $suffix++;
        }

        $accountToStore = Schema::hasColumn('students', 'account_number') ? $accountNumber : null;
        $fullName = trim($data['full_name']);

        $student = Student::create([
            'full_name' => $fullName,
            'student_code' => $studentCode,
            'account_number' => $accountToStore,
            'classroom' => $classroom,
            'password' => Hash::make($this->defaultStudentPassword()),
            'default_password' => true,
        ]);

        if ($regId) {
            DB::table('active_registrations')->where('id', $regId)->delete();
        }
        return redirect()->route('students.index')->with('default_pw', [
            'code' => $student->student_code,
            'password' => $this->defaultStudentPassword(),
        ]);
    }

    public function studentsDestroy(Student $student)
    {
        $access = $this->requireAdmin();
        if ($access) {
            return $access;
        }

        $student->delete();

        return redirect()->route('students.index');
    }

    public function studentsQr(Student $student)
    {
        $access = $this->requireAdmin();
        if ($access) {
            return $access;
        }

        return view('student.qr', array_merge([
            'student' => $student,
        ], $this->qrData($student->student_code)));
    }

    public function studentsDetails(Request $request, Student $student)
    {
        $access = $this->requireAdmin();
        if ($access) {
            return $access;
        }

        $error = null;
        $success = null;

        if ($request->isMethod('post')) {
            $request->validate([
                'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            ]);

            $photo = $request->file('photo');
            if (! $photo) {
                $error = 'Selecciona una fotografia valida.';
            } else {
                $targetDirectory = public_path('uploads/students');
                File::ensureDirectoryExists($targetDirectory);

                $extension = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
                $storedFilename = 'student_' . $student->id . '.' . $extension;
                $photo->move($targetDirectory, $storedFilename);

                $student->photo_filename = $storedFilename;
                $student->save();
                $student = $student->fresh();
                $success = 'Fotografia actualizada correctamente.';
            }
        }

        // The attendances table in this SQLite instance stores student_code/student_name and timestamps
        $attendanceCount = DB::table('attendances')->where('student_code', $student->student_code)->count();
        $recentAttendance = DB::table('attendances')
            ->where('student_code', $student->student_code)
            ->orderByDesc('date')
            ->orderByDesc('checked_at')
            ->limit(5)
            ->get(['date as attendance_date', 'checked_at as attendance_time', 'source']);

        return view('student.details', array_merge([
            'student' => $student,
            'attendanceCount' => $attendanceCount,
            'recentAttendance' => $recentAttendance,
            'error' => $error,
            'success' => $success,
        ], $this->qrData($student->student_code)));
    }

    public function studentPass(string $student_code)
    {
        $student = Student::whereRaw('LOWER(student_code) = LOWER(?)', [trim($student_code)])->first();

        if (! $student && Schema::hasColumn('students', 'account_number')) {
            $student = Student::whereRaw('LOWER(account_number) = LOWER(?)', [trim($student_code)])->first();
        }

        if (! $student) {
            abort(404, 'Alumno no encontrado');
        }

        return view('student.qr', array_merge([
            'student' => $student,
        ], $this->qrData($student->student_code)));
    }

    public function scan()
    {
        $access = $this->requireAdminOrScanner();
        if ($access) {
            return $access;
        }

        return view('scan');
    }

    public function checkin(Request $request)
    {
        $access = $this->requireAdminOrScanner();
        if ($access) {
            return $access;
        }

        $rawPayload = (string) ($request->json('student_code') ?? $request->input('student_code', ''));
        $studentCode = $this->extractStudentCode($rawPayload);
        $source = strtolower(trim((string) ($request->json('source') ?? $request->input('source', 'qr')))) ?: 'qr';

        if ($studentCode === '') {
            return response()->json(['ok' => false, 'message' => 'Codigo vacio'], 400);
        }

        $student = Student::whereRaw('LOWER(student_code) = LOWER(?)', [$studentCode])->first();
        if (! $student && Schema::hasColumn('students', 'account_number')) {
            $student = Student::whereRaw('LOWER(account_number) = LOWER(?)', [$studentCode])->first();
        }

        if (! $student) {
            return response()->json(['ok' => false, 'message' => 'Alumno no registrado'], 404);
        }

        $today = now()->toDateString();
        $nowTime = now()->format('H:i:s');

        $alreadyExists = DB::table('attendances')
            ->where('student_code', $student->student_code)
            ->where('date', $today)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'ok' => false,
                'message' => 'Asistencia ya registrada hoy',
                'student' => $student->full_name,
                'classroom' => $student->classroom,
                'date' => $today,
            ], 409);
        }

        DB::table('attendances')->insert([
            'student_code' => $student->student_code,
            'student_name' => $student->full_name,
            'classroom' => $student->classroom,
            'date' => $today,
            'checked_at' => now()->toDateTimeString(),
            'source' => $source,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Asistencia registrada',
            'student' => $student->full_name,
            'classroom' => $student->classroom,
            'time' => $nowTime,
            'date' => $today,
        ]);
    }

    public function attendance(Request $request)
    {
        $access = $this->requireAdminOrScanner();
        if ($access) {
            return $access;
        }

        $selectedDate = (string) $request->query('date', now()->toDateString());

        // The attendances table stores student_code and checked_at/date fields; join on student_code
        $rows = DB::table('attendances as a')
            ->join('students as s', 's.student_code', '=', 'a.student_code')
            ->where('a.date', $selectedDate)
            ->orderBy('s.classroom')
            ->orderBy('s.full_name')
            ->get([
                's.full_name',
                's.student_code',
                's.classroom',
                'a.checked_at as attendance_time',
                'a.date as attendance_date',
                'a.source',
            ]);

        $attendanceSections = [];
        $currentSection = null;
        $currentRecords = [];

        foreach ($rows as $row) {
            [$sectionGroup, $sectionShift] = $this->splitClassroomSection((string) $row->classroom);
            $sectionLabel = $sectionShift === '' ? $sectionGroup : $sectionGroup . ' - ' . $sectionShift;

            if ($currentSection !== $sectionLabel) {
                if ($currentSection !== null) {
                    $attendanceSections[] = [
                        'label' => $currentSection,
                        'records' => $currentRecords,
                    ];
                }

                $currentSection = $sectionLabel;
                $currentRecords = [];
            }

            $currentRecords[] = $row;
        }

        if ($currentSection !== null) {
            $attendanceSections[] = [
                'label' => $currentSection,
                'records' => $currentRecords,
            ];
        }

        return view('attendance', [
            'attendanceSections' => $attendanceSections,
            'selectedDate' => $selectedDate,
        ]);
    }
}
