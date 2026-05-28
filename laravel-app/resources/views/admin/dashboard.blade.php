@extends('layouts.app')

@section('content')
<section class="grid grid-3">
  <article class="card stat-card">
    <p class="hero-eyebrow">Resumen</p>
    <h2 class="card-title">{{ $studentCount }}</h2>
    <p class="card-subtitle">Alumnos registrados</p>
  </article>

  <article class="card stat-card">
    <p class="hero-eyebrow">Resumen</p>
    <h2 class="card-title">{{ $attendanceCount }}</h2>
    <p class="card-subtitle">Asistencias totales</p>
  </article>

  <article class="card accent-card">
    <h3 class="card-title">Accesos</h3>
    <div class="actions stacked-actions">
      <a class="btn btn-secondary" href="{{ route('students.index') }}">Gestionar alumnos</a>
      <a class="btn btn-secondary" href="{{ route('scan') }}">Abrir escáner</a>
      <a class="btn btn-secondary" href="{{ route('attendance') }}">Ver asistencias</a>
    </div>
  </article>
</section>

<section class="grid grid-2" style="margin-top: 1.5rem;">
  <article class="card">
    <h3 class="card-title">Restablecer contraseña</h3>
    <p class="card-subtitle">Funciona para admin o alumno escribiendo el usuario o código.</p>

    @if($resetError)
      <div class="notice notice-error">{{ $resetError }}</div>
    @endif

    @if($resetSuccess)
      <div class="notice notice-success">{{ $resetSuccess }}</div>
    @endif

    <form method="post" action="{{ route('admin.dashboard') }}" class="form">
      @csrf
      <input type="hidden" name="action" value="reset_password">

      <label class="field">
        <span>Usuario o código</span>
        <input class="input" name="recovery_user" required>
      </label>

      <label class="field">
        <span>Nueva contraseña</span>
        <input class="input" type="password" name="recovery_password" required>
      </label>

      <label class="field">
        <span>Confirmar contraseña</span>
        <input class="input" type="password" name="recovery_confirm" required>
      </label>

      <button class="btn" type="submit">Restablecer</button>
    </form>
  </article>

  <article class="card">
    <h3 class="card-title">Últimos alumnos</h3>
    <div class="list">
      @foreach($recentStudents as $student)
        <div class="list-item">
          <div>
            <strong>{{ $student->full_name }}</strong>
            <div class="muted">{{ $student->student_code }} · {{ $student->classroom }}</div>
          </div>
          <a class="btn btn-secondary" href="{{ route('students.details', $student->id) }}">Abrir</a>
        </div>
      @endforeach
    </div>
  </article>
</section>
@endsection