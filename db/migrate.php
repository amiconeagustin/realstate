<?php
/**
 * Migrador autocontenido — sin dependencias externas.
 * Subilo vía File Manager a cualquier carpeta pública y abrilo en el navegador.
 * URL de ejemplo: https://api.amicone.com.ar/db/migrate.php?token=RUN_MIGRATE
 * Se elimina solo si todas las sentencias ejecutan sin error.
 */

// ── Protección mínima contra ejecución accidental ──────────────────────────
if (($_GET['token'] ?? '') !== 'RUN_MIGRATE') {
    http_response_code(403);
    echo '<p style="font-family:monospace">Acceso denegado. Usá <code>?token=RUN_MIGRATE</code></p>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;line-height:1.6;padding:1.5rem">';
echo "=== Migrador RealState — " . date('Y-m-d H:i:s') . " ===\n\n";

// ── Credenciales de producción embebidas ───────────────────────────────────
$host   = 'localhost';
$dbname = 'apiamic1_realstate';
$user   = 'apiamic1_user';
$pass   = '7Opj_=,s-j2U,^zk';

// ── Conexión PDO ───────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✔ Conexión a '$dbname' OK\n\n";
} catch (PDOException $e) {
    echo "✘ Error de conexión: " . $e->getMessage() . "\n";
    echo '</pre>';
    exit;
}

// ── SQL embebido (sin CREATE DATABASE ni USE) ──────────────────────────────
$statements = [

    "CREATE TABLE IF NOT EXISTS users (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100)  NOT NULL,
        email         VARCHAR(150)  NOT NULL UNIQUE,
        password_hash VARCHAR(255)  NOT NULL,
        created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS projects (
        id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id              INT UNSIGNED  NOT NULL,
        name                 VARCHAR(200)  NOT NULL,
        location             VARCHAR(300)  NOT NULL,
        type                 ENUM('residencial','comercial','mixto') NOT NULL DEFAULT 'residencial',
        total_area_m2        DECIMAL(10,2) NOT NULL,
        buildable_area_m2    DECIMAL(10,2) NOT NULL,
        land_cost            DECIMAL(14,2) NOT NULL,
        construction_cost_m2 DECIMAL(10,2) NOT NULL,
        sale_price_m2        DECIMAL(10,2) NOT NULL,
        other_costs          DECIMAL(14,2) NOT NULL DEFAULT 0,
        status               ENUM('borrador','en_analisis','aprobado','rechazado') NOT NULL DEFAULT 'borrador',
        notes                TEXT,
        created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS comparables (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id   INT UNSIGNED  NOT NULL,
        address      VARCHAR(300)  NOT NULL,
        area_m2      DECIMAL(10,2) NOT NULL,
        total_price  DECIMAL(14,2) NOT NULL,
        price_per_m2 DECIMAL(10,2) GENERATED ALWAYS AS (total_price / area_m2) STORED,
        source       VARCHAR(200),
        sale_date    DATE,
        notes        TEXT,
        created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS project_metrics (
        id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id          INT UNSIGNED  NOT NULL UNIQUE,
        total_investment    DECIMAL(14,2) NOT NULL,
        total_revenue       DECIMAL(14,2) NOT NULL,
        gross_profit        DECIMAL(14,2) NOT NULL,
        roi_percent         DECIMAL(8,4)  NOT NULL,
        break_even_price_m2 DECIMAL(10,2) NOT NULL,
        calculated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

// ── Ejecutar cada sentencia ────────────────────────────────────────────────
$tableNames = ['users', 'projects', 'comparables', 'project_metrics'];
$errors = 0;

foreach ($statements as $i => $sql) {
    try {
        $pdo->exec($sql);
        echo "✔ Tabla '{$tableNames[$i]}' creada / ya existe\n";
    } catch (PDOException $e) {
        echo "✘ Error en '{$tableNames[$i]}': " . $e->getMessage() . "\n";
        $errors++;
    }
}

// ── Verificar tablas creadas ───────────────────────────────────────────────
echo "\n── Tablas en la base de datos ──\n";
$rows = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($rows as $t) {
    echo "   · $t\n";
}

// ── Auto-eliminar si todo OK ───────────────────────────────────────────────
echo "\n── Resultado: " . (count($statements) - $errors) . " OK, $errors errores ──\n";

if ($errors === 0) {
    echo "\n🗑  Eliminando migrate.php…\n";
    if (@unlink(__FILE__)) {
        echo "✔ migrate.php eliminado. El servidor está listo.\n";
    } else {
        echo "⚠ No se pudo eliminar automáticamente. Borralo manualmente desde File Manager.\n";
    }
} else {
    echo "\n⚠ Hubo errores. El archivo NO fue eliminado para que puedas revisar.\n";
}

echo '</pre>';
