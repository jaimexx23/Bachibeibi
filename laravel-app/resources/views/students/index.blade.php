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
      <button class="btn" type="submit">Crear alumno</button>
    </form>
  </article>

  <article class="card">
    <h3 class="card-title">Lista de alumnos</h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Código</th>
            <th>Grupo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($students as $student)
            <tr>
              <td>{{ $student->full_name }}</td>
              <td>{{ $student->student_code }}</td>
              <td>{{ $student->classroom }}</td>
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
  </article>
</section>
@endsection
