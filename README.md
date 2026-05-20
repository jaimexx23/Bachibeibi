# Sistema de Asistencia con QR (Preparatoria)

Proyecto web simple para registrar asistencia por QR:
- Registro de alumnos
- Inicio de sesion para alumnos
- Registro de cuenta para alumnos
- Panel de administrador
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

## Roles
- `admin`: acceso total al sistema, puede administrar alumnos, escanear y ver asistencias.
- `student`: solo puede registrar o actualizar sus datos en su panel.

Los nuevos registros de alumnos se crean por defecto con el rol `student`.

## Acceso
Hay un solo login en `/login`. El sistema detecta automáticamente si el usuario ingresado pertenece al rol `admin` o `student`.

Credenciales iniciales del admin por defecto:
- Usuario: admin
- Contrasena: admin123

Puedes cambiar estos valores con las variables de entorno ADMIN_USERNAME y ADMIN_PASSWORD.

Ejemplo en PowerShell antes de ejecutar:
```powershell
$env:ADMIN_USERNAME = "tu_usuario"
$env:ADMIN_PASSWORD = "tu_contrasena_segura"
python app.py
```

## Flujo recomendado de uso
1. Entrar por `Acceso` o directamente en `/login`.
2. Registrar alumnos desde `Alumnos` o dejar que se registren desde `Registrarse`.
3. Usar el sistema según el rol detectado: admin para todo, student solo para sus datos.
4. Ir a `Escanear` para tomar asistencia con camara.
5. Ver reportes del dia en `Asistencias`.

## Nota tecnica
La base de datos se guarda en `attendance.db` (SQLite) dentro del proyecto.
