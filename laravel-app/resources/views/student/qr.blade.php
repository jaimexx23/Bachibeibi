@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>QR de {{ $student->full_name }}</h2>
    <div class="qr">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($qrUrl) }}" alt="QR">
    </div>
    <p>Código: {{ $student->student_code }}</p>
  </div>
@endsection
@extends('layouts.app')
@section('content')
<section class="card center">
  <h2>QR de alumno</h2>
  <p><strong>{{ $student->full_name }}</strong></p>
  <p>Codigo: {{ $student->student_code }} | Grupo: {{ $student->classroom }}</p>
  <img class="qr" src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($qrUrl) }}" alt="QR alumno">
  <p>Imprime o guarda este QR para el alumno.</p>
  <a class="btn" href="{{ route('student.register') }}">Volver</a>
</section>
@endsection
