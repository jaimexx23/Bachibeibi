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

    <h3 class="card-title" style="margin-top: 1.5rem;">Suspensión</h3>

    @if($activeSuspension)
      <div class="notice notice-error">
        Suspensión activa del {{ $activeSuspension->starts_at }} al {{ $activeSuspension->ends_at }}.
        @if($activeSuspension->reason)
          <br>Motivo: {{ $activeSuspension->reason }}
        @endif
      </div>

      <form method="post" action="{{ route('students.details', $student) }}" class="form">
        @csrf
        <input type="hidden" name="suspension_action" value="lift">
        <button class="btn btn-secondary" type="submit">Levantar suspensión</button>
      </form>
    @else
      <p class="card-subtitle">El alumno no tiene una suspensión activa.</p>
    @endif

    <form method="post" action="{{ route('students.details', $student) }}" class="form" style="margin-top: 1rem;">
      @csrf
      <input type="hidden" name="suspension_action" value="apply">
      <div class="form-grid">
        <label class="field">
          <span>Inicio</span>
          <input class="input" type="date" name="suspension_starts_at" value="{{ old('suspension_starts_at', now()->toDateString()) }}" required>
        </label>
        <label class="field">
          <span>Fin</span>
          <input class="input" type="date" name="suspension_ends_at" value="{{ old('suspension_ends_at', now()->toDateString()) }}" required>
        </label>
      </div>
      <label class="field">
        <span>Motivo</span>
        <textarea class="input" name="suspension_reason" rows="3">{{ old('suspension_reason') }}</textarea>
      </label>
      <button class="btn" type="submit">Aplicar suspensión</button>
    </form>

    @if($suspensionHistory->isNotEmpty())
      <h4 class="card-title" style="margin-top: 1.5rem;">Historial de suspensiones</h4>
      <div class="list">
        @foreach($suspensionHistory as $suspension)
          <div class="list-item">
            <div>
              <strong>{{ $suspension->starts_at }} al {{ $suspension->ends_at }}</strong>
              @if($suspension->reason)
                <div class="muted">{{ $suspension->reason }}</div>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif

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
          <div>
            {{ $row->attendance_date }} {{ $row->attendance_time }}
            @if($row->attendance_status['is_late'])
              <strong style="color:#a31515;">· RETARDO</strong>
            @else
              <strong style="color:#2f6b2f;">· PUNTUAL</strong>
            @endif
          </div>
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