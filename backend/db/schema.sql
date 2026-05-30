-- Esquema de base de datos para evaluación de proyectos inmobiliarios
-- MVP - RealState Viability Evaluator

CREATE DATABASE IF NOT EXISTS realstate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE realstate;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(150)        NOT NULL UNIQUE,
    password_hash VARCHAR(255)        NOT NULL,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tipo: 'residencial', 'comercial', 'mixto'
-- Estado: 'borrador', 'en_analisis', 'aprobado', 'rechazado'
CREATE TABLE IF NOT EXISTS projects (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED        NOT NULL,
    name                 VARCHAR(200)        NOT NULL,
    location             VARCHAR(300)        NOT NULL,
    type                 ENUM('residencial','comercial','mixto') NOT NULL DEFAULT 'residencial',
    total_area_m2        DECIMAL(10,2)       NOT NULL COMMENT 'Superficie total del terreno en m2',
    buildable_area_m2    DECIMAL(10,2)       NOT NULL COMMENT 'Superficie construible en m2',
    land_cost            DECIMAL(14,2)       NOT NULL COMMENT 'Costo del terreno en USD',
    construction_cost_m2 DECIMAL(10,2)       NOT NULL COMMENT 'Costo de construccion por m2 en USD',
    sale_price_m2        DECIMAL(10,2)       NOT NULL COMMENT 'Precio de venta por m2 en USD',
    other_costs          DECIMAL(14,2)       NOT NULL DEFAULT 0 COMMENT 'Honorarios, impuestos, comercializacion',
    status               ENUM('borrador','en_analisis','aprobado','rechazado') NOT NULL DEFAULT 'borrador',
    notes                TEXT,
    created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ventas comparables del mercado para validar el precio de venta
CREATE TABLE IF NOT EXISTS comparables (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id    INT UNSIGNED        NOT NULL,
    address       VARCHAR(300)        NOT NULL,
    area_m2       DECIMAL(10,2)       NOT NULL,
    total_price   DECIMAL(14,2)       NOT NULL,
    price_per_m2  DECIMAL(10,2)       GENERATED ALWAYS AS (total_price / area_m2) STORED,
    source        VARCHAR(200)        COMMENT 'URL o fuente del comparable',
    sale_date     DATE,
    notes         TEXT,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Métricas calculadas y guardadas por proyecto
CREATE TABLE IF NOT EXISTS project_metrics (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id          INT UNSIGNED NOT NULL UNIQUE,
    total_investment    DECIMAL(14,2) NOT NULL COMMENT 'Terreno + construccion + otros costos',
    total_revenue       DECIMAL(14,2) NOT NULL COMMENT 'Precio venta m2 * superficie construible',
    gross_profit        DECIMAL(14,2) NOT NULL COMMENT 'Ingresos - Inversión total',
    roi_percent         DECIMAL(8,4)  NOT NULL COMMENT 'Ganancia bruta / inversión total * 100',
    break_even_price_m2 DECIMAL(10,2) NOT NULL COMMENT 'Precio mínimo por m2 para no perder',
    calculated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
