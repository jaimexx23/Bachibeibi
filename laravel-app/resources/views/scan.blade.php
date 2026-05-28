@extends('layouts.app')

@section('content')
<section class="grid grid-2">
  <article class="card">
    <p class="hero-eyebrow">Escáner</p>
    <h2 class="card-title">Registrar asistencia</h2>
    <p class="card-subtitle">Usa la cámara o escribe el código manualmente.</p>

    <div id="reader" class="reader-box"></div>
    <input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
    <div class="notice notice-info">Si el escáner no carga, revisa que el navegador tenga permiso de cámara.</div>
  </article>

  <article class="card">
    <h3 class="card-title">Entrada manual</h3>
    <div class="form">
      <label class="field">
        <span>Código o URL</span>
        <input class="input" id="manualCode" placeholder="ALUMNO: ABC123">
      </label>
      <button class="btn" type="button" onclick="manualCheckin()">Enviar</button>
    </div>

    <div class="result" id="result"></div>
  </article>
</section>
@endsection
