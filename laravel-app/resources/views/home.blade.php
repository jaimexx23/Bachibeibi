@extends('layouts.app')

@section('content')
<section class="hero">
  <p class="hero-eyebrow">Acceso central</p>
  <h1 class="hero-title">Entra al sistema con tu usuario y contraseña</h1>
  
</section>

<section class="home-grid">
  <div class="login-panel">
    <h2 class="card-title">Iniciar sesión</h2>
    @if(!empty($error))
      <div class="notice notice-error">{{ $error }}</div>
    @endif

    <form method="post" action="{{ route('menu') }}" class="form">
      @csrf
      <label class="field">
        <span>Usuario</span>
        <input class="input" name="username" autocomplete="username" required>
      </label>

      <label class="field">
        <span>Contraseña</span>
        <input class="input" type="password" name="password" autocomplete="current-password" required>
      </label>

      <button class="btn" type="submit">Entrar</button>
    </form>
  </div>

</section>
@endsection