# RealState MVP — Evaluador de Proyectos Inmobiliarios

MVP web para evaluar la viabilidad financiera de proyectos inmobiliarios.

## Stack

- **Backend:** PHP 8+, PDO/MySQL
- **Frontend:** HTML, CSS, JavaScript vanilla
- **Base de datos:** MySQL (XAMPP)

## Estructura

```
realstate/
├── backend/
│   ├── config/db.php          # Conexión PDO
│   ├── api/
│   │   ├── auth/              # login.php, register.php
│   │   ├── projects/          # CRUD proyectos + cálculo de métricas
│   │   └── comparables/       # CRUD comparables de mercado
│   └── db/schema.sql          # Esquema de base de datos
├── frontend/
│   ├── css/style.css
│   ├── js/
│   │   ├── api.js             # Capa fetch → backend
│   │   └── main.js            # Lógica de la SPA
│   └── index.html
├── .env.example
└── .gitignore
```

## Instalación local (XAMPP)

1. Clonar el repo en `C:\xampp\htdocs\realstate`
2. Copiar `.env.example` → `.env` y completar credenciales
3. Importar `backend/db/schema.sql` en MySQL (phpMyAdmin o CLI)
4. Abrir `http://localhost/realstate/frontend/`

## Métricas calculadas por proyecto

| Métrica | Descripción |
|---------|-------------|
| Inversión total | Terreno + construcción + otros costos |
| Ingresos totales | Precio venta/m² × superficie construible |
| Ganancia bruta | Ingresos − Inversión |
| ROI | Ganancia / Inversión × 100 |
| Precio de equilibrio | Inversión / superficie construible |
