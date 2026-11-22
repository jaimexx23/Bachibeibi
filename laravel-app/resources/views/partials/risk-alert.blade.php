@if($riskStudents->isNotEmpty())
  <details class="risk-alert">
    <summary>
      <span class="risk-alert-dot" aria-hidden="true"></span>
      <span>
        <strong>Seguimiento requerido</strong>
        <span class="risk-alert-count">
          {{ $riskStudents->count() }}
          {{ $riskStudents->count() === 1 ? 'alumno' : 'alumnos' }}
        </span>
      </span>
    </summary>

    <div class="risk-alert-content">
      <p>Con 3 días escolares consecutivos sin asistencia.</p>
      <ul>
        @foreach($riskStudents as $riskStudent)
          <li>
            {{ $riskStudent->full_name }}
            <span>· {{ $riskStudent->student_code }} · {{ $riskStudent->classroom }}</span>
          </li>
        @endforeach
      </ul>
    </div>
  </details>
@endif
