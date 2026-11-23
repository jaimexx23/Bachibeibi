@extends('layouts.app')

@section('content')
<section class="card">
  <div class="card-head">
    <div>
      <p class="hero-eyebrow">Administración</p>
      <h2 class="card-title">Suspensiones</h2>
    </div>
  </div>

  @if($error)
    <div class="notice notice-error">{{ $error }}</div>
  @endif

  @if($success)
    <div class="notice notice-success">{{ $success }}</div>
  @endif

  <form method="get" action="{{ route('suspensions') }}" class="form-inline" style="margin-top: 1rem;">
    <input
      class="input"
      name="search"
      value="{{ $search }}"
      placeholder="Nombre, código o número de cuenta"
    >
    <button class="btn" type="submit">Buscar</button>
  </form>

  @if($activeSuspensions->isNotEmpty())
    <h3 class="card-title" style="margin-top: 1.5rem;">Suspensiones activas</h3>
    <div class="list">
      @foreach($activeSuspensions as $suspension)
        <div class="list-item">
          <div>
            <strong>{{ $suspension->full_name }}</strong>
            <div class="muted">
              {{ $suspension->student_code }} · {{ $suspension->classroom }}
            </div>
            <div class="muted">
              {{ $suspension->starts_at }} al {{ $suspension->ends_at }}
              @if($suspension->reason)
                · {{ $suspension->reason }}
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  @if($search !== '')
    <h3 class="card-title" style="margin-top: 1.5rem;">Resultados</h3>

    @forelse($students as $student)
      <article class="section-block">
        <div class="section-title">{{ $student->full_name }}</div>
        <p class="card-subtitle">
          {{ $student->student_code }} · {{ $student->account_number }} · {{ $student->classroom }}
        </p>

        @if($student->active_suspension)
          <div class="notice notice-error">
            Suspensión activa:
            {{ $student->active_suspension->starts_at }}
            al
            {{ $student->active_suspension->ends_at }}
            @if($student->active_suspension->reason)
              · {{ $student->active_suspension->reason }}
            @endif
          </div>

          <form method="post" action="{{ route('suspensions') }}" class="form-inline">
            @csrf
            <input type="hidden" name="action" value="lift">
            <input type="hidden" name="student_id" value="{{ $student->id }}">
            <button class="btn btn-secondary" type="submit">Levantar suspensión</button>
          </form>
        @else
          <form method="post" action="{{ route('suspensions') }}" class="form">
            @csrf
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="student_id" value="{{ $student->id }}">
            <div class="form-grid">
              <label class="field">
                <span>Inicio</span>
                <input class="input" type="date" name="starts_at" value="{{ old('starts_at', now()->toDateString()) }}" required>
              </label>
              <label class="field">
                <span>Fin</span>
                <input class="input" type="date" name="ends_at" value="{{ old('ends_at', now()->toDateString()) }}" required>
              </label>
            </div>
            <label class="field">
              <span>Motivo</span>
              <textarea class="input" name="reason" rows="2">{{ old('reason') }}</textarea>
            </label>
            <button class="btn" type="submit">Aplicar suspensión</button>
          </form>
        @endif
      </article>
    @empty
      <div class="notice notice-info">No se encontraron alumnos.</div>
    @endforelse
  @endif
</section>
@endsection
