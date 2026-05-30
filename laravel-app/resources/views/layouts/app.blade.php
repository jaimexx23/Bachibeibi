<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name', 'BachilleresQR') }}</title>
  <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
  <div class="shell">
    <header class="topbar">
      <div>
        <div class="brand">BachilleresQR</div>
        <div class="brand-subtitle">Asistencia y QR con Laravel</div>
      </div>

      <nav class="nav">
        @unless(request()->routeIs('menu'))
          @if(session('role') === 'scanner' || session()->has('scanner_id'))
          <a class="nav-link {{ request()->routeIs('scan') ? 'active' : '' }}" href="{{ route('scan') }}">Escanear</a>
          <a class="nav-link {{ request()->routeIs('attendance') ? 'active' : '' }}" href="{{ route('attendance') }}">Asistencias</a>
          <a class="nav-link" href="{{ route('scanner.logout') }}">Salir</a>

        @elseif(session('role') === 'student' || session()->has('student_id'))
          <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}">Mi panel</a>
          <a class="nav-link" href="{{ route('student.logout') }}">Salir</a>

        @elseif(session('role') === 'admin' || session()->has('admin_id'))
          <a class="nav-link {{ request()->routeIs('menu') ? 'active' : '' }}" href="{{ route('menu') }}">Inicio</a>
          <a class="nav-link {{ request()->routeIs('students.index') ? 'active' : '' }}" href="{{ route('students.index') }}">Alumnos</a>
          <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Admin</a>
          <a class="nav-link {{ request()->routeIs('scan') ? 'active' : '' }}" href="{{ route('scan') }}">Escanear</a>
          <a class="nav-link {{ request()->routeIs('attendance') ? 'active' : '' }}" href="{{ route('attendance') }}">Asistencias</a>
          <a class="nav-link" href="{{ route('admin.logout') }}">Salir</a>

          @else
            <a class="nav-link {{ request()->routeIs('menu') ? 'active' : '' }}" href="{{ route('menu') }}">Inicio</a>
            <a class="nav-link {{ request()->routeIs('students.index') ? 'active' : '' }}" href="{{ route('students.index') }}">Alumnos</a>
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Admin</a>
            <a class="nav-link {{ request()->routeIs('scan') ? 'active' : '' }}" href="{{ route('scan') }}">Escanear</a>
            <a class="nav-link {{ request()->routeIs('attendance') ? 'active' : '' }}" href="{{ route('attendance') }}">Asistencias</a>
          @endif
        @endunless
      </nav>
    </header>

    <main class="container">
      @yield('content')
    </main>
  </div>

  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <script src="/js/scan.js"></script>
</body>
</html>
