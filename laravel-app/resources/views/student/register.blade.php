@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Registro alumno</h2>
    <form method="POST" action="{{ url('/student/register') }}">
      @csrf
      <div>
        <input name="full_name" placeholder="Nombre completo" required>
      </div>
      <div>
        <input name="student_code" placeholder="Código" required>
      </div>
      <div>
        <input name="classroom" placeholder="Grupo" required>
      </div>
      <div>
        <input type="password" name="password" placeholder="Contraseña" required>
        <input type="password" name="password_confirmation" placeholder="Confirmar contraseña" required>
      </div>
      <button type="submit">Registrarse</button>
    </form>
  </div>
@endsection
@extends('layouts.app')
@section('content')
<section class="grid single-panel">
  <article class="card">
    <h2>Registro de alumno</h2>
    <p>Crea tu cuenta para entrar a tu panel y mostrar tu codigo QR.</p>
    @if($errors->any())
      <div class="result"><div class="error">{{ $errors->first() }}</div></div>
    @endif
    <form method="post" class="form">@csrf
      <label>Nombre completo</label>
      <input name="full_name" required>
      <label>Codigo de alumno</label>
      <input name="student_code" required>
      <label>Grupo / Salon</label>
      <input name="classroom" required>
      <label>Contrasena</label>
      <input type="password" name="password" required>
      <label>Confirmar contrasena</label>
      <input type="password" name="password_confirmation" required>
      <button class="btn" type="submit">Registrar y entrar</button>
    </form>
  </article>
</section>
@endsection
