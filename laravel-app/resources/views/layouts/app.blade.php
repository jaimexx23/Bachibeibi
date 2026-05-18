<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name', 'Asistencia QR') }}</title>
  <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
</head>
<body>
  <header class="topbar">
    <h1>BachilleresQR</h1>
    <nav class="main-nav">
      <a href="{{ url('/')}}">Inicio</a>
      <a href="{{ route('students.index') }}">Alumnos</a>
      <a href="{{ route('student.login') }}">Acceso alumnos</a>
      <a href="{{ route('student.register') }}">Registro alumnos</a>
      <a href="{{ url('/scan') }}">Escanear</a>
      <a href="{{ url('/attendance') }}">Asistencias</a>
    </nav>
  </header>

  <main class="container">
    @yield('content')
  </main>

  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <script src="{{ asset('js/scan.js') }}"></script>
</body>
</html>
