@extends('layouts.app')

@section('content')
<section class="grid grid-2">
  <article class="card">
    <p class="hero-eyebrow">Escáner</p>
    <h2 class="card-title">Registrar asistencia</h2>
    <p class="card-subtitle">Usa la cámara o escribe el código manualmente.</p>

    <div id="reader" class="reader-box">
      <div id="scanFeedback" class="scan-feedback" aria-live="polite"></div>
      <div id="readerInstruction" class="reader-instruction">Apunta el QR aquí — acerca tu teléfono hasta que el código quede dentro del recuadro</div>
    </div>
    <div id="lastScanned" class="last-scanned" aria-live="polite" hidden></div>
    <!-- debug panel removed -->
    <style>
      /* Hide html5-qrcode secure-context banner inside reader */
      #reader .html5-qrcode-status, #reader .html5-qrcode-error, #reader .html5-qrcode-info {
        display: none !important;
      }
    </style>
    <input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
    <div style="margin-top:8px;">
      <label class="field">Subir imagen de QR (si la cámara no funciona):</label>
      <input type="file" id="qrFileInput" accept="image/*">
      <button class="btn" type="button" id="scanFileBtn">Escanear imagen</button>
    </div>
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
      <noscript>
        <form method="POST" action="{{ route('api.checkin') }}">
          @csrf
          <input type="hidden" name="student_code" id="manualCodeNoJs" placeholder="ALUMNO: ABC123">
          <button class="btn" type="submit">Enviar (sin JavaScript)</button>
        </form>
      </noscript>
    </div>

    <div class="result" id="result"></div>
  </article>
</section>
@endsection
