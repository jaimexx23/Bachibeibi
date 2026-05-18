import base64
import io
import os
import re
import sqlite3
from functools import wraps
from urllib.parse import parse_qs, urlparse
from datetime import datetime

import qrcode
from flask import Flask, jsonify, redirect, render_template, request, session, url_for
from werkzeug.security import check_password_hash, generate_password_hash

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATABASE_PATH = os.path.join(BASE_DIR, "attendance.db")

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "dev-secret-change-me")


# ---------- Database helpers ----------
def get_db_connection() -> sqlite3.Connection:
    conn = sqlite3.connect(DATABASE_PATH)
    conn.row_factory = sqlite3.Row
    return conn


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
            created_at TEXT NOT NULL
        )
        """
    )

    cur.execute("PRAGMA table_info(students)")
    student_columns = {row[1] for row in cur.fetchall()}
    if "password_hash" not in student_columns:
        cur.execute("ALTER TABLE students ADD COLUMN password_hash TEXT")

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
        if "student_id" not in session:
            return redirect(url_for("student_login"))
        return view(*args, **kwargs)

    return wrapped_view


def get_logged_student():
    student_id = session.get("student_id")
    if not student_id:
        return None

    conn = get_db_connection()
    student = conn.execute(
        "SELECT id, full_name, student_code, classroom FROM students WHERE id = ?",
        (student_id,),
    ).fetchone()
    conn.close()
    return student


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
    return redirect(url_for("scan"))


@app.route("/menu")
def menu():
    return render_template("home.html")


@app.route("/student")
def student_home():
    if session.get("student_id"):
        return redirect(url_for("student_dashboard"))
    return redirect(url_for("student_login"))


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
                    INSERT INTO students (full_name, student_code, classroom, created_at, password_hash)
                    VALUES (?, ?, ?, ?, ?)
                    """,
                    (
                        full_name,
                        student_code,
                        classroom,
                        datetime.now().isoformat(),
                        generate_password_hash(password),
                    ),
                )
                conn.commit()
                session["student_id"] = cursor.lastrowid
                conn.close()
                return redirect(url_for("student_dashboard"))
            except sqlite3.IntegrityError:
                conn.close()
                error = "Ese codigo de alumno ya esta registrado."

    return render_template("student_register.html", error=error)


@app.route("/student/login", methods=["GET", "POST"])
def student_login():
    error = None

    if session.get("student_id"):
        return redirect(url_for("student_dashboard"))

    if request.method == "POST":
        student_code = request.form.get("student_code", "").strip().upper()
        password = request.form.get("password", "")

        if not student_code or not password:
            error = "Ingresa tu codigo y contrasena."
        else:
            conn = get_db_connection()
            student = conn.execute(
                "SELECT id, password_hash FROM students WHERE student_code = ?",
                (student_code,),
            ).fetchone()
            conn.close()

            if student and student["password_hash"] and check_password_hash(student["password_hash"], password):
                session["student_id"] = student["id"]
                return redirect(url_for("student_dashboard"))

            error = "Codigo o contrasena incorrectos."

    return render_template("student_login.html", error=error)


@app.route("/student/logout")
def student_logout():
    session.pop("student_id", None)
    return redirect(url_for("student_login"))


@app.route("/student/dashboard")
@student_login_required
def student_dashboard():
    student = get_logged_student()
    if not student:
        session.pop("student_id", None)
        return redirect(url_for("student_login"))

    qr_payload = url_for("student_pass", student_code=student["student_code"], _external=True)
    qr_base64 = make_qr_base64(qr_payload)
    return render_template("student_dashboard.html", student=student, qr_base64=qr_base64, qr_payload=qr_payload)


@app.route("/students", methods=["GET", "POST"])
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
                    INSERT INTO students (full_name, student_code, classroom, created_at)
                    VALUES (?, ?, ?, ?)
                    """,
                    (full_name, student_code, classroom, datetime.now().isoformat()),
                )
                conn.commit()
            except sqlite3.IntegrityError:
                pass

        conn.close()
        return redirect(url_for("students"))

    rows = conn.execute(
        "SELECT id, full_name, student_code, classroom FROM students ORDER BY full_name"
    ).fetchall()
    conn.close()
    return render_template("students.html", students=rows)


@app.route("/students/<int:student_id>/qr")
def student_qr(student_id: int):
    conn = get_db_connection()
    student = conn.execute(
        "SELECT full_name, student_code, classroom FROM students WHERE id = ?",
        (student_id,),
    ).fetchone()
    conn.close()

    if not student:
        return "Alumno no encontrado", 404

    payload = url_for("student_pass", student_code=student["student_code"], _external=True)
    qr_base64 = make_qr_base64(payload)

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

    qr_payload = url_for("student_pass", student_code=student["student_code"], _external=True)
    qr_base64 = make_qr_base64(qr_payload)
    return render_template("student_qr.html", student=student, qr_base64=qr_base64)


@app.route("/scan")
def scan():
    return render_template("scan.html")


@app.route("/api/checkin", methods=["POST"])
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
