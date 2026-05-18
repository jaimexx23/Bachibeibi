# Sistema de Asistencia con QR (Preparatoria)

Proyecto web simple para registrar asistencia por QR:
- Registro de alumnos
- Inicio de sesion para alumnos
- Registro de cuenta para alumnos
- Generacion de QR por alumno
- Escaneo con camara desde navegador
- Registro de asistencia sin duplicar por dia
- Consulta por fecha

## Requisitos
- Python 3.10+

## Instalacion
```bash
python -m venv .venv
.venv\\Scripts\\activate
pip install -r requirements.txt
```

## Ejecucion
```bash
python app.py
```
Abrir en navegador: `http://127.0.0.1:5000`

## Flujo recomendado de uso
1. Ir a `Alumnos` y registrar estudiantes.
2. Ir a `Registro alumnos` si el alumno creara su propia cuenta, o usar `Alumnos` si el alta la hace un administrador.
3. Iniciar sesion en `Acceso alumnos` para ver el panel personal y su QR.
4. Abrir el QR de cada alumno y guardarlo/imprimirlo.
5. Ir a `Escanear` para tomar asistencia con camara.
6. Ver reportes del dia en `Asistencias`.

## Nota tecnica
La base de datos se guarda en `attendance.db` (SQLite) dentro del proyecto.
