<?php
// Ejecutar desde la carpeta laravel-app con: php tools/import_flask.php

$flaskDb = __DIR__ . '/..' . '/..' . '/attendance.db';
$laravelDb = __DIR__ . '/..' . '/database' . '/database.sqlite';

if (!file_exists($flaskDb)) {
    echo "No se encontró attendance.db en la ruta esperada: $flaskDb\n";
    exit(1);
}

try {
    $src = new PDO('sqlite:' . $flaskDb);
    $src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "No se pudo abrir la DB de origen: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    $dst = new PDO('sqlite:' . $laravelDb);
    $dst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "No se pudo abrir la DB de destino: " . $e->getMessage() . "\n";
    exit(1);
}

// Intenta detectar la tabla de alumnos en la DB de Flask
$possibleTables = ['students', 'alumnos', 'student'];
$table = null;
foreach ($possibleTables as $t) {
    $res = $src->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $t . "'");
    if ($res && $res->fetch()) { $table = $t; break; }
}

if (!$table) {
    echo "No se encontró una tabla de alumnos conocida en la DB de Flask.\n";
    exit(1);
}

echo "Importando desde tabla: $table\n";

$rows = $src->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
$inserted = 0;
foreach ($rows as $r) {
    // Try to map fields
    $full = $r['full_name'] ?? ($r['name'] ?? ($r['fullname'] ?? null));
    $code = $r['student_code'] ?? ($r['code'] ?? ($r['codigo'] ?? null));
    $classroom = $r['classroom'] ?? ($r['grupo'] ?? ($r['class'] ?? null));
    $password = $r['password'] ?? ($r['password_hash'] ?? null);
    if (!$code) continue;
    $code = strtoupper(trim($code));
    $full = $full ? trim($full) : $code;
    $classroom = $classroom ? trim($classroom) : '';

    // Check exists in destination
    $stmt = $dst->prepare('SELECT count(1) as c FROM students WHERE student_code = ?');
    $stmt->execute([$code]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($c && $c['c'] > 0) continue;

    // Insert
    $ins = $dst->prepare('INSERT INTO students (full_name, student_code, classroom, password, created_at, updated_at) VALUES (?, ?, ?, ?, datetime("now"), datetime("now"))');
    $pw = $password ? $password : null;
    $ins->execute([$full, $code, $classroom, $pw]);
    $inserted++;
}

echo "Import completed. Inserted: $inserted\n";
