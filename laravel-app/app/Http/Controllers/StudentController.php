<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function registerForm()
    {
        return view('student.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string',
            'student_code' => 'nullable|string|unique:students,student_code',
            'account_number' => 'required|string|unique:students,account_number',
            'classroom' => 'required|string',
            'password' => 'required|confirmed',
        ]);

        $student_code = isset($data['student_code']) && $data['student_code'] ? $data['student_code'] : $data['account_number'];
        $student = Student::create([
            'full_name' => $data['full_name'],
            'student_code' => strtoupper($student_code),
            'account_number' => strtoupper($data['account_number']),
            'classroom' => strtoupper($data['classroom']),
            'password' => Hash::make($data['password']),
        ]);

        session(['student_id' => $student->id]);
        return redirect()->route('student.dashboard');
    }

    public function loginForm()
    {
        return view('student.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'student_code' => 'required|string',
            'password' => 'required|string',
        ]);
        $identifier = strtoupper($data['student_code']);
        $student = Student::where('student_code', $identifier)->orWhere('account_number', $identifier)->first();
        if ($student && Hash::check($data['password'], $student->password)) {
            session(['student_id' => $student->id]);
            return redirect()->route('student.dashboard');
        }

        return back()->withErrors(['msg' => 'Credenciales invalidas']);
    }

    public function dashboard(Request $request)
    {
        $student = Student::find(session('student_id'));
        if (! $student) return redirect()->route('student.login');

        $qrUrl = URL::to('/qr/'.$student->student_code);
        return view('student.dashboard', compact('student', 'qrUrl'));
    }

    public function logout()
    {
        session()->forget('student_id');
        return redirect()->route('student.login');
    }

    public function publicQr($student_code)
    {
        $student = Student::where('student_code', strtoupper($student_code))->firstOrFail();
        $qrUrl = URL::to('/qr/'.$student->student_code);
        return view('student.qr', compact('student', 'qrUrl'));
    }

    public function checkin(Request $request)
    {
        $code = $request->input('student_code');
        $code = strtoupper($code);
        $student = Student::where('student_code', $code)->first();
        if (! $student) return response()->json(['ok' => false, 'message' => 'Alumno no registrado'], 404);

        $today = now()->toDateString();
        try {
            $inserted = DB::table('attendances')->insertOrIgnore([
                'student_code' => $student->student_code,
                'student_name' => $student->full_name,
                'classroom' => $student->classroom,
                'date' => $today,
                'checked_at' => now(),
                'source' => $request->input('source', 'qr'),
            ]);

            // insertOrIgnore returns boolean depending on driver; check if row exists
            $exists = DB::table('attendances')->where('student_code', $student->student_code)->where('date', $today)->exists();
            if ($exists) {
                return response()->json(['ok' => true, 'message' => 'Asistencia registrada', 'student' => $student->full_name, 'classroom' => $student->classroom, 'time' => now()->format('H:i:s')]);
            }

            return response()->json(['ok' => false, 'message' => 'No se pudo registrar la asistencia'], 500);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error al registrar asistencia', 'error' => $e->getMessage()], 500);
        }
    }

    // Admin: list and create students (from admin view)
    public function index()
    {
        $students = Student::orderBy('full_name')->get();
        return view('students.index', compact('students'));
    }

    public function storeAdmin(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string',
            'student_code' => 'nullable|string|unique:students,student_code',
            'account_number' => 'required|string|unique:students,account_number',
            'classroom' => 'required|string',
        ]);

        $student_code = isset($data['student_code']) && $data['student_code'] ? $data['student_code'] : $data['account_number'];
        // default password for all new students
        $defaultPassword = '123456';

        $student = Student::create([
            'full_name' => $data['full_name'],
            'student_code' => strtoupper($student_code),
            'account_number' => strtoupper($data['account_number']),
            'classroom' => strtoupper($data['classroom']),
            'password' => Hash::make($defaultPassword),
        ]);

        return redirect()->route('students.index')->with('default_pw', ['code' => $student->student_code, 'password' => $defaultPassword]);
    }

    // Student profile
    public function showProfile()
    {
        $student = Student::find(session('student_id'));
        if (! $student) return redirect()->route('student.login');
        return view('student.edit', compact('student'));
    }

    public function updateProfile(Request $request)
    {
        $student = Student::find(session('student_id'));
        if (! $student) return redirect()->route('student.login');

        $data = $request->validate([
            'full_name' => 'required|string',
            'classroom' => 'nullable|string',
        ]);

        $student->full_name = $data['full_name'];
        if (isset($data['classroom'])) $student->classroom = strtoupper($data['classroom']);
        $student->save();

        return redirect()->route('student.dashboard')->with('message', 'Perfil actualizado');
    }

    public function showChangePassword()
    {
        $student = Student::find(session('student_id'));
        if (! $student) return redirect()->route('student.login');
        return view('student.change_password', compact('student'));
    }

    public function updatePassword(Request $request)
    {
        $student = Student::find(session('student_id'));
        if (! $student) return redirect()->route('student.login');

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|confirmed|min:6',
        ]);

        if (! Hash::check($data['current_password'], $student->password)) {
            return back()->withErrors(['current_password' => 'Contraseña actual incorrecta']);
        }

        $student->password = Hash::make($data['password']);
        $student->save();

        return redirect()->route('student.dashboard')->with('message', 'Contraseña actualizada');
    }

    public function qrById($id)
    {
        $student = Student::findOrFail($id);
        $qrUrl = URL::to('/qr/'.$student->student_code);
        return view('student.qr', compact('student','qrUrl'));
    }
}
