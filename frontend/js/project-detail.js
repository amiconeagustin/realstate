// ===== Utilidades (compartidas con projects.js) =====
function usd(n) {
    if (n == null) return '—';
    return 'USD ' + Math.round(n).toLocaleString('es-AR');
}
function pct(n) {
    if (n == null) return 'N/D';
    return parseFloat(n).toFixed(1) + '%';
}
function m2fmt(n) {
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

// ===== ID del proyecto desde la URL =====
const params   = new URLSearchParams(window.location.search);
const projectId = parseInt(params.get('id') ?? '0', 10);

if (!projectId) {
    window.location.href = 'index.html';
}

// ===== Cargar proyecto =====
async function loadProject() {
    try {
        const p = await api.projects.get(projectId);
        fillForm(p);
        fillSavedMetrics(p);
        hide('loadingState');
        show('pageContent');
    } catch (err) {
        document.getElementById('loadingState').innerHTML =
            `<p style="color:var(--color-danger)">${escHtml(err.message)}</p>
             <a href="index.html" class="btn btn-secondary" style="margin-top:1rem">← Volver</a>`;
    }
}

// ===== Rellenar formulario con datos del proyecto =====
function fillForm(p) {
    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el && val != null) el.value = val;
    };

    document.getElementById('projectTitle').textContent    = p.name;
    document.getElementById('projectLocation').textContent = p.location;
    document.getElementById('projectStatus').value         = p.status ?? 'borrador';

    set('lot_address',         p.location);
    set('lot_m2',              p.total_area_m2);
    set('buildable_m2',        p.buildable_area_m2);
    set('project_type',        p.type);
    set('land_price',          p.land_cost);
    set('sale_price_m2',       p.sale_price_m2);
    set('construction_cost_m2',p.construction_cost_m2);

    // Parámetros avanzados
    const cp = p.calc_params ?? {};
    set('fee_percent',               cp.fee_percent               ?? 8);
    set('operating_percent',         cp.operating_percent         ?? 3);
    set('commercialization_percent', cp.commercialization_percent ?? 3);
    set('notarial_percent',          cp.notarial_percent           ?? 2);
    set('tax_percent',               cp.tax_percent               ?? 1.5);
    set('discount_rate',             cp.discount_rate             ?? 15);
    set('construction_months',       cp.construction_months       ?? 18);
    set('sales_start_month',         cp.sales_start_month         ?? 12);
    set('project_months',            cp.project_months            ?? 24);
}

// ===== Rellenar métricas guardadas =====
function fillSavedMetrics(p) {
    const sign = (n) => parseFloat(n) >= 0 ? 'cell-pos' : 'cell-neg';

    const setKpi = (id, val, colorClass) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val;
        if (colorClass) el.className = `kpi-value ${colorClass}`;
    };

    setKpi('sm-investment', usd(p.total_investment));
    setKpi('sm-revenue',    usd(p.total_revenue));
    setKpi('sm-profit',     usd(p.gross_profit),    sign(p.gross_profit));
    setKpi('sm-roi',        pct(p.roi_percent),      sign(p.roi_percent));
    setKpi('sm-breakeven',  usd(p.break_even_price_m2));
    setKpi('sm-date',       p.calculated_at ? p.calculated_at.slice(0, 10) : '—');
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

// ===== Leer formulario =====
function getFormData() {
    const fd = new FormData(document.getElementById('formDetail'));
    return {
        lot_address:               fd.get('lot_address')?.trim() ?? '',
        lot_m2:                    parseFloat(fd.get('lot_m2'))              || 0,
        buildable_m2:              parseFloat(fd.get('buildable_m2'))        || 0,
        land_price:                parseFloat(fd.get('land_price'))          || 0,
        sale_price_m2:             parseFloat(fd.get('sale_price_m2'))       || 0,
        construction_cost_m2:      parseFloat(fd.get('construction_cost_m2')) || 0,
        project_type:              fd.get('project_type'),
        fee_percent:               parseFloat(fd.get('fee_percent'))               || 8,
        operating_percent:         parseFloat(fd.get('operating_percent'))         || 3,
        commercialization_percent: parseFloat(fd.get('commercialization_percent')) || 3,
        notarial_percent:          parseFloat(fd.get('notarial_percent'))           || 2,
        tax_percent:               parseFloat(fd.get('tax_percent'))               || 1.5,
        discount_rate:             parseFloat(fd.get('discount_rate'))             || 15,
        construction_months:       parseInt(fd.get('construction_months'))          || 18,
        sales_start_month:         parseInt(fd.get('sales_start_month'))            || 12,
        project_months:            parseInt(fd.get('project_months'))               || 24,
    };
}

function validateFormData(d) {
    if (!d.lot_address)          return 'Ingresá la dirección del lote.';
    if (!(d.lot_m2 > 0))         return 'Los m² del lote deben ser mayores a 0.';
    if (!(d.buildable_m2 > 0))   return 'Los m² construibles deben ser mayores a 0.';
    if (!(d.land_price >= 0))    return 'El precio del terreno debe ser un número positivo.';
    if (!(d.sale_price_m2 > 0))  return 'El precio de venta por m² debe ser mayor a 0.';
    if (!(d.construction_cost_m2 > 0)) return 'El costo de construcción debe ser mayor a 0.';
    if (d.sales_start_month >= d.project_months) return 'El mes de inicio de ventas debe ser menor a la duración total.';
    return null;
}

// ===== Render de escenarios (igual a projects.js) =====
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

    const banner = document.getElementById(`banner-${key}`);
    banner.style.cssText = `background:${v.bg};color:${v.color};border-color:${v.border}`;
    banner.className = 'viability-banner';
    banner.innerHTML = `<strong>${v.label}</strong>
        <span class="viab-desc">VAN ${usd(scenario.van)} · TIR ${pct(scenario.tir)} · Margen ${pct(scenario.margen)}</span>`;

    const kpiColor = vc === 'verde' ? 'var(--color-success)'
                   : vc === 'amarillo' ? 'var(--color-warning)' : 'var(--color-danger)';

    document.getElementById(`van-${key}`).textContent      = usd(scenario.van);
    document.getElementById(`tir-${key}`).textContent      = pct(scenario.tir);
    document.getElementById(`margen-${key}`).textContent   = pct(scenario.margen);
    document.getElementById(`utilidad-${key}`).textContent = usd(scenario.utilidad);
    ['van','tir','margen','utilidad'].forEach(k => {
        document.getElementById(`${k}-${key}`).style.color = kpiColor;
    });

    const isM2Field = (k) => ['m2_vendibles','m2_canjeados'].includes(k);
    const rows = Object.entries(LABELS).map(([k, label]) => {
        const val       = scenario[k];
        const formatted = isM2Field(k) ? m2fmt(val) : usd(val);
        const isCost    = k.startsWith('costo_') || k === 'total_costos';
        const isTotal   = k === 'total_costos' || k === 'utilidad' || k === 'ingresos_brutos';
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

// ===== Botón Recalcular =====
document.getElementById('btnRecalc').addEventListener('click', async () => {
    const d    = getFormData();
    const err  = validateFormData(d);
    if (err) { toast(err, 'error'); return; }

    const btn = document.getElementById('btnRecalc');
    btn.disabled    = true;
    btn.textContent = 'Calculando…';

    try {
        const res = await api.projects.calculate({ ...d, save: false });
        ['efectivo','mixta','canje'].forEach(k => renderScenario(k, res.scenarios[k]));
        document.querySelector('[data-tab="efectivo"]').click();
        show('viabilityPanel');
        document.getElementById('viabilityPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Recalcular viabilidad';
    }
});

// ===== Formulario: Guardar cambios =====
document.getElementById('formDetail').addEventListener('submit', async e => {
    e.preventDefault();
    const d   = getFormData();
    const err = validateFormData(d);
    if (err) { toast(err, 'error'); return; }

    const btn = document.getElementById('btnSaveChanges');
    btn.disabled    = true;
    btn.textContent = 'Guardando…';

    try {
        await api.projects.update(projectId, {
            name:                 d.lot_address,
            location:             d.lot_address,
            type:                 d.project_type,
            total_area_m2:        d.lot_m2,
            buildable_area_m2:    d.buildable_m2,
            land_cost:            d.land_price,
            construction_cost_m2: d.construction_cost_m2,
            sale_price_m2:        d.sale_price_m2,
            other_costs:          0,
            status:               document.getElementById('projectStatus').value,
            fee_percent:               d.fee_percent,
            operating_percent:         d.operating_percent,
            commercialization_percent: d.commercialization_percent,
            notarial_percent:          d.notarial_percent,
            tax_percent:               d.tax_percent,
            discount_rate:             d.discount_rate,
            construction_months:       d.construction_months,
            sales_start_month:         d.sales_start_month,
            project_months:            d.project_months,
        });

        document.getElementById('projectTitle').textContent    = d.lot_address;
        document.getElementById('projectLocation').textContent = d.lot_address;
        toast('Cambios guardados correctamente.');

        // Refrescar métricas guardadas
        const updated = await api.projects.get(projectId);
        fillSavedMetrics(updated);
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Guardar cambios';
    }
});

// ===== Iniciar =====
loadProject();
