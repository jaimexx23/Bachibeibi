<?php

use App\Http\Controllers\BachilleresController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/', [BachilleresController::class, 'home'])->name('home');
Route::match(['get', 'post'], '/menu', [BachilleresController::class, 'menu'])->name('menu');

Route::get('/admin/login', [BachilleresController::class, 'adminLogin'])->name('admin.login');
Route::post('/admin/login', [BachilleresController::class, 'menu']);
Route::get('/student/login', [BachilleresController::class, 'studentLogin'])->name('student.login');
Route::post('/student/login', [BachilleresController::class, 'menu']);
Route::get('/scanner/login', [BachilleresController::class, 'scannerLogin'])->name('scanner.login');
Route::post('/scanner/login', [BachilleresController::class, 'menu']);

Route::get('/admin/logout', [BachilleresController::class, 'adminLogout'])->name('admin.logout');
Route::get('/student/logout', [BachilleresController::class, 'studentLogout'])->name('student.logout');
Route::get('/scanner/logout', [BachilleresController::class, 'scannerLogout'])->name('scanner.logout');

Route::match(['get', 'post'], '/student/register', [BachilleresController::class, 'studentRegister'])->name('student.register');
Route::match(['get', 'post'], '/student/dashboard', [BachilleresController::class, 'studentDashboard'])->name('student.dashboard');
Route::get('/student', [BachilleresController::class, 'studentHome'])->name('student.home');
Route::get('/student/profile', [BachilleresController::class, 'studentProfile'])->name('student.profile');
Route::post('/student/profile', [BachilleresController::class, 'studentProfileUpdate'])->name('student.profile.update');
Route::get('/student/change-password', [BachilleresController::class, 'studentChangePassword'])->name('student.change_password');
Route::post('/student/change-password', [BachilleresController::class, 'studentChangePasswordUpdate'])->name('student.change_password.update');

Route::match(['get', 'post'], '/admin', [BachilleresController::class, 'adminDashboard'])->name('admin.dashboard');
Route::get('/students', [BachilleresController::class, 'studentsIndex'])->name('students.index');
Route::post('/students', [BachilleresController::class, 'studentsStore'])->name('students.store');
Route::post('/students/{student}/delete', [BachilleresController::class, 'studentsDestroy'])->name('students.destroy');
Route::get('/students/{student}/qr', [BachilleresController::class, 'studentsQr'])->name('students.qr');
Route::match(['get', 'post'], '/students/{student}/details', [BachilleresController::class, 'studentsDetails'])->name('students.details');

Route::get('/qr/{student_code}', [BachilleresController::class, 'studentPass'])->name('student.qr');
Route::get('/scan', [BachilleresController::class, 'scan'])->name('scan');
Route::post('/api/checkin', [BachilleresController::class, 'checkin'])->name('api.checkin');
// Emergency GET endpoint (no CSRF) for manual checkin when JS cannot send CSRF token
Route::get('/api/checkin/{student_code}', [BachilleresController::class, 'checkinGet'])->name('api.checkin.get');
Route::get('/attendance', [BachilleresController::class, 'attendance'])->name('attendance');
