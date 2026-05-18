@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Editar perfil</h2>
    <form method="POST" action="{{ route('student.profile.update') }}">
      @csrf
      <div>
        <input name="full_name" value="{{ $student->full_name }}" required>
      </div>
      <div>
        <input name="classroom" value="{{ $student->classroom }}" placeholder="Grupo">
      </div>
      <button type="submit">Guardar</button>
    </form>
    <p><a href="{{ route('student.change_password') }}">Cambiar contraseña</a></p>
  </div>
@endsection
