@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Bienvenido, {{ $student->full_name }}</h2>
    <p>Código: <strong>{{ $student->student_code }}</strong> — Grupo: {{ $student->classroom }}</p>
    <p>Presenta este QR para registrar tu asistencia.</p>
    <div class="qr">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($qrUrl) }}" alt="QR">
    </div>
    <div style="margin-top:12px">
      <a href="{{ route('student.profile') }}">Editar perfil</a> |
      <a href="{{ route('student.change_password') }}">Cambiar contraseña</a> |
      <a href="{{ route('student.logout') }}">Cerrar sesión</a>
    </div>
  </div>
@endsection
