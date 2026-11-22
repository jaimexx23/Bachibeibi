@extends('layouts.app')

@section('content')
<section class="grid grid-2">
  <article class="card">
    <p class="hero-eyebrow">{{ $isParent ? 'Panel familiar' : 'Panel del alumno' }}</p>
    <h2 class="card-title">{{ $student->full_name }}</h2>
    <p class="card-subtitle">Código: {{ $student->student_code }} | Grupo: {{ $student->classroom }}</p>

    @if($isAtRisk)
      <div class="notice notice-error">
        <strong>Alerta:</strong> el alumno tiene 3 días escolares consecutivos sin asistencia.
      </div>
    @endif

    @if($error)
      <div class="notice notice-error">{{ $error }}</div>
    @endif

    @if($success)
      <div class="notice notice-success">{{ $success }}</div>
    @endif

    <div class="qr-box">
      {!! $qrSvg !!}
    </div>

    <p class="qr-url"><a href="{{ $qrPayload }}" target="_blank" rel="noreferrer">Abrir enlace del QR</a></p>

    <h3 class="card-title">Últimas asistencias</h3>
    @forelse($recentAttendance as $attendance)
      <p>
        {{ $attendance->attendance_date }} · {{ $attendance->attendance_time }} · {{ $attendance->source }}
        @if($attendance->attendance_status['is_late'])
          · <strong style="color:#a31515;">RETARDO</strong>
        @else
          · <strong style="color:#2f6b2f;">PUNTUAL</strong>
        @endif
      </p>
    @empty
      <p class="card-subtitle">No hay asistencias registradas.</p>
    @endforelse
  </article>

  @if($passwordNeedsUpdate)
  <article class="card">
    <h3 class="card-title">Cambiar contraseña</h3>
    <p class="card-subtitle">Si ves este aviso, actualiza tu clave ahora.</p>

    <div class="notice notice-info">Debes cambiar la contraseña por defecto.</div>

    <form method="post" action="{{ route('student.dashboard') }}" class="form">
      @csrf
      <label class="field">
        <span>Nueva contraseña</span>
        <input class="input" type="password" name="password" required>
      </label>

      <label class="field">
        <span>Confirmar nueva contraseña</span>
        <input class="input" type="password" name="confirm_password" required>
      </label>

      <button class="btn" type="submit">Guardar cambios</button>
    </form>

    <div class="actions">
      <a class="btn btn-secondary" href="{{ $isParent ? route('parent.logout') : route('student.logout') }}">Cerrar sesión</a>
    </div>
  </article>
  @endif
</section>
@endsection
