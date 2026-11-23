@extends('layouts.app')

@section('content')
<section class="grid grid-2">
  <article class="card">
    <p class="hero-eyebrow">QR del alumno</p>
    <h2 class="card-title">{{ $student->full_name }}</h2>
    <p class="card-subtitle">Código: {{ $student->student_code }} | Grupo: {{ $student->classroom }}</p>

    @if($activeSuspension)
      <div class="notice notice-error">
        <strong>Acceso denegado.</strong>
        El alumno se encuentra suspendido del {{ $activeSuspension->starts_at }} al {{ $activeSuspension->ends_at }}.
        @if($activeSuspension->reason)
          <br>Motivo: {{ $activeSuspension->reason }}
        @endif
      </div>
    @endif

    @if(!$activeSuspension)
    <div class="qr-box">
      {!! $qrSvg !!}
    </div>

    <p class="qr-url"><a href="{{ $qrPayload }}" target="_blank" rel="noreferrer">{{ $qrPayload }}</a></p>
    @endif
  </article>

  <article class="card accent-card">
    <h3 class="card-title">Uso</h3>
    <p class="card-subtitle">Escanea este código con el módulo de asistencia para registrar la entrada del alumno.</p>
    <div class="actions">
      <a class="btn btn-secondary" href="{{ route('students.index') }}">Volver a alumnos</a>
      <a class="btn btn-secondary" href="{{ route('students.details', $student) }}">Ver detalles</a>
    </div>
  </article>
</section>
@endsection
