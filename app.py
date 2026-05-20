import base64
import io
import os
import re
import socket
import sqlite3
from functools import wraps
from urllib.parse import parse_qs, urlparse
from datetime import datetime

import qrcode
from flask import Flask, jsonify, redirect, render_template, request, session, url_for
from werkzeug.security import check_password_hash, generate_password_hash

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATABASE_PATH = os.path.join(BASE_DIR, "attendance.db")
DEFAULT_ADMIN_USERNAME = os.environ.get("ADMIN_USERNAME", "admin")
DEFAULT_ADMIN_PASSWORD = os.environ.get("ADMIN_PASSWORD", "admin123")
DEFAULT_STUDENT_PASSWORD = os.environ.get("DEFAULT_STUDENT_PASSWORD", "alumno123")

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "dev-secret-change-me")


# ---------- Database helpers ----------
def get_db_connection() -> sqlite3.Connection:
    conn = sqlite3.connect(DATABASE_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def get_local_ip() -> str:
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as sock:
            sock.connect(("8.8.8.8", 80))
            return sock.getsockname()[0]
    except OSError:
        try:
            hostname = socket.gethostname()
            host_ip = socket.gethostbyname(hostname)
            if host_ip and not host_ip.startswith("127."):
                return host_ip
        except OSError:
            pass
        return "127.0.0.1"


def get_external_base_url() -> str:
    external_host = os.environ.get("EXTERNAL_HOST")
    external_scheme = os.environ.get("EXTERNAL_SCHEME", "http")
    if external_host:
        return f"{external_scheme}://{external_host.rstrip('/')}"

    host_url = request.host_url.rstrip("/")
    if host_url.startswith(("http://127.", "https://127.", "http://localhost", "https://localhost")):
        local_ip = get_local_ip()
        scheme = request.environ.get("wsgi.url_scheme", request.scheme) or external_scheme
        server_port = request.environ.get("SERVER_PORT", "5000")
        return f"{scheme}://{local_ip}:{server_port}"
    return host_url


def build_external_url(endpoint: str, **values) -> str:
    base_url = get_external_base_url()
    path = url_for(endpoint, _external=False, **values)
    return f"{base_url}{path}"


def init_db() -> None:
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute(
        """
        CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            student_code TEXT NOT NULL UNIQUE,
            classroom TEXT NOT NULL,
            created_at TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'student'
        )
        """
    )

    cur.execute("PRAGMA table_info(students)")
    student_columns = {row[1] for row in cur.fetchall()}
    if "password_hash" not in student_columns:
        cur.execute("ALTER TABLE students ADD COLUMN password_hash TEXT")
    if "role" not in student_columns:
        cur.execute("ALTER TABLE students ADD COLUMN role TEXT NOT NULL DEFAULT 'student'")
    if "default_password" not in student_columns:
        cur.execute("ALTER TABLE students ADD COLUMN default_password INTEGER NOT NULL DEFAULT 0")

    cur.execute("UPDATE students SET role = 'student' WHERE role IS NULL OR TRIM(role) = ''")
    cur.execute("UPDATE students SET default_password = 0 WHERE default_password IS NULL")

    cur.execute(
        """
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )
        """
    )

    cur.execute("SELECT id, password_hash FROM admins WHERE username = ?", (DEFAULT_ADMIN_USERNAME,))
    admin_row = cur.fetchone()
    if admin_row is None:
        cur.execute(
            """
            INSERT INTO admins (username, password_hash, created_at)
            VALUES (?, ?, ?)
            """,
            (DEFAULT_ADMIN_USERNAME, generate_password_hash(DEFAULT_ADMIN_PASSWORD), datetime.now().isoformat()),
        )
    elif not check_password_hash(admin_row["password_hash"], DEFAULT_ADMIN_PASSWORD):
        cur.execute(
            "UPDATE admins SET password_hash = ? WHERE id = ?",
            (generate_password_hash(DEFAULT_ADMIN_PASSWORD), admin_row["id"]),
        )

    cur.execute(
        """
        CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            attendance_date TEXT NOT NULL,
            attendance_time TEXT NOT NULL,
            source TEXT NOT NULL,
            FOREIGN KEY(student_id) REFERENCES students(id),
            UNIQUE(student_id, attendance_date)
        )
        """
    )

    conn.commit()
    conn.close()


@app.before_request
def ensure_db() -> None:
    init_db()


# ---------- Utility ----------
def make_qr_base64(payload: str) -> str:
    qr = qrcode.QRCode(box_size=8, border=2)
    qr.add_data(payload)
    qr.make(fit=True)
    img = qr.make_image(fill_color="black", back_color="white")

    buffer = io.BytesIO()
    try:
        img.save(buffer, format="PNG")
    except TypeError:
        # PyPNG backend does not accept the format keyword argument.
        img.save(buffer)
    encoded = base64.b64encode(buffer.getvalue()).decode("utf-8")
    return encoded


def student_login_required(view):
    @wraps(view)
    def wrapped_view(*args, **kwargs):
        if session.get("role") != "student" or "student_id" not in session:
            return redirect(url_for("student_login"))
        return view(*args, **kwargs)

    return wrapped_view


def admin_login_required(view):
    @wraps(view)
    def wrapped_view(*args, **kwargs):
        if session.get("role") != "admin" or "admin_id" not in session:
            return redirect(url_for("admin_login"))
        return view(*args, **kwargs)

    return wrapped_view


def get_logged_student():
    student_id = session.get("student_id")
    if not student_id:
        return None

    conn = get_db_connection()
    student = conn.execute(
        "SELECT id, full_name, student_code, classroom, role, password_hash, default_password FROM students WHERE id = ?",
        (student_id,),
    ).fetchone()
    conn.close()
    return student


def get_logged_admin():
    admin_id = session.get("admin_id")
    if not admin_id:
        return None

    conn = get_db_connection()
    admin = conn.execute("SELECT id, username FROM admins WHERE id = ?", (admin_id,)).fetchone()
    conn.close()
    return admin


def extract_student_code(raw_value: str) -> str:
    """Extracts a student code from plain text, prefixed text, or URL payloads."""
    text = str(raw_value or "").strip()
    if not text:
        return ""

    if "://" in text:
        try:
            parsed = urlparse(text)
            query = parse_qs(parsed.query)
            for key in ("code", "student_code", "alumno", "id"):
                values = query.get(key)
                if values:
                    candidate = str(values[0]).strip().upper()
                    if candidate:
                        return candidate
            # Fallback: last path segment if URL is like /checkin/ABC123
            path_candidate = parsed.path.strip("/").split("/")[-1].strip().upper()
            if path_candidate and path_candidate not in {"SCAN", "CHECKIN", "ALUMNO"}:
                return path_candidate
        except Exception:
            pass

    # Accept formats like "ALUMNO: ABC123" or "CODE=ABC123".
    match = re.search(r"(?:ALUMNO|STUDENT(?:_CODE)?|CODIGO|CODE)\s*[:=\-]\s*([A-Z0-9\-_.]+)", text, re.IGNORECASE)
    if match:
        return match.group(1).strip().upper()

    # Fallback: use first token-like value.
    token = re.search(r"[A-Z0-9][A-Z0-9\-_.]{1,}", text.upper())
    return token.group(0).strip().upper() if token else ""


# ---------- Routes ----------
@app.route("/")
def home():
    return redirect(url_for("menu"))


@app.route("/menu", methods=["GET", "POST"])
def menu():
    error = None

    if session.get("role") == "admin" and session.get("admin_id"):
        return redirect(url_for("admin_dashboard"))
    if session.get("role") == "student" and session.get("student_id"):
        return redirect(url_for("student_dashboard"))

    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")

        if not username or not password:
            error = "Ingresa usuario y contrasena."
        else:
            conn = get_db_connection()
            admin = conn.execute(
                "SELECT id, username, password_hash FROM admins WHERE LOWER(username) = LOWER(?)",
                (username,),
            ).fetchone()
            student = conn.execute(
                "SELECT id, student_code, password_hash FROM students WHERE LOWER(student_code) = LOWER(?) AND role = 'student'",
                (username,),
            ).fetchone()
            conn.close()

            if admin and check_password_hash(admin["password_hash"], password):
                session.clear()
                session["role"] = "admin"
                session["admin_id"] = admin["id"]
                session["admin_username"] = admin["username"]
                return redirect(url_for("admin_dashboard"))

            if student and student["password_hash"] and check_password_hash(student["password_hash"], password):
                session.clear()
                session["role"] = "student"
                session["student_id"] = student["id"]
                return redirect(url_for("student_dashboard"))

            error = "Usuario o contrasena incorrectos."

    return render_template("home.html", error=error)


@app.route("/admin/login", methods=["GET", "POST"])
def admin_login():
    return redirect(url_for("menu"))


@app.route("/student/login", methods=["GET", "POST"])
def student_login():
    return redirect(url_for("menu"))


@app.route("/admin/logout")
def admin_logout():
    session.clear()
    return redirect(url_for("menu"))


@app.route("/admin", methods=["GET", "POST"])
@admin_login_required
def admin_dashboard():
    conn = get_db_connection()
    student_count = conn.execute("SELECT COUNT(*) AS total FROM students WHERE role = 'student'").fetchone()["total"]
    attendance_count = conn.execute("SELECT COUNT(*) AS total FROM attendance").fetchone()["total"]
    recent_students = conn.execute(
        "SELECT id, full_name, student_code, classroom, role FROM students WHERE role = 'student' ORDER BY id DESC LIMIT 5"
    ).fetchall()

    reset_error = None
    reset_success = None

    if request.method == "POST" and request.form.get("action") == "reset_password":
        target_user = request.form.get("recovery_user", "").strip()
        new_password = request.form.get("recovery_password", "")
        confirm_password = request.form.get("recovery_confirm", "")

        if not target_user or not new_password or not confirm_password:
            reset_error = "Completa todos los campos para restablecer la contraseña."
        elif new_password != confirm_password:
            reset_error = "Las contraseñas no coinciden."
        else:
            target_user = target_user.upper()
            admin_row = conn.execute(
                "SELECT id FROM admins WHERE UPPER(username) = ?",
                (target_user,),
            ).fetchone()
            student_row = None
            if not admin_row:
                student_row = conn.execute(
                    "SELECT id FROM students WHERE UPPER(student_code) = ?",
                    (target_user,),
                ).fetchone()

            if admin_row:
                conn.execute(
                    "UPDATE admins SET password_hash = ? WHERE id = ?",
                    (generate_password_hash(new_password), admin_row["id"]),
                )
                conn.commit()
                reset_success = "Contraseña de administrador restablecida correctamente."
            elif student_row:
                conn.execute(
                    "UPDATE students SET password_hash = ?, default_password = ? WHERE id = ?",
                    (generate_password_hash(new_password), 0, student_row["id"]),
                )
                conn.commit()
                reset_success = "Contraseña de alumno restablecida correctamente."
            else:
                reset_error = "No se encontró ningún usuario con ese nombre o número de cuenta."

    conn.close()

    return render_template(
        "admin_dashboard.html",
        admin=get_logged_admin(),
        student_count=student_count,
        attendance_count=attendance_count,
        recent_students=recent_students,
        reset_error=reset_error,
        reset_success=reset_success,
    )


@app.route("/student")
def student_home():
    if session.get("role") == "admin" and session.get("admin_id"):
        return redirect(url_for("admin_dashboard"))
    if session.get("student_id"):
        return redirect(url_for("student_dashboard"))
    return redirect(url_for("menu"))


@app.route("/student/register", methods=["GET", "POST"])
def student_register():
    error = None

    if request.method == "POST":
        full_name = request.form.get("full_name", "").strip()
        student_code = request.form.get("student_code", "").strip().upper()
        classroom = request.form.get("classroom", "").strip().upper()
        password = request.form.get("password", "")
        confirm_password = request.form.get("confirm_password", "")

        if not full_name or not student_code or not classroom or not password:
            error = "Completa todos los campos."
        elif password != confirm_password:
            error = "Las contrasenas no coinciden."
        else:
            conn = get_db_connection()
            try:
                cursor = conn.execute(
                    """
                    INSERT INTO students (full_name, student_code, classroom, created_at, password_hash, role)
                    VALUES (?, ?, ?, ?, ?, ?)
                    """,
                    (
                        full_name,
                        student_code,
                        classroom,
                        datetime.now().isoformat(),
                        generate_password_hash(password),
                        "student",
                    ),
                )
                conn.commit()
                session.clear()
                session["role"] = "student"
                session["student_id"] = cursor.lastrowid
                conn.close()
                return redirect(url_for("student_dashboard"))
            except sqlite3.IntegrityError:
                conn.close()
                error = "Ese numero de cuenta ya esta registrado."

    return render_template("student_register.html", error=error)


@app.route("/student/logout")
def student_logout():
    session.clear()
    return redirect(url_for("menu"))


@app.route("/student/dashboard", methods=["GET", "POST"])
@student_login_required
def student_dashboard():
    student = get_logged_student()
    if not student:
        session.clear()
        return redirect(url_for("menu"))

    error = None
    success = None

    if request.method == "POST":
        password = request.form.get("password", "")
        confirm_password = request.form.get("confirm_password", "")

        if password and password != confirm_password:
            error = "Las contrasenas no coinciden."
        else:
            if password:
                conn = get_db_connection()
                try:
                    conn.execute(
                        "UPDATE students SET password_hash = ?, default_password = ? WHERE id = ?",
                        (generate_password_hash(password), 0, student["id"]),
                    )
                    conn.commit()
                    student = conn.execute(
                        "SELECT id, full_name, student_code, classroom, role, password_hash, default_password FROM students WHERE id = ?",
                        (student["id"],),
                    ).fetchone()
                    success = "Contraseña actualizada correctamente."
                finally:
                    conn.close()
            else:
                success = "No se realizaron cambios."

    student_default_password = student["default_password"] if "default_password" in student.keys() else 0
    student_password_hash = student["password_hash"] if "password_hash" in student.keys() else None

    password_needs_update = (
        student_default_password == 1
        or (student_password_hash and check_password_hash(student_password_hash, DEFAULT_STUDENT_PASSWORD))
    )

    qr_payload = build_external_url("student_pass", student_code=student["student_code"])
    qr_base64 = make_qr_base64(qr_payload)
    return render_template(
        "student_dashboard.html",
        student=student,
        error=error,
        success=success,
        qr_base64=qr_base64,
        qr_payload=qr_payload,
        password_needs_update=password_needs_update,
    )


@app.route("/students", methods=["GET", "POST"])
@admin_login_required
def students():
    conn = get_db_connection()

    if request.method == "POST":
        full_name = request.form.get("full_name", "").strip()
        student_code = request.form.get("student_code", "").strip().upper()
        classroom = request.form.get("classroom", "").strip().upper()

        if full_name and student_code and classroom:
            try:
                conn.execute(
                    """
                    INSERT INTO students (full_name, student_code, classroom, created_at, password_hash, role)
                    VALUES (?, ?, ?, ?, ?, ?)
                    """,
                    (
                        full_name,
                        student_code,
                        classroom,
                        datetime.now().isoformat(),
                        generate_password_hash(DEFAULT_STUDENT_PASSWORD),
                        "student",
                    ),
                )
                conn.commit()
            except sqlite3.IntegrityError:
                pass

        conn.close()
        return redirect(url_for("students"))

    rows = conn.execute(
        "SELECT id, full_name, student_code, classroom, role FROM students ORDER BY full_name"
    ).fetchall()
    conn.close()
    return render_template(
        "students.html",
        students=rows,
        default_student_password=DEFAULT_STUDENT_PASSWORD,
    )


@app.route("/students/<int:student_id>/delete", methods=["POST"])
@admin_login_required
def delete_student(student_id: int):
    conn = get_db_connection()
    conn.execute(
        "DELETE FROM students WHERE id = ? AND role = 'student'",
        (student_id,),
    )
    conn.commit()
    conn.close()
    return redirect(url_for("students"))


@app.route("/students/<int:student_id>/qr")
@admin_login_required
def student_qr(student_id: int):
    conn = get_db_connection()
    student = conn.execute(
        "SELECT full_name, student_code, classroom FROM students WHERE id = ? AND role = 'student'",
        (student_id,),
    ).fetchone()
    conn.close()

    if not student:
        return "Alumno no encontrado", 404

    qr_payload = build_external_url("student_pass", student_code=student["student_code"])
    qr_base64 = make_qr_base64(qr_payload)

    return render_template("student_qr.html", student=student, qr_base64=qr_base64)


@app.route("/qr/<student_code>")
def student_pass(student_code: str):
    conn = get_db_connection()
    student = conn.execute(
        "SELECT full_name, student_code, classroom FROM students WHERE student_code = ?",
        (student_code.strip().upper(),),
    ).fetchone()
    conn.close()

    if not student:
        return "Alumno no encontrado", 404

    qr_payload = build_external_url("student_pass", student_code=student["student_code"])
    qr_base64 = make_qr_base64(qr_payload)
    return render_template("student_qr.html", student=student, qr_base64=qr_base64)


@app.route("/scan")
@admin_login_required
def scan():
    return render_template("scan.html")


@app.route("/api/checkin", methods=["POST"])
@admin_login_required
def checkin():
    data = request.get_json(silent=True) or {}
    raw_payload = data.get("student_code", "")
    student_code = extract_student_code(str(raw_payload))
    source = str(data.get("source", "qr")).strip().lower() or "qr"

    if not student_code:
        return jsonify({"ok": False, "message": "Codigo vacio"}), 400

    conn = get_db_connection()
    student = conn.execute(
        "SELECT id, full_name, classroom FROM students WHERE student_code = ?",
        (student_code,),
    ).fetchone()

    if not student:
        conn.close()
        return jsonify({"ok": False, "message": "Alumno no registrado"}), 404

    today = datetime.now().strftime("%Y-%m-%d")
    now_time = datetime.now().strftime("%H:%M:%S")

    try:
        conn.execute(
            """
            INSERT INTO attendance (student_id, attendance_date, attendance_time, source)
            VALUES (?, ?, ?, ?)
            """,
            (student["id"], today, now_time, source),
        )
        conn.commit()
        conn.close()
        return jsonify(
            {
                "ok": True,
                "message": "Asistencia registrada",
                "student": student["full_name"],
                "classroom": student["classroom"],
                "time": now_time,
                "date": today,
            }
        )
    except sqlite3.IntegrityError:
        conn.close()
        return jsonify(
            {
                "ok": False,
                "message": "Asistencia ya registrada hoy",
                "student": student["full_name"],
                "classroom": student["classroom"],
                "date": today,
            }
        ), 409


@app.route("/attendance")
@admin_login_required
def attendance():
    selected_date = request.args.get("date", datetime.now().strftime("%Y-%m-%d"))

    conn = get_db_connection()
    rows = conn.execute(
        """
        SELECT s.full_name, s.student_code, s.classroom, a.attendance_time, a.attendance_date
        FROM attendance a
        JOIN students s ON s.id = a.student_id
        WHERE a.attendance_date = ?
        ORDER BY s.classroom, s.full_name
        """,
        (selected_date,),
    ).fetchall()
    conn.close()

    return render_template("attendance.html", records=rows, selected_date=selected_date)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
