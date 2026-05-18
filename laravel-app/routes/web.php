<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\StudentController;

Route::get('/student/register', [StudentController::class, 'registerForm'])->name('student.register');
Route::post('/student/register', [StudentController::class, 'register']);
Route::get('/student/login', [StudentController::class, 'loginForm'])->name('student.login');
Route::post('/student/login', [StudentController::class, 'login']);
Route::get('/student/logout', [StudentController::class, 'logout'])->name('student.logout');
Route::get('/student/dashboard', [StudentController::class, 'dashboard'])->name('student.dashboard');
Route::get('/qr/{student_code}', [StudentController::class, 'publicQr'])->name('student.qr');
Route::post('/api/checkin', [StudentController::class, 'checkin'])->name('api.checkin');

// Admin/student management
Route::get('/students', [StudentController::class, 'index'])->name('students.index');
Route::post('/students', [StudentController::class, 'storeAdmin'])->name('students.store');
Route::get('/students/{id}/qr', [StudentController::class, 'qrById'])->name('students.qr');

// Scanner and attendance listing
Route::get('/scan', function () { return view('scan'); })->name('scan');
Route::get('/attendance', function () { return view('attendance'); })->name('attendance');

// Student profile routes (student must be logged in)
Route::get('/student/profile', [StudentController::class, 'showProfile'])->name('student.profile');
Route::post('/student/profile', [StudentController::class, 'updateProfile'])->name('student.profile.update');
Route::get('/student/change-password', [StudentController::class, 'showChangePassword'])->name('student.change_password');
Route::post('/student/change-password', [StudentController::class, 'updatePassword'])->name('student.change_password.update');
