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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class BachilleresController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN
    |--------------------------------------------------------------------------
    */

    // Dejamos margen para aproximadamente 30 alumnos.
    private const MAX_ACTIVE_STUDENTS = 35;

    // Después de 45 minutos sin actividad dejamos de contar la sesión.
    private const ACTIVE_SESSION_MINUTES = 45;

    // Protección del inicio de sesión.
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_DECAY_SECONDS = 60;

    private function defaultStudentPassword(): string
    {
        return (string) env('DEFAULT_STUDENT_PASSWORD', 'alumno123');
    }

    /*
    |--------------------------------------------------------------------------
    | QR
    |--------------------------------------------------------------------------
    */

    private function generateQrSvg(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(240),
            new SvgImageBackEnd()
        );

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

    /*
    |--------------------------------------------------------------------------
    | CONTROL DE SESIONES ACTIVAS
    |--------------------------------------------------------------------------
    */

    private function cleanExpiredSessions(): void
    {
        if (!Schema::hasTable('active_sessions')) {
            return;
        }

        DB::table('active_sessions')
            ->where(
                'last_activity',
                '<',
                now()->subMinutes(self::ACTIVE_SESSION_MINUTES)
            )
            ->delete();
    }

    private function activeStudentCount(): int
    {
        if (!Schema::hasTable('active_sessions')) {
            return 0;
        }

        $this->cleanExpiredSessions();

        return DB::table('active_sessions')
            ->where('role', 'student')
            ->where(
                'last_activity',
                '>=',
                now()->subMinutes(self::ACTIVE_SESSION_MINUTES)
            )
            ->count();
    }

    private function registerActiveSession(
        Request $request,
        string $role,
        int $userId
    ): void {
        if (!Schema::hasTable('active_sessions')) {
            return;
        }

        DB::table('active_sessions')->updateOrInsert(
            [
                'session_id' => $request->session()->getId(),
            ],
            [
                'role' => $role,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => substr(
                    (string) $request->userAgent(),
                    0,
                    1000
                ),
                'last_activity' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function touchActiveSession(Request $request): void
    {
        if (!Schema::hasTable('active_sessions')) {
            return;
        }

        $role = session('role');

        $userId = match ($role) {
            'student' => session('student_id'),
            'admin' => session('admin_id'),
            'scanner' => session('scanner_id'),
            default => null,
        };

        if (!$role || !$userId) {
            return;
        }

        /*
         * Usamos updateOrInsert porque una sesión puede haber sido
         * eliminada como inactiva y posteriormente volver a utilizarse.
         */
        DB::table('active_sessions')->updateOrInsert(
            [
                'session_id' => $request->session()->getId(),
            ],
            [
                'role' => $role,
                'user_id' => (int) $userId,
                'ip_address' => $request->ip(),
                'user_agent' => substr(
                    (string) $request->userAgent(),
                    0,
                    1000
                ),
                'last_activity' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CUENTAS ADMINISTRADOR / SCANNER
    |--------------------------------------------------------------------------
    */

    private function ensureCoreAccounts(): void
    {
        if (
            !Schema::hasTable('admins') ||
            !Schema::hasTable('scanners')
        ) {
            return;
        }

        $now = now()->toDateTimeString();

        /*
         * ADMIN
         */
        $adminUsername = (string) env(
            'ADMIN_USERNAME',
            'admin'
        );

        $adminPassword = (string) env(
            'ADMIN_PASSWORD',
            'admin123'
        );

        $admin = DB::table('admins')
            ->whereRaw(
                'LOWER(username) = LOWER(?)',
                [$adminUsername]
            )
            ->first();

        if (!$admin) {
            DB::table('admins')->insert([
                'username' => $adminUsername,
                'password_hash' => Hash::make($adminPassword),
                'created_at' => $now,
            ]);
        }

        /*
         * SCANNER
         */
        $scannerUsername = (string) env(
            'SCANNER_USERNAME',
            'scanner'
        );

        $scannerPassword = (string) env(
            'SCANNER_PASSWORD',
            'scanner123'
        );

        $scanner = DB::table('scanners')
            ->whereRaw(
                'LOWER(username) = LOWER(?)',
                [$scannerUsername]
            )
            ->first();

        if (!$scanner) {
            DB::table('scanners')->insert([
                'username' => $scannerUsername,
                'password_hash' => Hash::make($scannerPassword),
                'created_at' => $now,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONSULTA LOGIN ALUMNO
    |--------------------------------------------------------------------------
    */

    private function studentLoginQuery(string $username)
    {
        $username = strtoupper(trim($username));

        return Student::query()
            ->where(function ($query) use ($username) {
                $query->whereRaw(
                    'UPPER(student_code) = ?',
                    [$username]
                );

                if (Schema::hasColumn('students', 'account_number')) {
                    $query->orWhereRaw(
                        'UPPER(account_number) = ?',
                        [$username]
                    );
                }
            });
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDACIONES DE SESIÓN
    |--------------------------------------------------------------------------
    */

    private function requireStudent()
    {
        if (
            session('role') !== 'student' ||
            !session('student_id')
        ) {
            return redirect()->route('student.login');
        }

        return null;
    }

    private function requireAdmin()
    {
        if (
            session('role') !== 'admin' ||
            !session('admin_id')
        ) {
            return redirect()->route('admin.login');
        }

        return null;
    }

    private function hasAdminOrScannerSession(): bool
    {
        $isAdmin =
            session('role') === 'admin' &&
            session('admin_id');

        $isScanner =
            session('role') === 'scanner' &&
            session('scanner_id');

        return (bool) ($isAdmin || $isScanner);
    }

    private function requireAdminOrScanner()
    {
        if ($this->hasAdminOrScannerSession()) {
            return null;
        }

        return redirect()->route('menu');
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRAER CÓDIGO QR
    |--------------------------------------------------------------------------
    */

    private function extractStudentCode(string $rawValue): string
    {
        $text = trim($rawValue);

        if ($text === '') {
            return '';
        }

        if (str_contains($text, '://')) {
            $parsed = parse_url($text);

            if (is_array($parsed)) {
                if (!empty($parsed['query'])) {
                    parse_str($parsed['query'], $query);

                    foreach (
                        ['code', 'student_code', 'alumno', 'id']
                        as $key
                    ) {
                        if (!empty($query[$key])) {
                            return strtoupper(
                                trim((string) $query[$key])
                            );
                        }
                    }
                }

                if (!empty($parsed['path'])) {
                    $segments = array_values(
                        array_filter(
                            explode(
                                '/',
                                trim($parsed['path'], '/')
                            )
                        )
                    );

                    $candidate = strtoupper(
                        trim((string) end($segments))
                    );

                    if (
                        $candidate !== '' &&
                        !in_array(
                            $candidate,
                            ['SCAN', 'CHECKIN', 'ALUMNO'],
                            true
                        )
                    ) {
                        return $candidate;
                    }
                }
            }
        }

        if (
            preg_match(
                '/(?:ALUMNO|STUDENT(?:_CODE)?|CODIGO|CODE)\s*[:=\-]\s*([A-Z0-9\-_.]+)/i',
                $text,
                $matches
            )
        ) {
            return strtoupper(trim($matches[1]));
        }

        if (
            preg_match(
                '/[A-Z0-9][A-Z0-9\-_.]{1,}/i',
                $text,
                $matches
            )
        ) {
            return strtoupper(trim($matches[0]));
        }

        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | GRUPO / TURNO
    |--------------------------------------------------------------------------
    */

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
            return array_map(
                'trim',
                explode(' - ', $normalized, 2)
            );
        }

        if (str_contains($normalized, ' / ')) {
            return array_map(
                'trim',
                explode(' / ', $normalized, 2)
            );
        }

        $parts = explode(' ', $normalized);

        if (count($parts) >= 2) {
            foreach ([2, 1] as $size) {
                $suffix = trim(
                    implode(
                        ' ',
                        array_slice($parts, -$size)
                    )
                );

                if (in_array($suffix, $knownShifts, true)) {
                    return [
                        trim(
                            implode(
                                ' ',
                                array_slice($parts, 0, -$size)
                            )
                        ),
                        $suffix,
                    ];
                }
            }
        }

        return [$normalized, ''];
    }

    private function studentGroupSortKey(string $group): array
    {
        $normalized = strtoupper(trim($group));

        if ($normalized === '') {
            return [PHP_INT_MAX, ''];
        }

        if (
            preg_match(
                '/^(\d+)/',
                $normalized,
                $matches
            )
        ) {
            return [
                (int) $matches[1],
                $normalized,
            ];
        }

        return [
            PHP_INT_MAX,
            $normalized,
        ];
    }

    private function studentShiftSortKey(string $shift): array
    {
        $normalized = strtoupper(trim($shift));

        $order = [
            'MATUTINO' => 1,
            'VESPERTINO' => 2,
            'NOCTURNO' => 3,
            'MIXTO' => 4,
        ];

        return [
            $order[$normalized] ?? 99,
            $normalized,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HOME
    |--------------------------------------------------------------------------
    */

    public function home()
    {
        return redirect()->route('menu');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN GENERAL
    |--------------------------------------------------------------------------
    */

    public function menu(Request $request)
    {
        $this->ensureCoreAccounts();
        $this->cleanExpiredSessions();

        /*
         * Si ya tiene una sesión iniciada.
         */
        if (
            session('role') === 'admin' &&
            session('admin_id')
        ) {
            $this->touchActiveSession($request);

            return redirect()->route('admin.dashboard');
        }

        if (
            session('role') === 'student' &&
            session('student_id')
        ) {
            $this->touchActiveSession($request);

            return redirect()->route('student.dashboard');
        }

        if (
            session('role') === 'scanner' &&
            session('scanner_id')
        ) {
            $this->touchActiveSession($request);

            return redirect()->route('scan');
        }

        $error = null;

        if (!$request->isMethod('post')) {
            return view('home', compact('error'));
        }

        $username = strtoupper(
            trim(
                (string) $request->input(
                    'username',
                    ''
                )
            )
        );

        $password = (string) $request->input(
            'password',
            ''
        );

        if (
            $username === '' ||
            $password === ''
        ) {
            $error = 'Ingresa usuario y contraseña.';

            return view(
                'home',
                compact('error')
            );
        }

        /*
         * Rate Limit:
         * 5 intentos fallidos por usuario/IP cada minuto.
         */
        $rateLimitKey =
            'login:' .
            sha1(
                strtolower($username) .
                '|' .
                $request->ip()
            );

        if (
            RateLimiter::tooManyAttempts(
                $rateLimitKey,
                self::MAX_LOGIN_ATTEMPTS
            )
        ) {
            $seconds = RateLimiter::availableIn(
                $rateLimitKey
            );

            $error =
                'Demasiados intentos de acceso. Intenta nuevamente en ' .
                $seconds .
                ' segundos.';

            return view(
                'home',
                compact('error')
            );
        }

        /*
         * ================================================================
         * ADMIN
         * ================================================================
         */

        $admin = DB::table('admins')
            ->whereRaw(
                'UPPER(username) = ?',
                [$username]
            )
            ->first();

        if (
            $admin &&
            !empty($admin->password_hash) &&
            Hash::check(
                $password,
                $admin->password_hash
            )
        ) {
            RateLimiter::clear($rateLimitKey);

            session()->flush();

            $request
                ->session()
                ->regenerate();

            session([
                'role' => 'admin',
                'admin_id' => $admin->id,
                'admin_username' => $admin->username,
            ]);

            $this->registerActiveSession(
                $request,
                'admin',
                (int) $admin->id
            );

            return redirect()
                ->route('admin.dashboard');
        }

        /*
         * ================================================================
         * ALUMNO
         * ================================================================
         */

        $student = $this
            ->studentLoginQuery($username)
            ->first();

        if (
            $student &&
            !empty($student->password_hash) &&
            Hash::check(
                $password,
                $student->password_hash
            )
        ) {
            /*
             * IMPORTANTE:
             * Verificamos el límite solamente después de saber que
             * las credenciales del alumno son correctas.
             */
            $activeStudents =
                $this->activeStudentCount();

            if (
                $activeStudents >=
                self::MAX_ACTIVE_STUDENTS
            ) {
                $error =
                    'En este momento hay demasiados alumnos conectados. Intenta nuevamente en unos minutos.';

                return view(
                    'home',
                    compact('error')
                );
            }

            RateLimiter::clear(
                $rateLimitKey
            );

            session()->flush();

            $request
                ->session()
                ->regenerate();

            session([
                'role' => 'student',
                'student_id' => $student->id,
            ]);

            $this->registerActiveSession(
                $request,
                'student',
                (int) $student->id
            );

            return redirect()
                ->route('student.dashboard');
        }

        /*
         * ================================================================
         * SCANNER
         * ================================================================
         */

        $scanner = DB::table('scanners')
            ->whereRaw(
                'UPPER(username) = ?',
                [$username]
            )
            ->first();

        if (
            $scanner &&
            !empty($scanner->password_hash) &&
            Hash::check(
                $password,
                $scanner->password_hash
            )
        ) {
            RateLimiter::clear(
                $rateLimitKey
            );

            session()->flush();

            $request
                ->session()
                ->regenerate();

            session([
                'role' => 'scanner',
                'scanner_id' => $scanner->id,
                'scanner_username' => $scanner->username,
            ]);

            $this->registerActiveSession(
                $request,
                'scanner',
                (int) $scanner->id
            );

            return redirect()
                ->route('scan');
        }

        /*
         * Login incorrecto.
         */
        RateLimiter::hit(
            $rateLimitKey,
            self::LOGIN_DECAY_SECONDS
        );

        $error =
            'Usuario o contraseña incorrectos.';

        return view(
            'home',
            compact('error')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REDIRECCIONES LOGIN
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function adminLogout(Request $request)
    {
        if (Schema::hasTable('active_sessions')) {
            DB::table('active_sessions')
                ->where(
                    'session_id',
                    $request->session()->getId()
                )
                ->delete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('menu');
    }

    public function studentLogout(Request $request)
    {
        if (Schema::hasTable('active_sessions')) {
            DB::table('active_sessions')
                ->where(
                    'session_id',
                    $request->session()->getId()
                )
                ->delete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('menu');
    }

    public function scannerLogout(Request $request)
    {
        if (Schema::hasTable('active_sessions')) {
            DB::table('active_sessions')
                ->where(
                    'session_id',
                    $request->session()->getId()
                )
                ->delete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('menu');
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTRO PÚBLICO DESACTIVADO
    |--------------------------------------------------------------------------
    */

    public function studentRegister(Request $request)
    {
        return redirect()->route('menu');
    }

    public function studentHome()
    {
        if (
            session('role') === 'admin' &&
            session('admin_id')
        ) {
            return redirect()
                ->route('admin.dashboard');
        }

        if (
            session('role') === 'student' &&
            session('student_id')
        ) {
            return redirect()
                ->route('student.dashboard');
        }

        return redirect()->route('menu');
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD ALUMNO
    |--------------------------------------------------------------------------
    */

    public function studentDashboard(Request $request)
    {
        $access = $this->requireStudent();

        if ($access) {
            return $access;
        }

        /*
         * Mantener la sesión marcada como activa.
         */
        $this->touchActiveSession($request);

        $student = Student::find(
            session('student_id')
        );

        if (!$student) {
            if (Schema::hasTable('active_sessions')) {
                DB::table('active_sessions')
                    ->where(
                        'session_id',
                        $request->session()->getId()
                    )
                    ->delete();
            }

            $request
                ->session()
                ->invalidate();

            return redirect()
                ->route('menu');
        }

        $error =
            session('error') ?? null;

        $success =
            session('success') ?? null;

        /*
         * Cambio de contraseña.
         */
        if ($request->isMethod('post')) {
            $password =
                (string) $request->input(
                    'password',
                    ''
                );

            $confirmPassword =
                (string) $request->input(
                    'confirm_password',
                    ''
                );

            if (
                $password !== '' &&
                $password !== $confirmPassword
            ) {
                session()->flash(
                    'error',
                    'Las contraseñas no coinciden.'
                );

                return redirect()
                    ->route('student.dashboard');
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    session()->flash(
                        'error',
                        'La contraseña debe tener al menos 6 caracteres.'
                    );

                    return redirect()
                        ->route('student.dashboard');
                }

                /*
                 * El modelo Student convierte automáticamente
                 * password → password_hash.
                 */
                $student->password =
                    $password;

                if (
                    Schema::hasColumn(
                        'students',
                        'default_password'
                    )
                ) {
                    $student->default_password =
                        false;
                }

                $student->save();

                session()->flash(
                    'success',
                    'Contraseña actualizada correctamente.'
                );

                return redirect()
                    ->route('student.dashboard');
            }

            session()->flash(
                'success',
                'No se realizaron cambios.'
            );

            return redirect()
                ->route('student.dashboard');
        }

        /*
         * Verificar si continúa utilizando la contraseña inicial.
         */
        $passwordNeedsUpdate = false;

        if (
            Schema::hasColumn(
                'students',
                'default_password'
            )
        ) {
            $passwordNeedsUpdate =
                (bool) $student->default_password;

        } elseif (
            !empty($student->password_hash)
        ) {
            $passwordNeedsUpdate =
                Hash::check(
                    $this->defaultStudentPassword(),
                    $student->password_hash
                );
        }

        return view(
            'student.dashboard',
            array_merge(
                [
                    'student' => $student,
                    'error' => $error,
                    'success' => $success,
                    'passwordNeedsUpdate' =>
                        $passwordNeedsUpdate,
                ],
                $this->qrData(
                    $student->student_code
                )
            )
        );
    }

    public function studentProfile()
    {
        return redirect()
            ->route('student.dashboard');
    }

    public function studentProfileUpdate()
    {
        return redirect()
            ->route('student.dashboard');
    }

    public function studentChangePassword()
    {
        return redirect()
            ->route('student.dashboard');
    }

    public function studentChangePasswordUpdate(
        Request $request
    ) {
        return $this->studentDashboard(
            $request
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD ADMIN
    |--------------------------------------------------------------------------
    */

    public function adminDashboard(Request $request)
    {
        $access = $this->requireAdmin();

        if ($access) {
            return $access;
        }

        $this->touchActiveSession($request);

        $this->ensureCoreAccounts();

        $studentCount =
            Student::count();

        $attendanceCount =
            DB::table('attendances')
                ->count();

        $recentStudents =
            Student::orderByDesc('id')
                ->limit(5)
                ->get([
                    'id',
                    'full_name',
                    'student_code',
                    'classroom',
                ]);

        $resetError = null;
        $resetSuccess = null;

        if (
            $request->isMethod('post') &&
            $request->input('action') ===
            'reset_password'
        ) {
            $targetUser =
                strtoupper(
                    trim(
                        (string)
                        $request->input(
                            'recovery_user',
                            ''
                        )
                    )
                );

            $newPassword =
                (string)
                $request->input(
                    'recovery_password',
                    ''
                );

            $confirmPassword =
                (string)
                $request->input(
                    'recovery_confirm',
                    ''
                );

            if (
                $targetUser === '' ||
                $newPassword === '' ||
                $confirmPassword === ''
            ) {
                $resetError =
                    'Completa todos los campos para restablecer la contraseña.';

            } elseif (
                $newPassword !==
                $confirmPassword
            ) {
                $resetError =
                    'Las contraseñas no coinciden.';

            } elseif (
                strlen($newPassword) < 6
            ) {
                $resetError =
                    'La contraseña debe tener al menos 6 caracteres.';

            } else {
                $admin =
                    DB::table('admins')
                        ->whereRaw(
                            'UPPER(username) = ?',
                            [$targetUser]
                        )
                        ->first();

                $student = null;

                if (!$admin) {
                    $student =
                        Student::query()
                            ->whereRaw(
                                'UPPER(student_code) = ?',
                                [$targetUser]
                            )
                            ->first();

                    if (
                        !$student &&
                        Schema::hasColumn(
                            'students',
                            'account_number'
                        )
                    ) {
                        $student =
                            Student::query()
                                ->whereRaw(
                                    'UPPER(account_number) = ?',
                                    [$targetUser]
                                )
                                ->first();
                    }
                }

                if ($admin) {
                    DB::table('admins')
                        ->where(
                            'id',
                            $admin->id
                        )
                        ->update([
                            'password_hash' =>
                                Hash::make(
                                    $newPassword
                                ),
                        ]);

                    $resetSuccess =
                        'Contraseña de administrador restablecida correctamente.';

                } elseif ($student) {
                    /*
                     * El modelo generará password_hash.
                     */
                    $student->password =
                        $newPassword;

                    if (
                        Schema::hasColumn(
                            'students',
                            'default_password'
                        )
                    ) {
                        $student->default_password =
                            false;
                    }

                    $student->save();

                    $resetSuccess =
                        'Contraseña de alumno restablecida correctamente.';

                } else {
                    $resetError =
                        'No se encontró ningún usuario con ese nombre o número de cuenta.';
                }
            }
        }

        return view(
            'admin.dashboard',
            compact(
                'studentCount',
                'attendanceCount',
                'recentStudents',
                'resetError',
                'resetSuccess'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LISTADO DE ALUMNOS
    |--------------------------------------------------------------------------
    */

    public function studentsIndex()
    {
        $access = $this->requireAdmin();

        if ($access) {
            return $access;
        }

        $search = trim(
            (string) request(
                'search',
                ''
            )
        );

        $studentsQuery =
            Student::query();

        if ($search !== '') {
            $studentsQuery
                ->whereRaw(
                    'UPPER(account_number) LIKE ?',
                    [
                        '%' .
                        strtoupper($search) .
                        '%'
                    ]
                );
        }

        $students =
            $studentsQuery
                ->get()
                ->map(
                    function (
                        Student $student
                    ) {
                        [
                            $group,
                            $shift
                        ] = $this
                            ->splitClassroomSection(
                                (string)
                                $student->classroom
                            );

                        $student->display_group =
                            $group !== ''
                                ? $group
                                : 'SIN GRUPO';

                        $student->display_shift =
                            $shift !== ''
                                ? $shift
                                : 'SIN TURNO';

                        return $student;
                    }
                )
                ->sort(
                    function (
                        Student $left,
                        Student $right
                    ) {
                        [
                            $leftGroupOrder,
                            $leftGroupName
                        ] = $this
                            ->studentGroupSortKey(
                                (string)
                                $left->display_group
                            );

                        [
                            $rightGroupOrder,
                            $rightGroupName
                        ] = $this
                            ->studentGroupSortKey(
                                (string)
                                $right->display_group
                            );

                        if (
                            $leftGroupOrder !==
                            $rightGroupOrder
                        ) {
                            return
                                $leftGroupOrder
                                <=>
                                $rightGroupOrder;
                        }

                        if (
                            $leftGroupName !==
                            $rightGroupName
                        ) {
                            return strcmp(
                                $leftGroupName,
                                $rightGroupName
                            );
                        }

                        [
                            $leftShiftOrder,
                            $leftShiftName
                        ] = $this
                            ->studentShiftSortKey(
                                (string)
                                $left->display_shift
                            );

                        [
                            $rightShiftOrder,
                            $rightShiftName
                        ] = $this
                            ->studentShiftSortKey(
                                (string)
                                $right->display_shift
                            );

                        if (
                            $leftShiftOrder !==
                            $rightShiftOrder
                        ) {
                            return
                                $leftShiftOrder
                                <=>
                                $rightShiftOrder;
                        }

                        if (
                            $leftShiftName !==
                            $rightShiftName
                        ) {
                            return strcmp(
                                $leftShiftName,
                                $rightShiftName
                            );
                        }

                        return strcmp(
                            (string) $left->full_name,
                            (string) $right->full_name
                        );
                    }
                )
                ->values();

        $groupedStudents =
            $students
                ->groupBy(
                    function ($student) {
                        return (string)
                            $student->display_group;
                    }
                )
                ->map(
                    function ($groupStudents) {
                        return $groupStudents
                            ->groupBy(
                                function ($student) {
                                    return (string)
                                        $student->display_shift;
                                }
                            )
                            ->map(
                                function (
                                    $shiftStudents
                                ) {
                                    return $shiftStudents
                                        ->values();
                                }
                            )
                            ->sortKeysUsing(
                                function (
                                    $left,
                                    $right
                                ) {
                                    [
                                        $leftOrder,
                                        $leftName
                                    ] = $this
                                        ->studentShiftSortKey(
                                            $left
                                        );

                                    [
                                        $rightOrder,
                                        $rightName
                                    ] = $this
                                        ->studentShiftSortKey(
                                            $right
                                        );

                                    if (
                                        $leftOrder !==
                                        $rightOrder
                                    ) {
                                        return
                                            $leftOrder
                                            <=>
                                            $rightOrder;
                                    }

                                    return strcmp(
                                        $leftName,
                                        $rightName
                                    );
                                }
                            );
                    }
                )
                ->sortKeysUsing(
                    function (
                        $left,
                        $right
                    ) {
                        [
                            $leftOrder,
                            $leftName
                        ] = $this
                            ->studentGroupSortKey(
                                $left
                            );

                        [
                            $rightOrder,
                            $rightName
                        ] = $this
                            ->studentGroupSortKey(
                                $right
                            );

                        if (
                            $leftOrder !==
                            $rightOrder
                        ) {
                            return
                                $leftOrder
                                <=>
                                $rightOrder;
                        }

                        return strcmp(
                            $leftName,
                            $rightName
                        );
                    }
                );

        return view(
            'students.index',
            compact(
                'students',
                'groupedStudents',
                'search'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR ALUMNO
    |--------------------------------------------------------------------------
    */

    public function studentsStore(Request $request)
    {
        $access = $this->requireAdmin();

        if ($access) {
            return $access;
        }

        $rules = [
            'full_name' => [
                'required',
                'string',
                'max:255',
            ],
            'account_number' => [
                'required',
                'string',
                'max:255',
            ],
            'classroom' => [
                'required',
                'string',
                'max:255',
            ],
            'shift' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];

        if (
            Schema::hasColumn(
                'students',
                'account_number'
            )
        ) {
            $rules['account_number'][] =
                'unique:students,account_number';
        }

        $data =
            $request->validate(
                $rules
            );

        /*
         * Este límite es de procesos de creación,
         * no de alumnos conectados.
         */
        $regId = null;

        if (
            Schema::hasTable(
                'active_registrations'
            )
        ) {
            $recentRegs =
                DB::table(
                    'active_registrations'
                )
                    ->where(
                        'created_at',
                        '>=',
                        now()->subMinutes(10)
                    )
                    ->count();

            if ($recentRegs >= 20) {
                return redirect()
                    ->route('students.index')
                    ->with(
                        'error',
                        'Demasiadas inscripciones en curso. Intenta más tarde.'
                    );
            }

            $regId =
                DB::table(
                    'active_registrations'
                )
                    ->insertGetId([
                        'session_id' =>
                            $request
                                ->session()
                                ->getId(),

                        'started_at' =>
                            now(),

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
        }

        try {
            $accountNumber =
                strtoupper(
                    trim(
                        $data['account_number']
                    )
                );

            $group =
                strtoupper(
                    trim(
                        $data['classroom']
                    )
                );

            $shift =
                strtoupper(
                    trim(
                        (string)
                        (
                            $data['shift']
                            ?? ''
                        )
                    )
                );

            $classroom = $group;

            if ($shift !== '') {
                $classroom .=
                    ' - ' .
                    $shift;
            }

            $studentCode =
                $accountNumber !== ''
                    ? $accountNumber
                    : (
                        'S' .
                        time() .
                        substr(
                            uniqid(),
                            -4
                        )
                    );

            $baseCode =
                $studentCode;

            $suffix = 1;

            while (
                DB::table('students')
                    ->whereRaw(
                        'UPPER(student_code) = ?',
                        [
                            strtoupper(
                                $studentCode
                            )
                        ]
                    )
                    ->exists()
            ) {
                $studentCode =
                    $baseCode .
                    '-' .
                    $suffix++;
            }

            $accountToStore =
                Schema::hasColumn(
                    'students',
                    'account_number'
                )
                    ? $accountNumber
                    : null;

            $fullName =
                trim(
                    $data['full_name']
                );

            $studentData = [
                'full_name' =>
                    $fullName,

                'student_code' =>
                    $studentCode,

                'account_number' =>
                    $accountToStore,

                'classroom' =>
                    $classroom,

                /*
                 * El modelo Student hace el Hash.
                 */
                'password' =>
                    $this
                        ->defaultStudentPassword(),
            ];

            if (
                Schema::hasColumn(
                    'students',
                    'default_password'
                )
            ) {
                $studentData[
                    'default_password'
                ] = true;
            }

            $student =
                Student::create(
                    $studentData
                );

        } finally {
            if ($regId) {
                DB::table(
                    'active_registrations'
                )
                    ->where(
                        'id',
                        $regId
                    )
                    ->delete();
            }
        }

        return redirect()
            ->route('students.index')
            ->with(
                'default_pw',
                [
                    'code' =>
                        $student->student_code,

                    'password' =>
                        $this
                            ->defaultStudentPassword(),
                ]
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR ALUMNO
    |--------------------------------------------------------------------------
    */

    public function studentsDestroy(Student $student)
    {
        $access = $this->requireAdmin();

        if ($access) {
            return $access;
        }

        $student->delete();

        return redirect()
            ->route('students.index');
    }

    /*
    |--------------------------------------------------------------------------
    | QR DE ALUMNO
    |--------------------------------------------------------------------------
    */

    public function studentsQr(Student $student)
    {
        $access = $this->requireAdmin();

        if ($access) {
            return $access;
        }

        return view(
            'student.qr',
            array_merge(
                [
                    'student' => $student,
                ],
                $this->qrData(
                    $student->student_code
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETALLE ALUMNO
    |--------------------------------------------------------------------------
    */

    public function studentsDetails(
        Request $request,
        Student $student
    ) {
        $access = $this->requireAdmin();

        if ($access) {
            return $access;
        }

        $error = null;
        $success = null;

        if ($request->isMethod('post')) {
            /*
             * FOTO
             */
            if ($request->hasFile('photo')) {
                $request->validate([
                    'photo' => [
                        'required',
                        'image',
                        'mimes:png,jpg,jpeg,webp',
                        'max:4096',
                    ],
                ]);

                $photo =
                    $request->file('photo');

                if (!$photo) {
                    $error =
                        'Selecciona una fotografía válida.';

                } else {
                    $targetDirectory =
                        storage_path(
                            'app/public/students'
                        );

                    File::ensureDirectoryExists(
                        $targetDirectory
                    );

                    $extension =
                        strtolower(
                            $photo
                                ->getClientOriginalExtension()
                            ?: 'jpg'
                        );

                    $storedFilename =
                        'student_' .
                        $student->id .
                        '.' .
                        $extension;

                    $photo->move(
                        $targetDirectory,
                        $storedFilename
                    );

                    $student->photo_filename =
                        $storedFilename;

                    $student->save();

                    $student =
                        $student->fresh();

                    $success =
                        'Fotografía actualizada correctamente.';
                }

            /*
             * DATOS
             */
            } elseif (
                $request->has('full_name') ||
                $request->has('account_number') ||
                $request->has('classroom')
            ) {
                $rules = [
                    'full_name' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    'classroom' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],
                ];

                if (
                    Schema::hasColumn(
                        'students',
                        'account_number'
                    )
                ) {
                    $rules['account_number'] = [
                        'required',
                        'string',
                        'max:255',
                    ];
                }

                $data =
                    $request->validate(
                        $rules
                    );

                $student->full_name =
                    trim(
                        $data['full_name']
                    );

                if (
                    isset(
                        $data['classroom']
                    )
                ) {
                    $student->classroom =
                        strtoupper(
                            trim(
                                (string)
                                $data['classroom']
                            )
                        );
                }

                if (
                    Schema::hasColumn(
                        'students',
                        'account_number'
                    ) &&
                    isset(
                        $data['account_number']
                    )
                ) {
                    $account =
                        strtoupper(
                            trim(
                                $data['account_number']
                            )
                        );

                    $exists =
                        DB::table('students')
                            ->whereRaw(
                                'UPPER(account_number) = ?',
                                [$account]
                            )
                            ->where(
                                'id',
                                '<>',
                                $student->id
                            )
                            ->exists();

                    if ($exists) {
                        return back()
                            ->with(
                                'error',
                                'El número de cuenta ya está en uso por otro alumno.'
                            );
                    }

                    $student->account_number =
                        $account;
                }

                $student->save();

                $student =
                    $student->fresh();

                $success =
                    'Datos actualizados correctamente.';
            }
        }

        $attendanceCount =
            DB::table('attendances')
                ->where(
                    'student_code',
                    $student->student_code
                )
                ->count();

        $recentAttendance =
            DB::table('attendances')
                ->where(
                    'student_code',
                    $student->student_code
                )
                ->orderByDesc('attendance_date')
                ->orderByDesc('attendance_time')
                ->limit(5)
                ->get([
                    'attendance_date',
                    'attendance_time',
                    'source',
                ]);

        return view(
            'student.details',
            array_merge(
                [
                    'student' =>
                        $student,

                    'attendanceCount' =>
                        $attendanceCount,

                    'recentAttendance' =>
                        $recentAttendance,

                    'error' =>
                        $error,

                    'success' =>
                        $success,
                ],
                $this->qrData(
                    $student->student_code
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PASE ALUMNO
    |--------------------------------------------------------------------------
    */

    public function studentPass(string $student_code)
    {
        $code =
            strtoupper(
                trim($student_code)
            );

        $student =
            Student::query()
                ->whereRaw(
                    'UPPER(student_code) = ?',
                    [$code]
                )
                ->first();

        if (
            !$student &&
            Schema::hasColumn(
                'students',
                'account_number'
            )
        ) {
            $student =
                Student::query()
                    ->whereRaw(
                        'UPPER(account_number) = ?',
                        [$code]
                    )
                    ->first();
        }

        if (!$student) {
            abort(
                404,
                'Alumno no encontrado'
            );
        }

        return view(
            'student.qr',
            array_merge(
                [
                    'student' => $student,
                ],
                $this->qrData(
                    $student->student_code
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SCANNER
    |--------------------------------------------------------------------------
    */

    public function scan(Request $request)
    {
        $access =
            $this->requireAdminOrScanner();

        if ($access) {
            return $access;
        }

        $this->touchActiveSession(
            $request
        );

        return view('scan');
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK-IN
    |--------------------------------------------------------------------------
    */

    public function checkin(Request $request)
    {
        /*
         * Este endpoint es consumido por JavaScript.
         * Si la sesión no es válida, devolvemos JSON 401 en vez de redirigir.
         */
        if (!$this->hasAdminOrScannerSession()) {
            return response()->json([
                'ok' => false,
                'message' => 'Sesión no autorizada. Inicia sesión nuevamente como escáner.',
            ], 401);
        }

        $this->touchActiveSession($request);

        $rawPayload = (string) (
            $request->json('student_code')
            ?? $request->input('student_code', '')
        );

        $studentCode = $this->extractStudentCode($rawPayload);

        $source = strtolower(
            trim(
                (string) (
                    $request->json('source')
                    ?? $request->input('source', 'qr')
                )
            )
        ) ?: 'qr';

        if ($studentCode === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Código vacío o no válido.',
            ], 400);
        }

        $normalizedCode = strtoupper(trim($studentCode));

        $student = Student::query()
            ->whereRaw(
                'UPPER(student_code) = ?',
                [$normalizedCode]
            )
            ->first();

        if (
            !$student &&
            Schema::hasColumn('students', 'account_number')
        ) {
            $student = Student::query()
                ->whereRaw(
                    'UPPER(account_number) = ?',
                    [$normalizedCode]
                )
                ->first();
        }

        if (!$student) {
            return response()->json([
                'ok' => false,
                'message' => 'Alumno no registrado.',
            ], 404);
        }

        $today = now()->toDateString();
        $nowTime = now()->format('H:i:s');

        $alreadyExists = DB::table('attendances')
            ->where('student_id', $student->id)
            ->where('attendance_date', $today)
            ->exists();

        if ($alreadyExists) {
            $existing = DB::table('attendances')
                ->where('student_id', $student->id)
                ->where('attendance_date', $today)
                ->orderByDesc('attendance_time')
                ->first(['attendance_time']);

            return response()->json([
                'ok' => false,
                'message' => 'Alumno ya registrado hoy',
                'student' => $student->full_name,
                'classroom' => $student->classroom,
                'date' => $today,
                'time' => $existing?->attendance_time,
            ], 409);
        }

        try {
            $checkedAt = now();

            DB::table('attendances')->insert([
                // Relación obligatoria con el alumno.
                'student_id' => $student->id,

                // Datos de referencia del alumno.
                'student_code' => $student->student_code,
                'student_name' => $student->full_name,
                'classroom' => $student->classroom,

                // Columnas actuales de asistencia.
                'attendance_date' => $checkedAt->toDateString(),
                'attendance_time' => $checkedAt->format('H:i:s'),

                // Columnas heredadas: se mantienen por compatibilidad
                // con vistas o datos previos del sistema.
                'date' => $checkedAt->toDateString(),
                'checked_at' => $checkedAt->toDateTimeString(),

                'source' => $source,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Asistencia registrada',
                'student' => $student->full_name,
                'classroom' => $student->classroom,
                'time' => $checkedAt->format('H:i:s'),
                'date' => $checkedAt->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error registrando asistencia', [
                'student_id' => $student->id ?? null,
                'student_code' => $student->student_code ?? null,
                'source' => $source,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo registrar la asistencia.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ASISTENCIAS
    |--------------------------------------------------------------------------
    */

    public function attendance(Request $request)
    {
        $access =
            $this->requireAdminOrScanner();

        if ($access) {
            return $access;
        }

        $this->touchActiveSession(
            $request
        );

        $selectedDate =
            (string)
            $request->query(
                'date',
                now()->toDateString()
            );

        $rows =
            DB::table('attendances as a')
                ->join(
                    'students as s',
                    's.student_code',
                    '=',
                    'a.student_code'
                )
                ->where(
                    'a.attendance_date',
                    $selectedDate
                )
                ->orderBy(
                    's.classroom'
                )
                ->orderBy(
                    's.full_name'
                )
                ->get([
                    's.full_name',
                    's.student_code',
                    's.classroom',
                    'a.attendance_time',
                    'a.attendance_date',
                    'a.source',
                ]);

        $attendanceSections = [];
        $currentSection = null;
        $currentRecords = [];

        foreach ($rows as $row) {
            [
                $sectionGroup,
                $sectionShift
            ] = $this
                ->splitClassroomSection(
                    (string)
                    $row->classroom
                );

            $sectionLabel =
                $sectionShift === ''
                    ? $sectionGroup
                    : (
                        $sectionGroup .
                        ' - ' .
                        $sectionShift
                    );

            if (
                $currentSection !==
                $sectionLabel
            ) {
                if (
                    $currentSection !==
                    null
                ) {
                    $attendanceSections[] = [
                        'label' =>
                            $currentSection,

                        'records' =>
                            $currentRecords,
                    ];
                }

                $currentSection =
                    $sectionLabel;

                $currentRecords =
                    [];
            }

            $currentRecords[] =
                $row;
        }

        if (
            $currentSection !== null
        ) {
            $attendanceSections[] = [
                'label' =>
                    $currentSection,

                'records' =>
                    $currentRecords,
            ];
        }

        return view(
            'attendance',
            [
                'attendanceSections' =>
                    $attendanceSections,

                'selectedDate' =>
                    $selectedDate,
            ]
        );
    }
}