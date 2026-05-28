@extends('layouts.app')

@section('content')
<section class="grid grid-2">
  <article class="card">
    <p class="hero-eyebrow">Registro de alumno</p>
    <h2 class="card-title">Crear cuenta</h2>
    <p class="card-subtitle">Tu alumno quedará con el rol <strong>student</strong> por defecto.</p>

    @if($errors->any())
      <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('student.register') }}" class="form">
      @csrf
      <label class="field">
        <span>Nombre completo</span>
        <input class="input" name="full_name" required>
      </label>

      <label class="field">
        <span>Código de alumno</span>
        <input class="input" name="student_code" required>
      </label>

      <label class="field">
        <span>Grupo</span>
        <input class="input" name="classroom" required>
      </label>

      <label class="field">
        <span>Contraseña</span>
        <input class="input" type="password" name="password" required>
      </label>

      <label class="field">
        <span>Confirmar contraseña</span>
        <input class="input" type="password" name="password_confirmation" required>
      </label>

      <button class="btn" type="submit">Registrar y entrar</button>
    </form>
  </article>

  <article class="card accent-card">
    <h3 class="card-title">Importante</h3>
    <p class="card-subtitle">Después del primer acceso podrás cambiar tu contraseña desde tu panel.</p>
  </article>
</section>
@endsection
