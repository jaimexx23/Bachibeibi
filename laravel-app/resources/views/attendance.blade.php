@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Asistencias</h2>
    <p>Listado de asistencias registradas (hoy)</p>
    @php
      $rows = \DB::table('attendances')->where('date', now()->toDateString())->get();
    @endphp
    <ul>
    @foreach($rows as $r)
      <li>{{ $r->checked_at }} — {{ $r->student_code }} — {{ $r->student_name }} — {{ $r->classroom }} ({{ $r->source }})</li>
    @endforeach
    </ul>
  </div>
@endsection
