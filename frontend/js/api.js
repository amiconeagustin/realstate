// Capa de comunicación con el backend
const API_BASE = '/realstate/api';

async function request(path, options = {}) {
    const res = await fetch(`${API_BASE}${path}`, {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        ...options,
    });

    const data = await res.json().catch(() => null);

    if (!res.ok) {
        throw new Error(data?.error ?? `Error ${res.status}`);
    }
    return data;
}

const api = {
    auth: {
        login:    (email, password) => request('/auth/login.php',    { method: 'POST', body: JSON.stringify({ email, password }) }),
        register: (name, email, password) => request('/auth/register.php', { method: 'POST', body: JSON.stringify({ name, email, password }) }),
    },
    projects: {
        list:      ()          => request('/projects/index.php'),
        create:    (data)      => request('/projects/index.php',        { method: 'POST', body: JSON.stringify(data) }),
        calculate: (data)      => request('/projects/calculate.php',    { method: 'POST', body: JSON.stringify(data) }),
        get:       (id)        => request(`/projects/get.php?id=${id}`),
        update:    (id, data)  => request(`/projects/get.php?id=${id}`, { method: 'PUT',  body: JSON.stringify(data) }),
    },
    comparables: {
        list:   (projectId)        => request(`/comparables/index.php?project_id=${projectId}`),
        create: (projectId, data)  => request(`/comparables/index.php?project_id=${projectId}`, { method: 'POST', body: JSON.stringify(data) }),
    },
};
