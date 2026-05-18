@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Alumnos</h2>
    <form method="POST" action="{{ route('students.store') }}">
      @csrf
      <div>
        <input name="full_name" placeholder="Nombre completo" required>
        <input name="account_number" placeholder="Número de cuenta (obligatorio)" required>
        <input name="student_code" placeholder="Código (opcional)">
        <input name="classroom" placeholder="Grupo" required>
        <button type="submit">Crear alumno</button>
      </div>
    </form>

    @if(session('default_pw'))
      @php $pw = session('default_pw'); @endphp
      <div class="card">
        <strong>Alumno creado:</strong> {{ $pw['code'] }} — Contraseña por defecto: <code>{{ $pw['password'] }}</code>
        <p>Pídale al alumno que cambie su contraseña tras el primer ingreso.</p>
      </div>
    @endif

    <hr>
    <ul>
      @foreach($students as $s)
        <li>{{ $s->full_name }} — Cuenta: {{ $s->account_number ?? '-' }} — Código: {{ $s->student_code }} — {{ $s->classroom }} — <a href="{{ route('students.qr', $s->id) }}">QR</a></li>
      @endforeach
    </ul>
  </div>
@endsection
