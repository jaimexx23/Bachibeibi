@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Acceso alumno</h2>
    <form method="POST" action="{{ url('/student/login') }}">
      @csrf
      <div>
        <input name="student_code" placeholder="Código" required>
      </div>
      <div>
        <input type="password" name="password" placeholder="Contraseña" required>
      </div>
      <button type="submit">Entrar</button>
    </form>
  </div>
@endsection
@extends('layouts.app')
@section('content')
<section class="grid single-panel">
  <article class="card">
    <h2>Inicio de sesión de alumno</h2>
    @if($errors->any())
      <div class="result"><div class="error">{{ $errors->first() }}</div></div>
    @endif
    <form method="post" class="form">@csrf
      <label>Codigo de alumno</label>
      <input name="student_code" required>
      <label>Contrasena</label>
      <input type="password" name="password" required>
      <button class="btn" type="submit">Entrar</button>
    </form>
  </article>
</section>
@endsection
