@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Cambiar contraseña</h2>
    <form method="POST" action="{{ route('student.change_password.update') }}">
      @csrf
      <div>
        <input type="password" name="current_password" placeholder="Contraseña actual" required>
      </div>
      <div>
        <input type="password" name="password" placeholder="Nueva contraseña" required>
        <input type="password" name="password_confirmation" placeholder="Confirmar nueva contraseña" required>
      </div>
      <button type="submit">Cambiar</button>
    </form>
  </div>
@endsection
