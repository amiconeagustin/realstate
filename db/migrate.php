<?php
/**
 * Migrador de una sola ejecución.
 * Accedé vía: https://api.amicone.com.ar/db/migrate.php?token=RUN_MIGRATE
 * Se elimina solo después de ejecutarse exitosamente.
 */

// Token de protección mínima para evitar ejecución accidental
$token = $_GET['token'] ?? '';
if ($token !== 'RUN_MIGRATE') {
    http_response_code(403);
    echo '<p>Acceso denegado. Usá ?token=RUN_MIGRATE para ejecutar.</p>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;padding:1rem">';
echo "=== Migrador RealState ===\n\n";

// Conectar a la base de datos mediante config/db.php
require_once __DIR__ . '/../config/db.php';
echo "✔ Conexión a la base de datos OK\n\n";

// Leer el schema SQL
$sqlFile = __DIR__ . '/schema.sql';
if (!file_exists($sqlFile)) {
    echo "✘ No se encontró schema.sql en " . $sqlFile . "\n";
    echo '</pre>';
    exit;
}
$sql = file_get_contents($sqlFile);

// Filtrar líneas que no aplican en cPanel:
// - CREATE DATABASE (la DB ya existe, no hay permisos para crearla)
// - USE <db> (PDO ya está conectado a la DB correcta vía .env)
$lines = explode("\n", $sql);
$filtered = array_filter($lines, function ($line) {
    $trimmed = trim($line);
    return stripos($trimmed, 'CREATE DATABASE') === false
        && stripos($trimmed, 'USE ')           === false;
});
$cleanSql = implode("\n", $filtered);

// Dividir en sentencias individuales por ";"
$statements = array_filter(
    array_map('trim', explode(';', $cleanSql)),
    fn($s) => $s !== '' && !preg_match('/^(--)/', $s)
);

$ok    = 0;
$error = 0;
foreach ($statements as $stmt) {
    // Saltar bloques que sean solo comentarios
    if (preg_match('/^[\s\-]*$/', $stmt)) continue;

    try {
        $pdo->exec($stmt);
        // Extraer nombre de la tabla para el log
        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $stmt, $matches);
        $table = $matches[1] ?? '(sentencia)';
        echo "✔ Tabla creada / verificada: $table\n";
        $ok++;
    } catch (PDOException $e) {
        echo "✘ Error: " . $e->getMessage() . "\n";
        $error++;
    }
}

echo "\n--- Resultado: $ok OK, $error errores ---\n";

if ($error === 0) {
    // Auto-eliminar este archivo
    $self = __FILE__;
    echo "\n🗑  Eliminando migrate.php del servidor…\n";
    if (unlink($self)) {
        echo "✔ migrate.php eliminado. El servidor está listo.\n";
    } else {
        echo "⚠ No se pudo eliminar migrate.php automáticamente. Eliminalo manualmente.\n";
    }
} else {
    echo "\n⚠ Hubo errores. migrate.php NO fue eliminado para que puedas revisar.\n";
}

echo '</pre>';
