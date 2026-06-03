@extends('layouts.app')

@section('content')
<section class="grid grid-2">
  <article class="card">
    <p class="hero-eyebrow">Administración</p>
    <h2 class="card-title">Alumnos</h2>
    <p class="card-subtitle">Crea, revisa y elimina registros de alumnos.</p>

    @if(session('default_pw'))
      @php $pw = session('default_pw'); @endphp
      <div class="notice notice-success">
        Alumno creado: <strong>{{ $pw['code'] }}</strong> | Contraseña por defecto: <strong>{{ $pw['password'] }}</strong>
      </div>
    @endif

    @if($errors->any())
      <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('students.store') }}" class="form">
      @csrf
      <div class="form-grid">
        <label class="field">
          <span>Nombre completo</span>
          <input class="input" name="full_name" required>
        </label>

        <label class="field">
          <span>Número de cuenta</span>
          <input class="input" name="account_number" required>
        </label>

        <label class="field">
          <span>Grupo</span>
          <input class="input" name="classroom" required>
        </label>

        <label class="field">
          <span>Turno</span>
          <input class="input" name="shift" placeholder="Ej. Matutino, Vespertino">
        </label>
      </div>
      <button class="btn" type="submit">Dar de alta</button>
    </form>
  </article>

  <article class="card">
    <h3 class="card-title">Lista de alumnos</h3>

    <section class="student-toolbar">
      <div class="student-toolbar-head">
        <div>
          <p class="student-toolbar-kicker">Filtros</p>
          <h4 class="student-toolbar-title">Busca y ordena alumnos</h4>
        </div>
      </div>

      <form method="get" action="{{ route('students.index') }}" class="student-filter-grid">
        <label class="field student-filter-field student-filter-field-wide">
          <span>Número de cuenta</span>
          <input class="input" name="search" value="{{ $search }}" placeholder="Ej. 202208463">
        </label>

        <div class="student-filter-actions">
          <button class="btn" type="submit">Filtrar</button>
          <a class="btn btn-secondary" href="{{ route('students.index') }}">Limpiar</a>
        </div>
      </form>
    </section>

    @if($students->isEmpty())
      <div class="notice notice-error">No se encontraron alumnos con los filtros actuales.</div>
    @else
      <div class="notice notice-success" style="margin-bottom: 1rem;">
        {{ $students->count() }} alumno(s) encontrados.
      </div>
    @endif

    @if(! $students->isEmpty())
      <div class="student-groups">
        @foreach($groupedStudents as $groupName => $shifts)
          <section class="student-group-card">
            <div class="student-group-header">
              <div>
                <h4 class="student-group-title">Grupo {{ $groupName }}</h4>
                <p class="student-group-subtitle">{{ $shifts->sum(fn($items) => $items->count()) }} alumno(s)</p>
              </div>
            </div>

            @foreach($shifts as $shiftName => $groupStudents)
              <div class="student-shift-block">
                <h5 class="student-shift-title">Turno {{ $shiftName }}</h5>

                <div class="table-wrap">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Nombre</th>
                        <th>Cuenta</th>
                        <th>Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($groupStudents as $student)
                        <tr>
                          <td>{{ $student->full_name }}</td>
                          <td>{{ $student->account_number ?? '—' }}</td>
                          <td>
                            <div class="actions inline-actions">
                              <a class="btn btn-secondary" href="{{ route('students.qr', $student) }}">QR</a>
                              <a class="btn btn-secondary" href="{{ route('students.details', $student) }}">Detalles</a>
                              <form method="post" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('¿Eliminar este alumno?');">
                                @csrf
                                <button class="btn btn-danger" type="submit">Eliminar</button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endforeach
          </section>
        @endforeach
      </div>
    @endif
  </article>
</section>
@endsection
