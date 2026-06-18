/**
 * MediCore HMS – Panel Patient
 * js/panel_patient.js
 */
'use strict';

const SESSION_TIMEOUT = 900;
const SESSION_WARNING = 120;

let sessionSeconds = SESSION_TIMEOUT;
let sessionTimer   = null;

/* ═══════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initSessionTimer();
  setInterval(pingSession, 30000);
  loadCitas();
});

/* ═══════════════════════════════════════════════════════════
   SESSION
══════════════════════════════════════════════════════════════ */
function initSessionTimer() {
  clearInterval(sessionTimer);
  sessionTimer = setInterval(tickSession, 1000);
  ['mousemove','keydown','click','scroll'].forEach(e =>
    document.addEventListener(e, () => {
      sessionSeconds = SESSION_TIMEOUT;
      document.getElementById('sessionWarning').classList.add('d-none');
    }, { passive: true })
  );
}

function tickSession() {
  sessionSeconds--;
  if (sessionSeconds <= 0) {
    clearInterval(sessionTimer);
    window.location.href = 'Patient_Login.html?timeout=1';
    return;
  }
  if (sessionSeconds <= SESSION_WARNING) {
    const warn = document.getElementById('sessionWarning');
    warn.classList.remove('d-none');
    const m = Math.floor(sessionSeconds / 60);
    const s = String(sessionSeconds % 60).padStart(2,'0');
    document.getElementById('sessionCountdown').textContent = `${m}:${s}`;
  }
}

async function pingSession() {
  try {
    const r = await fetch('php/api/session_ping.php');
    const d = await r.json();
    if (!d.activa) window.location.href = 'Patient_Login.html?timeout=1';
    else sessionSeconds = d.segundos_restantes;
  } catch(e) { /* silent */ }
}

/* ═══════════════════════════════════════════════════════════
   LOAD DATA
══════════════════════════════════════════════════════════════ */
async function loadCitas() {
  try {
    const r = await fetch('php/api/patient_citas.php');

    // Solo redirigir al login si la sesión no existe (401)
    if (r.status === 401) {
      window.location.href = 'Patient_Login.html';
      return;
    }

    const d = await r.json();

    if (d.error) {
      document.getElementById('patientInfoCard').innerHTML =
        `<div class="info-loading text-danger"><i class="fas fa-exclamation-circle me-2"></i>${d.error}</div>`;
      return;
    }

    renderPatientInfo(d.paciente);
    renderProximas(d.proximas || []);
    renderHistorial(d.historial || []);
  } catch(e) {
    document.getElementById('patientInfoCard').innerHTML =
      '<div class="info-loading text-danger"><i class="fas fa-exclamation-circle me-2"></i>Error al cargar datos. Recarga la página.</div>';
  }
}

/* ═══════════════════════════════════════════════════════════
   RENDER PATIENT INFO
══════════════════════════════════════════════════════════════ */
function renderPatientInfo(p) {
  if (!p) return;

  const nombre = `${p.nombre} ${p.apellido_paterno} ${p.apellido_materno||''}`.trim();
  document.getElementById('patientFullName').textContent = nombre;
  document.title = `MediCore – ${nombre}`;

  const edad = calcEdad(p.fecha_nacimiento);

  document.getElementById('patientInfoCard').innerHTML = `
    <div class="info-header">
      <div class="info-avatar"><i class="fas fa-user"></i></div>
      <div>
        <div class="info-name">${nombre}</div>
        <div class="info-expediente">Expediente: ${p.numero_expediente || '—'}</div>
      </div>
    </div>
    <div class="info-grid">
      <div class="info-group">
        <span class="info-label">Fecha de nacimiento</span>
        <span class="info-value">${fmtDate(p.fecha_nacimiento)}</span>
      </div>
      <div class="info-group">
        <span class="info-label">Edad</span>
        <span class="info-value">${edad} años</span>
      </div>
      <div class="info-group">
        <span class="info-label">Sexo</span>
        <span class="info-value">${p.sexo || '—'}</span>
      </div>
      <div class="info-group">
        <span class="info-label">Grupo sanguíneo</span>
        <span class="info-value">${p.grupo_sanguineo || '—'}</span>
      </div>
      <div class="info-group">
        <span class="info-label">Alergias</span>
        <span class="info-value">${p.alergias || 'Ninguna registrada'}</span>
      </div>
    </div>
  `;
}

/* ═══════════════════════════════════════════════════════════
   RENDER PRÓXIMAS CITAS
══════════════════════════════════════════════════════════════ */
function renderProximas(citas) {
  document.getElementById('badgeProximas').textContent = citas.length;
  const cont = document.getElementById('proximasContainer');

  if (citas.length === 0) {
    cont.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-calendar-times text-muted"></i>
        <p>No tienes citas próximas programadas.</p>
      </div>
    `;
    return;
  }

  cont.innerHTML = citas.map(c => {
    const dt   = new Date(c.fecha_hora);
    const day  = dt.getDate();
    const mon  = dt.toLocaleDateString('es-MX', { month:'short' });
    const time = dt.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' });

    return `
      <div class="cita-card proxima">
        <div class="cita-fecha-box">
          <div class="cita-fecha-day">${day}</div>
          <div class="cita-fecha-month">${mon}</div>
          <div class="cita-fecha-time">${time}</div>
        </div>
        <div class="cita-body">
          <div class="cita-medico"><i class="fas fa-user-md me-1 text-muted"></i>${c.medico || '—'}</div>
          <div class="cita-tipo">${c.tipo_medico || ''} · ${c.tipo_cita || ''}</div>
          ${c.motivo_consulta ? `<div class="cita-motivo"><strong>Motivo:</strong> ${c.motivo_consulta}</div>` : ''}
          ${c.consultorio_area ? `<div class="cita-consultorio"><i class="fas fa-door-open me-1"></i>${c.consultorio_area} – Consultorio ${c.numero_consultorio}</div>` : ''}
        </div>
        <div class="cita-badge">${estatusBadge(c.estatus)}</div>
      </div>
    `;
  }).join('');
}

/* ═══════════════════════════════════════════════════════════
   RENDER HISTORIAL
══════════════════════════════════════════════════════════════ */
function renderHistorial(citas) {
  const cont = document.getElementById('historialContainer');

  if (citas.length === 0) {
    cont.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-history text-muted"></i>
        <p>Sin historial de citas anteriores.</p>
      </div>
    `;
    return;
  }

  cont.innerHTML = citas.map(c => {
    const dt   = new Date(c.fecha_hora);
    const day  = dt.getDate();
    const mon  = dt.toLocaleDateString('es-MX', { month:'short' });
    const time = dt.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' });

    return `
      <div class="cita-card historial">
        <div class="cita-fecha-box" style="background:#f3f4f6;color:#6b7280;">
          <div class="cita-fecha-day">${day}</div>
          <div class="cita-fecha-month">${mon}</div>
          <div class="cita-fecha-time">${time}</div>
        </div>
        <div class="cita-body">
          <div class="cita-medico"><i class="fas fa-user-md me-1 text-muted"></i>${c.medico || '—'}</div>
          <div class="cita-tipo">${c.tipo_medico || ''} · ${c.tipo_cita || ''}</div>
          ${c.motivo_consulta ? `<div class="cita-motivo"><strong>Motivo:</strong> ${c.motivo_consulta}</div>` : ''}
          ${c.diagnostico ? `<div class="cita-diagnostico"><i class="fas fa-notes-medical me-1"></i><strong>Diagnóstico:</strong> ${c.diagnostico}</div>` : ''}
          ${c.tratamiento ? `<div class="cita-diagnostico"><i class="fas fa-pills me-1"></i><strong>Tratamiento:</strong> ${c.tratamiento}</div>` : ''}
        </div>
        <div class="cita-badge">${estatusBadge(c.estatus)}</div>
      </div>
    `;
  }).join('');
}

/* ═══════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════════ */
function fmtDate(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('es-MX', { year:'numeric', month:'long', day:'numeric' });
}

function calcEdad(fechaNac) {
  if (!fechaNac) return '—';
  const hoy  = new Date();
  const nac  = new Date(fechaNac);
  let edad   = hoy.getFullYear() - nac.getFullYear();
  const m    = hoy.getMonth() - nac.getMonth();
  if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
  return edad;
}

function estatusBadge(est) {
  const map = {
    'Programada':  'badge-programada',
    'Confirmada':  'badge-programada',
    'Completada':  'badge-completada',
    'Cancelada':   'badge-cancelada',
    'En consulta': 'badge-en-proceso',
    'Reagendada':  'badge-en-proceso',
  };
  return `<span class="badge-status ${map[est]||'badge-default'}">${est||'—'}</span>`;
}
