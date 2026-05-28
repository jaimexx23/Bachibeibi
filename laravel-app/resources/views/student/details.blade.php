@extends('layouts.app')

@section('content')
<section class="grid grid-2">
  <article class="card">
    <p class="hero-eyebrow">Detalle del alumno</p>
    <h2 class="card-title">{{ $student->full_name }}</h2>
    <p class="card-subtitle">Código: {{ $student->student_code }} | Grupo: {{ $student->classroom }}</p>

    @if($error)
      <div class="notice notice-error">{{ $error }}</div>
    @endif

    @if($success)
      <div class="notice notice-success">{{ $success }}</div>
    @endif

    <div class="meta-grid">
      <div><strong>Asistencias:</strong> {{ $attendanceCount }}</div>
      <div><strong>Rol:</strong> {{ $student->role }}</div>
      <div><strong>Archivo:</strong> {{ $student->photo_filename ?? 'Sin foto' }}</div>
    </div>

    @if($student->photo_filename && file_exists(public_path('uploads/students/' . $student->photo_filename)))
      <div class="photo-preview">
        <img src="{{ asset('uploads/students/' . $student->photo_filename) }}" alt="Foto de {{ $student->full_name }}">
      </div>
    @endif

    <div class="qr-box">
      {!! $qrSvg !!}
    </div>
  </article>

  <article class="card">
    <h3 class="card-title">Subir fotografía</h3>
    <p class="card-subtitle">PNG, JPG, JPEG o WEBP. Máximo 4 MB.</p>

    <form method="post" enctype="multipart/form-data" action="{{ route('students.details', $student) }}" class="form">
      @csrf
      <label class="field">
        <span>Fotografía</span>
        <input class="input" type="file" name="photo" accept="image/png,image/jpeg,image/webp" required>
      </label>
      <button class="btn" type="submit">Guardar fotografía</button>
    </form>

    <h3 class="card-title" style="margin-top: 1.5rem;">Últimas asistencias</h3>
    <div class="list">
      @forelse($recentAttendance as $row)
        <div class="list-item">
          <div>{{ $row->attendance_date }} {{ $row->attendance_time }}</div>
          <div class="muted">{{ $row->source }}</div>
        </div>
      @empty
        <div class="notice notice-info">Todavía no hay asistencias registradas.</div>
      @endforelse
    </div>

    <div class="actions">
      <a class="btn btn-secondary" href="{{ route('students.qr', $student) }}">Ver QR</a>
      <a class="btn btn-secondary" href="{{ route('students.index') }}">Volver</a>
    </div>
  </article>
</section>
@endsection