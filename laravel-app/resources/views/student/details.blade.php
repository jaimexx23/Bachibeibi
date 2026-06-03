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

    <h3 class="card-title" style="margin-top: 1.5rem;">Editar datos</h3>
    <p class="card-subtitle">Modifica el nombre, número de cuenta o grupo del alumno.</p>

    <form method="post" action="{{ route('students.details', $student) }}" class="form">
      @csrf
      <div class="form-grid">
        <label class="field">
          <span>Nombre completo</span>
          <input class="input" name="full_name" value="{{ old('full_name', $student->full_name) }}" required>
        </label>

        @if(
          Illuminate\Support\Facades\Schema::hasColumn('students', 'account_number')
        )
        <label class="field">
          <span>Número de cuenta</span>
          <input class="input" name="account_number" value="{{ old('account_number', $student->account_number) }}" required>
        </label>
        @endif

        <label class="field">
          <span>Grupo / Aula</span>
          <input class="input" name="classroom" value="{{ old('classroom', $student->classroom) }}">
        </label>
      </div>

      <button class="btn" type="submit">Guardar cambios</button>
    </form>

    @php
      $studentPhotoFilename = $student->photo_filename;
      $photoInStoragePath = $studentPhotoFilename ? storage_path('app/public/students/' . $studentPhotoFilename) : null;
      $photoInLegacyPublicPath = $studentPhotoFilename ? public_path('uploads/students/' . $studentPhotoFilename) : null;
      $photoUrl = null;

      if ($studentPhotoFilename && $photoInStoragePath && file_exists($photoInStoragePath)) {
        $photoUrl = asset('storage/students/' . $studentPhotoFilename);
      } elseif ($studentPhotoFilename && $photoInLegacyPublicPath && file_exists($photoInLegacyPublicPath)) {
        $photoUrl = asset('uploads/students/' . $studentPhotoFilename);
      }
    @endphp

    @if($photoUrl)
      <div class="photo-preview">
        <img src="{{ $photoUrl }}" alt="Foto de {{ $student->full_name }}">
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