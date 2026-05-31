// ===== Estado =====
let lastResult = null;

// ===== Utilidades =====
function usd(n) {
    if (n == null) return '—';
    return 'USD ' + Math.round(n).toLocaleString('es-AR');
}
function pct(n) {
    if (n == null) return 'N/D';
    return n.toFixed(1) + '%';
}
function m2(n) {
    if (n == null) return '—';
    return parseFloat(n).toLocaleString('es-AR') + ' m²';
}
function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function show(id)  { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id)  { document.getElementById(id)?.classList.add('hidden'); }

function toast(msg, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${type}`;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => el.classList.add('hidden'), 3500);
}

// ===== Toggle parámetros avanzados =====
document.getElementById('toggleParams').addEventListener('click', () => {
    const panel = document.getElementById('paramsPanel');
    const icon  = document.querySelector('.toggle-icon');
    const open  = !panel.classList.contains('hidden');
    panel.classList.toggle('hidden', open);
    icon.textContent = open ? '▸' : '▾';
});

// ===== Tabs de escenarios =====
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        ['efectivo','mixta','canje'].forEach(t => {
            document.getElementById(`tab-${t}`).classList.toggle('hidden', t !== tab);
        });
    });
});

// ===== Validación =====
function validateForm(data) {
    const checks = [
        [!data.lot_address,          'Ingresá la dirección del lote.'],
        [!(data.lot_m2 > 0),         'Los m² del lote deben ser mayores a 0.'],
        [!(data.buildable_m2 > 0),   'Los m² construibles deben ser mayores a 0.'],
        [!(data.land_price >= 0),    'El precio del terreno debe ser un número positivo.'],
        [!(data.sale_price_m2 > 0),  'El precio de venta por m² debe ser mayor a 0.'],
        [!(data.construction_cost_m2 > 0), 'El costo de construcción debe ser mayor a 0.'],
        [data.buildable_m2 > data.lot_m2 * 10,
            'Los m² construibles parecen excesivos respecto al lote.'],
        [data.sales_start_month >= data.project_months,
            'El mes de inicio de ventas debe ser menor a la duración total.'],
        [data.construction_months > data.project_months,
            'Los meses de construcción no pueden superar la duración total.'],
    ];
    for (const [cond, msg] of checks) {
        if (cond) return msg;
    }
    return null;
}

// ===== Renderizar resultados =====
const LABELS = {
    ingresos_brutos:         'Ingresos brutos',
    m2_vendibles:            'M² vendibles',
    m2_canjeados:            'M² canjeados',
    costo_terreno_efectivo:  'Terreno (efectivo)',
    costo_terreno_canje:     'Terreno (canje valor)',
    costo_notarial:          'Gastos notariales',
    costo_construccion:      'Construcción',
    costo_honorarios:        'Honorarios profesionales',
    costo_operativo:         'Gastos operativos',
    costo_comercializacion:  'Comercialización',
    costo_impuestos:         'Impuestos',
    total_costos:            'Total costos',
    utilidad:                'Utilidad neta',
};

const VIABILIDAD_CONFIG = {
    verde:    { label: 'Viable',           bg: '#dcfce7', color: '#15803d', border: '#86efac' },
    amarillo: { label: 'Riesgo moderado',  bg: '#fef9c3', color: '#a16207', border: '#fde047' },
    rojo:     { label: 'No viable',        bg: '#fee2e2', color: '#b91c1c', border: '#fca5a5' },
};

function renderScenario(key, scenario) {
    const v  = VIABILIDAD_CONFIG[scenario.viabilidad] ?? VIABILIDAD_CONFIG.rojo;
    const vc = scenario.viabilidad;

    // Banner de viabilidad
    const banner = document.getElementById(`banner-${key}`);
    banner.style.cssText = `background:${v.bg};color:${v.color};border-color:${v.border}`;
    banner.className = 'viability-banner';
    banner.innerHTML = `<strong>${v.label}</strong>
        <span class="viab-desc">VAN ${usd(scenario.van)} · TIR ${pct(scenario.tir)} · Margen ${pct(scenario.margen)}</span>`;

    // KPIs
    const kpiColor = vc === 'verde' ? 'var(--color-success)'
                   : vc === 'amarillo' ? 'var(--color-warning)' : 'var(--color-danger)';

    document.getElementById(`van-${key}`).textContent      = usd(scenario.van);
    document.getElementById(`tir-${key}`).textContent      = pct(scenario.tir);
    document.getElementById(`margen-${key}`).textContent   = pct(scenario.margen);
    document.getElementById(`utilidad-${key}`).textContent = usd(scenario.utilidad);

    ['van','tir','margen','utilidad'].forEach(k => {
        document.getElementById(`${k}-${key}`).style.color = kpiColor;
    });

    // Desglose
    const isM2Field = (k) => ['m2_vendibles','m2_canjeados'].includes(k);
    const rows = Object.entries(LABELS).map(([k, label]) => {
        const val = scenario[k];
        const formatted = isM2Field(k) ? m2(val) : usd(val);
        const isCost  = k.startsWith('costo_') || k === 'total_costos';
        const isTotal = k === 'total_costos' || k === 'utilidad' || k === 'ingresos_brutos';
        return `<tr class="${isTotal ? 'row-total' : ''}">
            <td>${escHtml(label)}</td>
            <td class="${isCost ? 'cell-cost' : k === 'utilidad' ? (val >= 0 ? 'cell-pos' : 'cell-neg') : ''}">${formatted}</td>
        </tr>`;
    }).join('');

    document.getElementById(`breakdown-${key}`).innerHTML = `
        <details class="breakdown-details">
            <summary>Ver desglose completo</summary>
            <table class="breakdown-table">
                <thead><tr><th>Concepto</th><th>Monto</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </details>`;
}

// ===== Recolectar datos del formulario =====
function getFormData() {
    const fd = new FormData(document.getElementById('formCalc'));
    return {
        lot_address:               fd.get('lot_address')?.trim() ?? '',
        lot_m2:                    parseFloat(fd.get('lot_m2'))   || 0,
        buildable_m2:              parseFloat(fd.get('buildable_m2')) || 0,
        land_price:                parseFloat(fd.get('land_price')) || 0,
        sale_price_m2:             parseFloat(fd.get('sale_price_m2')) || 0,
        construction_cost_m2:      parseFloat(fd.get('construction_cost_m2')) || 0,
        project_type:              fd.get('project_type'),
        fee_percent:               parseFloat(fd.get('fee_percent'))               || 8,
        operating_percent:         parseFloat(fd.get('operating_percent'))         || 3,
        commercialization_percent: parseFloat(fd.get('commercialization_percent')) || 3,
        notarial_percent:          parseFloat(fd.get('notarial_percent'))           || 2,
        tax_percent:               parseFloat(fd.get('tax_percent'))               || 1.5,
        discount_rate:             parseFloat(fd.get('discount_rate'))             || 15,
        construction_months:       parseInt(fd.get('construction_months'))         || 18,
        sales_start_month:         parseInt(fd.get('sales_start_month'))           || 12,
        project_months:            parseInt(fd.get('project_months'))              || 24,
    };
}

// ===== Submit: calcular =====
document.getElementById('formCalc').addEventListener('submit', async e => {
    e.preventDefault();
    const data   = getFormData();
    const errMsg = validateForm(data);
    if (errMsg) { toast(errMsg, 'error'); return; }

    const btn = document.getElementById('btnCalc');
    btn.disabled    = true;
    btn.textContent = 'Calculando…';

    try {
        const res = await api.projects.calculate({ ...data, save: false });
        lastResult = { input: data, result: res };

        // Mostrar resultados
        document.getElementById('resultsTitle').textContent =
            `Resultados — ${escHtml(data.lot_address)}`;
        show('results');

        ['efectivo','mixta','canje'].forEach(k => renderScenario(k, res.scenarios[k]));

        // Activar tab efectivo por defecto
        document.querySelector('[data-tab="efectivo"]').click();

        show('btnSave');
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Calcular viabilidad';
    }
});

// ===== Guardar proyecto =====
document.getElementById('btnSave').addEventListener('click', async () => {
    if (!lastResult) return;
    const btn = document.getElementById('btnSave');
    btn.disabled    = true;
    btn.textContent = 'Guardando…';

    try {
        const res = await api.projects.calculate({ ...lastResult.input, save: true });
        toast('Proyecto guardado correctamente.');
        hide('btnSave');
        if (res.project_id) {
            document.getElementById('resultsTitle').textContent +=
                ` (ID #${res.project_id})`;
        }
    } catch (err) {
        toast(err.message, 'error');
        btn.disabled    = false;
        btn.textContent = 'Guardar proyecto';
    }
});
