// Central API client. Change API_BASE to match your Laravel server.
const API_BASE = 'http://localhost:8000/api';

function getToken() {
  return localStorage.getItem('token');
}

function setAuth(user, token) {
  localStorage.setItem('token', token);
  localStorage.setItem('user', JSON.stringify(user));
}

function clearAuth() {
  localStorage.removeItem('token');
  localStorage.removeItem('user');
}

function currentUser() {
  try { return JSON.parse(localStorage.getItem('user')); }
  catch { return null; }
}

async function apiFetch(path, { method = 'GET', body = null, auth = true } = {}) {
  const headers = { 'Accept': 'application/json' };
  if (body) headers['Content-Type'] = 'application/json';
  if (auth) {
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : null,
  });

  const text = await res.text();
  const data = text ? JSON.parse(text) : null;

  if (!res.ok) {
    const msg = data?.message || `Request failed (${res.status})`;
    const err = new Error(msg);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

function requireAuth() {
  if (!getToken()) {
    window.location.href = 'login.html';
  }
}

function logout() {
  apiFetch('/logout', { method: 'POST' }).catch(() => {});
  clearAuth();
  window.location.href = 'login.html';
}

function showError(el, msg) {
  el.innerHTML = `<div class="error">${msg}</div>`;
}
function showSuccess(el, msg) {
  el.innerHTML = `<div class="success">${msg}</div>`;
}
