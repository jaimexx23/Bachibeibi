@extends('layouts.app')

@section('content')
@include('partials.risk-alert')

<section class="card">
  <div class="card-head">
    <div>
      <p class="hero-eyebrow">Asistencias</p>
      <h2 class="card-title">Registro por fecha</h2>
    </div>

    <form method="get" action="{{ route('attendance') }}" class="form-inline">
      <input class="input" type="date" name="date" value="{{ $selectedDate }}">
      <button class="btn" type="submit">Filtrar</button>
    </form>
  </div>

  @forelse($attendanceSections as $section)
    <article class="section-block">
      <h3 class="section-title">{{ $section['label'] }}</h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Código</th>
              <th>Nombre</th>
              <th>Grupo</th>
              <th>Origen</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            @foreach($section['records'] as $row)
              <tr>
                <td>{{ $row->attendance_time }}</td>
                <td>{{ $row->student_code }}</td>
                <td>{{ $row->full_name }}</td>
                <td>{{ $row->classroom }}</td>
                <td>{{ $row->source }}</td>
                <td>
                  @if($row->attendance_status['is_late'])
                    <strong style="color:#a31515;">RETARDO</strong>
                  @else
                    <strong style="color:#2f6b2f;">PUNTUAL</strong>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </article>
  @empty
    <div class="notice notice-info">No hay asistencias para esa fecha.</div>
  @endforelse
</section>
@endsection
