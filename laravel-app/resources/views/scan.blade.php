@extends('layouts.app')
@section('content')
  <div class="card">
    <h2>Escanear QR</h2>
    <div id="reader"></div>
    <div class="result" id="result"></div>
    <div style="margin-top:12px">
      <input id="manualCode" placeholder="Introducir código manualmente">
      <button onclick="manualCheckin()">Enviar</button>
      <input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
    </div>
  </div>
@endsection
