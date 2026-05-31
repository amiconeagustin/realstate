// Estado mínimo de la SPA
const state = { user: null };

// ===== Persistencia de sesión en localStorage =====

function authSave(user)  { localStorage.setItem('rs_user', JSON.stringify(user)); }
function authClear()     { localStorage.removeItem('rs_user'); }
function authLoad()      {
    try { return JSON.parse(localStorage.getItem('rs_user')); }
    catch { return null; }
}

// ===== Utilidades UI =====

function show(id)  { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id)  { document.getElementById(id)?.classList.add('hidden'); }

function toast(msg, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${type}`;
    setTimeout(() => { el.classList.add('hidden'); }, 3500);
}

function showView(viewId) {
    ['viewAuth', 'viewProjects', 'viewNewProject'].forEach(hide);
    show(viewId);
}

// ===== Navegación post-login =====

function onLogin(user) {
    state.user = user;
    authSave(user);
    show('navProjects');
    show('navLogout');
    loadProjects();
}

function onLogout() {
    state.user = null;
    authClear();
    hide('navProjects');
    hide('navLogout');
    showView('viewAuth');
}

// ===== Proyectos =====

async function loadProjects() {
    showView('viewProjects');
    try {
        const projects = await api.projects.list();
        renderProjects(projects);
    } catch (e) {
        // Sesión expirada en el servidor → limpiar y volver al login
        if (e.message === 'No autenticado' || e.message.startsWith('Error 401')) {
            authClear();
            onLogout();
            return;
        }
        toast(e.message, 'error');
    }
}

function renderProjects(projects) {
    const container = document.getElementById('projectList');
    if (!projects.length) {
        container.innerHTML = '<p class="empty-state">No tenés proyectos todavía. ¡Creá el primero!</p>';
        return;
    }

    container.innerHTML = projects.map(p => {
        const roi      = p.roi_percent != null ? parseFloat(p.roi_percent).toFixed(1) + '%' : '—';
        const profit   = p.gross_profit != null
            ? 'USD ' + parseInt(p.gross_profit).toLocaleString('es-AR')
            : '—';
        const roiClass = p.roi_percent >= 0 ? 'positive' : 'negative';

        return `
        <div class="project-card" data-id="${p.id}" role="button" tabindex="0"
             onclick="window.location.href='project-detail.html?id=${p.id}'"
             onkeydown="if(event.key==='Enter')window.location.href='project-detail.html?id=${p.id}'">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <h3>${escHtml(p.name)}</h3>
                <span class="badge badge-${p.status}">${escHtml(p.status)}</span>
            </div>
            <p class="location">${escHtml(p.location)}</p>
            <div class="metrics">
                <div class="metric ${roiClass}">
                    <div class="label">ROI</div>
                    <div class="value">${roi}</div>
                </div>
                <div class="metric ${roiClass}">
                    <div class="label">Ganancia bruta</div>
                    <div class="value">${profit}</div>
                </div>
            </div>
            <p class="card-hint">Clic para ver detalles →</p>
        </div>`;
    }).join('');
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ===== Event listeners =====

document.getElementById('goRegister').addEventListener('click', e => {
    e.preventDefault();
    hide('formLogin');
    show('formRegister');
});

document.getElementById('goLogin').addEventListener('click', e => {
    e.preventDefault();
    hide('formRegister');
    show('formLogin');
});

document.getElementById('formLogin').addEventListener('submit', async e => {
    e.preventDefault();
    try {
        const { user } = await api.auth.login(
            document.getElementById('loginEmail').value,
            document.getElementById('loginPassword').value
        );
        onLogin(user);
    } catch (err) {
        toast(err.message, 'error');
    }
});

document.getElementById('formRegister').addEventListener('submit', async e => {
    e.preventDefault();
    try {
        const { user } = await api.auth.register(
            document.getElementById('regName').value,
            document.getElementById('regEmail').value,
            document.getElementById('regPassword').value
        );
        onLogin(user); // sesión creada en el servidor → ir directo a proyectos
    } catch (err) {
        toast(err.message, 'error');
    }
});

document.getElementById('navLogout').addEventListener('click', e => {
    e.preventDefault();
    onLogout();
});

document.getElementById('navProjects').addEventListener('click', e => {
    e.preventDefault();
    loadProjects();
});

// ===== Verificación al arrancar la página =====
(async function init() {
    const saved = authLoad();
    if (!saved) return; // no hay sesión guardada → mostrar login (comportamiento por defecto)
    state.user = saved;
    show('navProjects');
    show('navLogout');
    try {
        await loadProjects(); // verifica que la sesión PHP siga viva
    } catch {
        authClear();
        onLogout();
    }
})();

document.getElementById('btnCancelProject')?.addEventListener('click', () => {
    showView('viewProjects');
});

document.getElementById('formProject')?.addEventListener('submit', async e => {
    e.preventDefault();
    const formData = Object.fromEntries(new FormData(e.target));
    try {
        await api.projects.create(formData);
        toast('Proyecto creado correctamente.');
        e.target.reset();
        loadProjects();
    } catch (err) {
        toast(err.message, 'error');
    }
});
