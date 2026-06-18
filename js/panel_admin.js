'use strict';
const SESSION_TIMEOUT   = 900;   // 15 min in seconds
const SESSION_WARNING   = 120;   // show warning at 2 min remaining
const PING_INTERVAL     = 30000; // ping every 30 s
let currentSection  = 'dashboard';
let sessionTimer    = null;
let sessionSeconds  = SESSION_TIMEOUT;
let chartsCreated   = {};
let auditPage       = 1;
let currentReporte  = 'financiero';
document.addEventListener('DOMContentLoaded', () => {
  initTopbarDate();
  initNavigation();
  initSessionTimer();
  initSidebarToggle();
  const now = new Date();
  const mm  = String(now.getMonth() + 1).padStart(2,'0');
  document.getElementById('reporteMes').value = `${now.getFullYear()}-${mm}`;
  const today = now.toISOString().split('T')[0];
  const d30   = new Date(now - 30*24*3600*1000).toISOString().split('T')[0];
  document.getElementById('auditDesde').value = d30;
  document.getElementById('auditHasta').value = today;
  loadDashboard();
  loadNotificaciones();
  loadRolesSelect();
  document.getElementById('btnNuevoEmpleado').addEventListener('click', openNuevoEmpleado);
  document.getElementById('btnFilterEmp').addEventListener('click', () => loadEmpleados(false));
  document.getElementById('btnGuardarEmp').addEventListener('click', guardarEmpleado);
  document.getElementById('btnConfirmarBaja').addEventListener('click', confirmarBaja);
  document.getElementById('btnConfirmarReasig').addEventListener('click', confirmarReasignar);
  document.getElementById('btnConfirmarPrecio').addEventListener('click', confirmarPrecio);
  document.getElementById('btnCrearMant').addEventListener('click', crearMantenimiento);
  document.getElementById('btnCancelEmergencia').addEventListener('click', () => openModal('modalCancelacion'));
  document.getElementById('btnConfirmarCancelacion').addEventListener('click', confirmarCancelacion);
  document.getElementById('btnBuscarAudit').addEventListener('click', () => { auditPage=1; loadAuditoria(); });
  document.getElementById('btnCargarReporte').addEventListener('click', loadReporte);
  document.getElementById('btnNotifTop').addEventListener('click', () => navigateTo('notificaciones'));
  document.querySelectorAll('.report-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.report-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentReporte = btn.dataset.reporte;
      loadReporte();
    });
  });
  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.close));
  });
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal(overlay.id);
    });
  });
  setInterval(pingSession, PING_INTERVAL);
});
function initNavigation() {
  document.querySelectorAll('.nav-link[data-section]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      navigateTo(link.dataset.section);
    });
  });
}
function navigateTo(section) {
  currentSection = section;
  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
  const activeLink = document.querySelector(`.nav-link[data-section="${section}"]`);
  if (activeLink) activeLink.classList.add('active');
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  const target = document.getElementById(`section-${section}`);
  if (target) target.classList.add('active');
  const titles = {
    dashboard: 'Dashboard', notificaciones: 'Notificaciones', empleados: 'GestiÃ³n de Empleados',
    precios: 'ValidaciÃ³n de Precios', pagos: 'GestiÃ³n de Pagos', mantenimiento: 'Mantenimiento',
    reportes: 'Reportes', auditoria: 'BitÃ¡cora de AuditorÃ­a'
  };
  document.getElementById('topbarTitle').textContent = titles[section] || section;
  const loaders = {
    notificaciones: loadNotificaciones,
    empleados: () => loadEmpleados(true),
    precios: loadPrecios,
    pagos: loadPagos,
    mantenimiento: loadMantenimiento,
    reportes: loadReporte,
    auditoria: loadAuditoria,
  };
  if (loaders[section]) loaders[section]();
}
function initSidebarToggle() {
  document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('expanded');
  });
}
function initSessionTimer() {
  sessionSeconds = SESSION_TIMEOUT;
  clearInterval(sessionTimer);
  sessionTimer = setInterval(tickSession, 1000);
  ['mousemove','keydown','click','scroll'].forEach(evt => {
    document.addEventListener(evt, resetSessionTimer, { passive: true });
  });
}
function resetSessionTimer() {
  sessionSeconds = SESSION_TIMEOUT;
  document.getElementById('sessionWarning').classList.add('d-none');
}
function tickSession() {
  sessionSeconds--;
  if (sessionSeconds <= 0) {
    clearInterval(sessionTimer);
    window.location.href = 'php/logout.php?timeout=1';
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
    if (!d.activa) {
      window.location.href = 'php/logout.php?timeout=1';
    } else {
      sessionSeconds = d.segundos_restantes;
    }
  } catch(e) { }
}
function initTopbarDate() {
  const d = new Date();
  document.getElementById('topbarDate').textContent = d.toLocaleDateString('es-MX', {
    weekday:'long', year:'numeric', month:'long', day:'numeric'
  });
}
function fmt(n) {
  return Number(n || 0).toLocaleString('es-MX', { style:'currency', currency:'MXN' });
}
function fmtDate(str) {
  if (!str) return 'â€”';
  return new Date(str).toLocaleString('es-MX', { dateStyle:'short', timeStyle:'short' });
}
function showToast(msg, type = 'success') {
  const icons = { success:'check-circle', danger:'times-circle', warning:'exclamation-triangle', info:'info-circle' };
  const toast = document.createElement('div');
  toast.className = `toast-msg toast-${type}`;
  toast.style.cssText = 'opacity:1;transform:translateX(0)';
  toast.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'} me-2"></i>${msg}`;
  document.getElementById('toastContainer').appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(30px)';
    toast.style.transition = 'all .3s ease';
    setTimeout(() => toast.remove(), 350);
  }, 3500);
}
function openModal(id) {
  document.getElementById(id).classList.add('active');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
}
async function apiPost(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return r.json();
}
function estatusBadge(est) {
  const map = {
    'Activo':         'badge-activo',
    'Por dar de baja':'badge-baja-proc',
    'Baja':           'badge-baja',
    'Pendiente':      'badge-pendiente',
    'Vigente':        'badge-activo',
    'Rechazado':      'badge-baja',
    'Programado':     'badge-info',
    'En proceso':     'badge-warning',
    'Completado':     'badge-activo',
    'Cancelado':      'badge-baja',
  };
  return `<span class="badge-status ${map[est]||'badge-info'}">${est}</span>`;
}
function destroyChart(id) {
  if (chartsCreated[id]) { chartsCreated[id].destroy(); delete chartsCreated[id]; }
}
async function loadDashboard() {
  try {
    const d = await (await fetch('php/api/dashboard.php')).json();
    if (d.error) return;
    document.getElementById('kpiEmpleados').textContent  = d.kpis.total_empleados   ?? 'â€“';
    document.getElementById('kpiPacientes').textContent  = d.kpis.total_pacientes   ?? 'â€“';
    document.getElementById('kpiCitasHoy').textContent   = d.kpis.citas_hoy         ?? 'â€“';
    document.getElementById('kpiPagosPend').textContent  = d.kpis.pagos_pendientes  ?? 'â€“';
    document.getElementById('kpiPreciosPend').textContent= d.kpis.precios_pendientes?? 'â€“';
    document.getElementById('kpiStock').textContent      = d.kpis.alertas_stock     ?? 'â€“';
    const np = (d.kpis.precios_pendientes||0) + (d.kpis.modificaciones_pendientes||0);
    document.getElementById('badgeNotif').textContent  = np;
    document.getElementById('badgePrecios').textContent = d.kpis.precios_pendientes||0;
    document.getElementById('badgePagos').textContent  = d.kpis.modificaciones_pendientes||0;
    if (np > 0) document.getElementById('notifDot').classList.remove('d-none');
    if (d.admin_nombre) document.getElementById('adminNombre').textContent = d.admin_nombre;
    buildChartIngresos(d.ingresos || []);
    buildChartCitas(d.citas_estado || []);
    buildChartMeds(d.top_meds || []);
    buildChartConsultas(d.consultas_tipo || [], d.consultas_resumen || {});
  } catch(e) {
    showToast('Error al cargar el dashboard', 'danger');
  }
}
function buildChartIngresos(data) {
  destroyChart('chartIngresos');
  const ctx = document.getElementById('chartIngresos').getContext('2d');
  chartsCreated.chartIngresos = new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.map(r => r.mes),
      datasets: [{
        label: 'Ingresos (MXN)',
        data: data.map(r => parseFloat(r.total||0)),
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,0.1)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#3b82f6',
        pointRadius: 4,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { ticks: { callback: v => '$'+v.toLocaleString('es-MX') } }
      }
    }
  });
}
function buildChartCitas(data) {
  destroyChart('chartCitas');
  const ctx = document.getElementById('chartCitas').getContext('2d');
  const colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];
  chartsCreated.chartCitas = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: data.map(r => r.estatus),
      datasets: [{ data: data.map(r => r.total), backgroundColor: colors }]
    },
    options: { responsive: true, plugins: { legend: { position:'bottom' } } }
  });
}
function buildChartMeds(data) {
  destroyChart('chartMeds');
  const ctx = document.getElementById('chartMeds').getContext('2d');
  chartsCreated.chartMeds = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(r => r.nombre_sustancia),
      datasets: [{
        label: 'Unidades dispensadas',
        data: data.map(r => r.total_dispensado),
        backgroundColor: 'rgba(245,158,11,0.7)',
        borderColor: '#f59e0b',
        borderWidth: 1,
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } }
    }
  });
}
function buildChartConsultas(data, resumen) {
  destroyChart('chartConsultas');
  const ctx = document.getElementById('chartConsultas').getContext('2d');
  const coloresGeneral     = ['#3b82f6','#60a5fa'];
  const coloresEspecialista= ['#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#ec4899'];
  let idxG = 0, idxE = 0;
  const labels = [], valores = [], colores = [], borderColors = [];
  data.forEach(r => {
    labels.push(r.tipo);
    valores.push(parseInt(r.total));
    if (r.categoria === 'General') {
      const c = coloresGeneral[idxG++ % coloresGeneral.length];
      colores.push(c + 'CC'); borderColors.push(c);
    } else {
      const c = coloresEspecialista[idxE++ % coloresEspecialista.length];
      colores.push(c + 'CC'); borderColors.push(c);
    }
  });
  const totalG = parseInt(resumen.general || 0);
  const totalE = parseInt(resumen.especialista || 0);
  chartsCreated.chartConsultas = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: valores,
        backgroundColor: colores,
        borderColor: borderColors,
        borderWidth: 2,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      cutout: '60%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { size: 11 },
            padding: 10,
            generateLabels: (chart) => {
              const ds = chart.data.datasets[0];
              return chart.data.labels.map((lbl, i) => {
                const isGen = data[i]?.categoria === 'General';
                return {
                  text: (isGen ? 'â— ' : 'â—† ') + lbl + ' (' + ds.data[i] + ')',
                  fillStyle: ds.backgroundColor[i],
                  strokeStyle: ds.borderColor[i],
                  lineWidth: 2,
                  index: i,
                };
              });
            }
          }
        },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const cat = data[ctx.dataIndex]?.categoria || '';
              const pct = Math.round(ctx.parsed / valores.reduce((a,b)=>a+b,0) * 100);
              return ` ${ctx.label}: ${ctx.parsed} consultas (${pct}%) â€” ${cat}`;
            }
          }
        },
        afterDraw: undefined,
      }
    },
    plugins: [{
      id: 'centerText',
      afterDraw(chart) {
        const { ctx: c, chartArea: { top, bottom, left, right } } = chart;
        const cx = (left + right) / 2, cy = (top + bottom) / 2;
        c.save();
        c.fillStyle = '#3b82f6'; c.font = 'bold 13px Inter,sans-serif';
        c.textAlign = 'center'; c.textBaseline = 'middle';
        c.fillText(`G: ${totalG}`, cx, cy - 10);
        c.fillStyle = '#10b981'; c.font = 'bold 13px Inter,sans-serif';
        c.fillText(`E: ${totalE}`, cx, cy + 10);
        c.restore();
      }
    }]
  });
}
async function loadNotificaciones() {
  try {
    const d = await (await fetch('php/api/notificaciones.php')).json();
    const container = document.getElementById('notifContainer');
    if (!d.notificaciones || d.notificaciones.length === 0) {
      container.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i><p>Sin notificaciones pendientes</p></div>';
      return;
    }
    container.innerHTML = d.notificaciones.map(n => `
      <div class="notif-card notif-${n.color}">
        <div class="notif-icon"><i class="fas ${n.icono}"></i></div>
        <div class="notif-body">
          <div class="notif-title">${n.titulo}</div>
          <div class="notif-msg">${n.mensaje}</div>
        </div>
        <button class="btn-notif-action" onclick="navigateTo('${n.seccion}')">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    `).join('');
  } catch(e) {
    showToast('Error al cargar notificaciones', 'danger');
  }
}
async function loadRolesSelect() {
  try {
    const [rolesResp, consResp] = await Promise.all([
      fetch('php/api/empleados.php?action=roles').then(r => r.json()),
      fetch('php/api/empleados.php?action=consultorios').then(r => r.json()),
    ]);
    const selFilter = document.getElementById('filterEmpRol');
    const selEmp    = document.getElementById('empRol');
    (rolesResp.roles || []).forEach(r => {
      const opt = `<option value="${r.id_rol}">${r.nombre_rol}</option>`;
      selFilter.innerHTML += opt;
      selEmp.innerHTML    += opt;
    });
    const selCon = document.getElementById('empConsultorio');
    (consResp.consultorios || []).forEach(c => {
      selCon.innerHTML += `<option value="${c.id_consultorio}">${c.area} â€“ ${c.numero_consultorio}</option>`;
    });
  } catch(e) {
    console.error('Error cargando roles/consultorios:', e);
  }
}
async function loadEmpleados(resetFilters = false) {
  if (resetFilters) {
    document.getElementById('filterEmpNombre').value  = '';
    document.getElementById('filterEmpRol').value     = '';
    document.getElementById('filterEmpEstatus').value = '';
  }
  const nombre  = document.getElementById('filterEmpNombre').value.trim();
  const rol     = document.getElementById('filterEmpRol').value;
  const estatus = document.getElementById('filterEmpEstatus').value;
  const tbody = document.getElementById('tablaEmpleados');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargandoâ€¦</td></tr>';
  try {
    const params = new URLSearchParams({ action:'list' });
    if (nombre)  params.set('nombre',  nombre);
    if (rol)     params.set('rol',     rol);
    if (estatus) params.set('estatus', estatus);
    const d = await (await fetch(`php/api/empleados.php?${params}`)).json();
    if (!d.empleados || d.empleados.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin empleados que coincidan</td></tr>';
      return;
    }
    tbody.innerHTML = d.empleados.map(e => `
      <tr>
        <td><code>${e.numero_empleado}</code></td>
        <td>${e.nombre} ${e.apellido_paterno} ${e.apellido_materno||''}</td>
        <td>${e.nombre_rol}<br><small class="text-muted">${e.tipo||''}</small></td>
        <td>${e.consultorio_area ? `${e.consultorio_area} â€“ ${e.numero_consultorio}` : 'â€”'}</td>
        <td>${estatusBadge(e.estatus)}</td>
        <td>${e.fecha_baja_programada ? fmtDate(e.fecha_baja_programada) : 'â€”'}</td>
        <td class="actions-cell">
          <button class="btn-action edit" title="Editar" onclick="openEditarEmpleado(${e.id_empleado})">
            <i class="fas fa-edit"></i>
          </button>
          ${e.estatus === 'Activo' ? `
            <button class="btn-action warn" title="Iniciar baja" onclick="openBaja(${e.id_empleado},'${e.nombre} ${e.apellido_paterno}')">
              <i class="fas fa-user-clock"></i>
            </button>
          ` : ''}
          ${e.estatus === 'Por dar de baja' ? `
            <button class="btn-action info" title="Reasignar pacientes" onclick="openReasignar(${e.id_empleado})">
              <i class="fas fa-exchange-alt"></i>
            </button>
            <button class="btn-action danger" title="Baja definitiva" onclick="bajaDefinitiva(${e.id_empleado},'${e.nombre} ${e.apellido_paterno}')">
              <i class="fas fa-user-minus"></i>
            </button>
          ` : ''}
        </td>
      </tr>
    `).join('');
  } catch(e) {
    showToast('Error al cargar empleados', 'danger');
  }
}
function openNuevoEmpleado() {
  document.getElementById('modalEmpTitle').textContent = 'Nuevo Empleado';
  document.getElementById('empId').value = '';
  ['empNumero','empNombre','empApPat','empApMat','empTipo','empArea','empPassword','empPasswordConf'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('empRol').value = '';
  document.getElementById('empConsultorio').value = '';
  document.getElementById('empTurno').value = 'Matutino';
  document.getElementById('empPasswordReq').style.display = '';
  openModal('modalEmpleado');
}
async function openEditarEmpleado(id) {
  try {
    const d = await (await fetch(`php/api/empleados.php?action=list&id=${id}`)).json();
    const e = d.empleados && d.empleados[0];
    if (!e) return showToast('Empleado no encontrado', 'danger');
    document.getElementById('modalEmpTitle').textContent = 'Editar Empleado';
    document.getElementById('empId').value          = e.id_empleado;
    document.getElementById('empNumero').value      = e.numero_empleado;
    document.getElementById('empNombre').value      = e.nombre;
    document.getElementById('empApPat').value       = e.apellido_paterno;
    document.getElementById('empApMat').value       = e.apellido_materno||'';
    document.getElementById('empRol').value         = e.id_rol;
    document.getElementById('empTipo').value        = e.tipo||'';
    document.getElementById('empConsultorio').value = e.id_consultorio||'';
    document.getElementById('empTurno').value = e.turno||'Matutino';
    document.getElementById('empArea').value  = e.area_asignada||'';
    document.getElementById('empPassword').value    = '';
    document.getElementById('empPasswordConf').value= '';
    document.getElementById('empPasswordReq').style.display = 'none';
    openModal('modalEmpleado');
  } catch(e) {
    showToast('Error al cargar datos del empleado', 'danger');
  }
}
async function guardarEmpleado() {
  const id     = document.getElementById('empId').value;
  const pw     = document.getElementById('empPassword').value;
  const pwConf = document.getElementById('empPasswordConf').value;
  if (!id && !pw) return showToast('La contraseÃ±a es requerida para nuevos empleados', 'warning');
  if (pw && pw.length < 8) return showToast('La contraseÃ±a debe tener al menos 8 caracteres', 'warning');
  if (pw && pw !== pwConf) return showToast('Las contraseÃ±as no coinciden', 'warning');
  const payload = {
    action:           id ? 'actualizar' : 'crear',
    id_empleado:      id || undefined,
    numero_empleado:  document.getElementById('empNumero').value,
    nombre:           document.getElementById('empNombre').value,
    apellido_paterno: document.getElementById('empApPat').value,
    apellido_materno: document.getElementById('empApMat').value,
    id_rol:           document.getElementById('empRol').value,
    tipo:             document.getElementById('empTipo').value,
    id_consultorio:   document.getElementById('empConsultorio').value || null,
    turno:            document.getElementById('empTurno').value,
    area_asignada:    document.getElementById('empArea').value,
  };
  if (pw) payload.password = pw;
  try {
    const d = await apiPost('php/api/empleados.php', payload);
    if (d.error) return showToast(d.error, 'danger');
    showToast(id ? 'Empleado actualizado correctamente' : 'Empleado creado correctamente');
    closeModal('modalEmpleado');
    loadEmpleados();
  } catch(e) {
    showToast('Error al guardar empleado', 'danger');
  }
}
function openBaja(id, nombre) {
  document.getElementById('bajaEmpId').value      = id;
  document.getElementById('bajaEmpNombre').textContent = `Empleado: ${nombre}`;
  document.getElementById('bajaMotivo').value     = '';
  openModal('modalBaja');
}
async function confirmarBaja() {
  const id     = document.getElementById('bajaEmpId').value;
  const motivo = document.getElementById('bajaMotivo').value.trim();
  if (!motivo) return showToast('El motivo de baja es requerido', 'warning');
  try {
    const d = await apiPost('php/api/empleados.php', { action:'iniciar_baja', id_empleado: id, motivo });
    if (d.error) return showToast(d.error, 'danger');
    showToast('PerÃ­odo de gracia iniciado. Baja definitiva en 30 dÃ­as.', 'warning');
    closeModal('modalBaja');
    loadEmpleados();
  } catch(e) {
    showToast('Error al iniciar baja', 'danger');
  }
}
async function openReasignar(id) {
  document.getElementById('reasEmpId').value = id;
  document.getElementById('reasPacientesList').innerHTML = '<p class="text-muted">Cargandoâ€¦</p>';
  document.getElementById('reasReceptor').innerHTML = '<option value="">Cargandoâ€¦</option>';
  openModal('modalReasignar');
  try {
    const [pac, med] = await Promise.all([
      (await fetch(`php/api/empleados.php?action=pacientes_activos&id=${id}`)).json(),
      (await fetch(`php/api/empleados.php?action=medicos_activos&id=${id}`)).json(),
    ]);
    const sel = document.getElementById('reasReceptor');
    sel.innerHTML = '<option value="">â€“ Seleccionar receptor â€“</option>';
    (med.medicos || []).forEach(m => {
      sel.innerHTML += `<option value="${m.id_empleado}">${m.nombre} (${m.tipo||m.nombre_rol})</option>`;
    });
    const list = document.getElementById('reasPacientesList');
    const items = [...(pac.citas||[]), ...(pac.hospitalizaciones||[])];
    if (items.length === 0) {
      list.innerHTML = '<p class="text-success"><i class="fas fa-check me-1"></i>Sin pacientes pendientes de reasignaciÃ³n</p>';
    } else {
      list.innerHTML = items.map(p => `
        <div class="paciente-item">
          <i class="fas fa-user me-2 text-muted"></i>
          <span>${p.paciente_nombre || p.nombre_paciente || 'Paciente'}</span>
          <span class="ms-auto text-muted small">${p.tipo || 'Cita'} â€“ ${fmtDate(p.fecha_hora || p.fecha_ingreso)}</span>
        </div>
      `).join('');
    }
  } catch(e) {
    showToast('Error al cargar datos de reasignaciÃ³n', 'danger');
  }
}
async function confirmarReasignar() {
  const empId    = document.getElementById('reasEmpId').value;
  const receptor = document.getElementById('reasReceptor').value;
  if (!receptor) return showToast('Selecciona un empleado receptor', 'warning');
  try {
    const d = await apiPost('php/api/empleados.php', { action:'reasignar', id_empleado: empId, id_receptor: receptor });
    if (d.error) return showToast(d.error, 'danger');
    showToast('Pacientes reasignados correctamente');
    closeModal('modalReasignar');
    loadEmpleados();
  } catch(e) {
    showToast('Error al reasignar pacientes', 'danger');
  }
}
async function bajaDefinitiva(id, nombre) {
  if (!confirm(`Â¿Confirma la BAJA DEFINITIVA de ${nombre}? Esta acciÃ³n no se puede revertir.`)) return;
  try {
    const d = await apiPost('php/api/empleados.php', { action:'baja_definitiva', id_empleado: id });
    if (d.error) return showToast(d.error, 'danger');
    showToast('Baja definitiva procesada correctamente');
    loadEmpleados();
  } catch(e) {
    showToast('Error al procesar baja definitiva', 'danger');
  }
}
async function loadPrecios() {
  try {
    const d = await (await fetch('php/api/precios.php')).json();
    const tbody = document.getElementById('tablaPrecios');
    if (!d.lotes || d.lotes.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin lotes pendientes de validaciÃ³n</td></tr>';
      return;
    }
    tbody.innerHTML = d.lotes.map(l => `
      <tr>
        <td>${l.nombre_sustancia}<br><small class="text-muted">${l.tipo_medicamento}</small></td>
        <td>${l.numero_lote}<br><small class="text-muted">Cad: ${l.fecha_caducidad||'â€”'}</small></td>
        <td>${l.farmaceutico||'â€”'}</td>
        <td>${l.precio_vigente ? fmt(l.precio_vigente) : 'â€”'}</td>
        <td class="fw-semibold">${fmt(l.precio_propuesto)}</td>
        <td>${estatusBadge(l.estatus_precio)}</td>
        <td class="actions-cell">
          ${l.estatus_precio === 'Pendiente' ? `
            <button class="btn-action edit" title="Aprobar" onclick="openValidarPrecio(${l.id_lote},'${l.nombre_sustancia}',${l.precio_propuesto},'aprobar')">
              <i class="fas fa-check"></i>
            </button>
            <button class="btn-action danger" title="Rechazar" onclick="openValidarPrecio(${l.id_lote},'${l.nombre_sustancia}',${l.precio_propuesto},'rechazar')">
              <i class="fas fa-times"></i>
            </button>
          ` : 'â€”'}
        </td>
      </tr>
    `).join('');
  } catch(e) {
    showToast('Error al cargar precios', 'danger');
  }
}
function openValidarPrecio(loteId, med, precio, accion) {
  document.getElementById('precioLoteId').value = loteId;
  document.getElementById('precioAccion').value = accion;
  document.getElementById('precioMotivo').value = '';
  document.getElementById('modalPrecioTitle').textContent = accion === 'aprobar' ? 'Aprobar precio' : 'Rechazar precio';
  document.getElementById('precioResumen').innerHTML = `
    <div class="precio-detail">
      <span class="precio-label">Medicamento:</span> <strong>${med}</strong><br>
      <span class="precio-label">Precio propuesto:</span> <strong class="text-primary">${fmt(precio)}</strong>
    </div>
  `;
  document.getElementById('btnConfirmarPrecio').className = accion === 'aprobar' ? 'btn-primary-action' : 'btn-danger-action';
  document.getElementById('btnConfirmarPrecio').textContent = accion === 'aprobar' ? 'âœ“ Aprobar' : 'âœ— Rechazar';
  openModal('modalPrecio');
}
async function confirmarPrecio() {
  const id     = document.getElementById('precioLoteId').value;
  const accion = document.getElementById('precioAccion').value;
  const motivo = document.getElementById('precioMotivo').value.trim();
  if (accion === 'rechazar' && !motivo) return showToast('El motivo es requerido para rechazar', 'warning');
  try {
    const d = await apiPost('php/api/precios.php', { action: accion, id_lote: id, motivo });
    if (d.error) return showToast(d.error, 'danger');
    showToast(accion === 'aprobar' ? 'Precio aprobado correctamente' : 'Precio rechazado');
    closeModal('modalPrecio');
    loadPrecios();
    loadNotificaciones();
  } catch(e) {
    showToast('Error al procesar precio', 'danger');
  }
}
async function loadPagos() {
  try {
    const d = await (await fetch('php/api/pagos.php?tipo=pendientes')).json();
    const tbody = document.getElementById('tablaPagos');
    if (!d || d.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Sin modificaciones pendientes</td></tr>';
      return;
    }
    tbody.innerHTML = d.map(m => `
      <tr>
        <td>#${m.id_modificacion}</td>
        <td><strong>${m.folio_pago||'â€”'}</strong><br><small class="text-muted">${fmt(m.monto_total)}</small></td>
        <td>${m.paciente||'â€”'}</td>
        <td><small>${m.motivo||'â€”'}</small></td>
        <td>${m.solicitante||'â€”'}</td>
        <td><small>${fmtDate(m.fecha_solicitud)}</small></td>
        <td>${estatusBadge(m.estatus_pago||m.estatus)}</td>
        <td class="actions-cell">
          <button class="btn-action edit" title="Autorizar" onclick="accionPago(${m.id_modificacion},'aprobar')">
            <i class="fas fa-check"></i>
          </button>
          <button class="btn-action danger" title="Rechazar" onclick="accionPago(${m.id_modificacion},'rechazar')">
            <i class="fas fa-times"></i>
          </button>
        </td>
      </tr>
    `).join('');
  } catch(e) {
    showToast('Error al cargar pagos', 'danger');
  }
}
async function accionPago(id, accion) {
  const label = accion === 'aprobar' ? 'autorizar' : 'rechazar';
  if (!confirm(`Â¿Confirma ${label} la modificaciÃ³n #${id}?`)) return;
  try {
    const d = await apiPost('php/api/pagos.php', { action: accion, id_modificacion: id });
    if (d.error) return showToast(d.error, 'danger');
    showToast(accion === 'aprobar' ? 'ModificaciÃ³n autorizada' : 'ModificaciÃ³n rechazada');
    loadPagos();
    loadNotificaciones();
  } catch(e) {
    showToast('Error al procesar la modificaciÃ³n', 'danger');
  }
}
async function confirmarCancelacion() {
  const id_pago    = document.getElementById('cancelPagoId').value;
  const clave      = document.getElementById('cancelClave').value;
  const motivo     = document.getElementById('cancelMotivo').value.trim();
  if (!id_pago || !clave || !motivo) return showToast('Todos los campos son requeridos', 'warning');
  try {
    const d = await apiPost('php/api/pagos.php', { action:'cancelacion_emergencia', id_pago, clave_admin: clave, motivo });
    if (d.error) return showToast(d.error, 'danger');
    showToast('Pago cancelado de emergencia correctamente', 'warning');
    closeModal('modalCancelacion');
    document.getElementById('cancelPagoId').value = '';
    document.getElementById('cancelClave').value  = '';
    document.getElementById('cancelMotivo').value = '';
    loadPagos();
  } catch(e) {
    showToast('Error al cancelar pago', 'danger');
  }
}
async function loadMantenimiento() {
  try {
    const d = await (await fetch('php/api/mantenimiento.php')).json();
    const tbody = document.getElementById('tablaMantenimiento');
    if (!d.mantenimientos || d.mantenimientos.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin registros de mantenimiento</td></tr>';
      return;
    }
    tbody.innerHTML = d.mantenimientos.map(m => `
      <tr>
        <td>#${m.id_mantenimiento}</td>
        <td>${fmtDate(m.fecha_hora_inicio)}</td>
        <td>${m.duracion_minutos} min</td>
        <td>${m.descripcion||'â€”'}</td>
        <td>${estatusBadge(m.estatus)}</td>
        <td>
          <span class="badge bg-secondary">${m.total_alertas||0} alertas</span>
        </td>
        <td class="actions-cell">
          ${m.estatus === 'Programado' ? `
            <button class="btn-action danger" title="Cancelar" onclick="cancelarMantenimiento(${m.id_mantenimiento})">
              <i class="fas fa-times"></i>
            </button>
          ` : 'â€”'}
        </td>
      </tr>
    `).join('');
  } catch(e) {
    showToast('Error al cargar mantenimientos', 'danger');
  }
}
async function crearMantenimiento() {
  const fecha    = document.getElementById('mantFechaHora').value;
  const duracion = parseInt(document.getElementById('mantDuracion').value);
  const desc     = document.getElementById('mantDescripcion').value.trim();
  if (!fecha || !duracion || !desc) return showToast('Completa todos los campos', 'warning');
  if (duracion > 15 || duracion < 1) return showToast('La duraciÃ³n debe ser entre 1 y 15 minutos', 'warning');
  try {
    const d = await apiPost('php/api/mantenimiento.php', {
      action: 'crear', fecha_hora_inicio: fecha, duracion_minutos: duracion, descripcion: desc
    });
    if (d.error) return showToast(d.error, 'danger');
    showToast('Mantenimiento programado. Alertas enviadas a 5 Ã¡reas.', 'info');
    document.getElementById('mantFechaHora').value = '';
    document.getElementById('mantDuracion').value  = '';
    document.getElementById('mantDescripcion').value = '';
    loadMantenimiento();
  } catch(e) {
    showToast('Error al programar mantenimiento', 'danger');
  }
}
async function cancelarMantenimiento(id) {
  if (!confirm(`Â¿Cancelar el mantenimiento #${id}?`)) return;
  try {
    const d = await apiPost('php/api/mantenimiento.php', { action:'cancelar', id_mantenimiento: id });
    if (d.error) return showToast(d.error, 'danger');
    showToast('Mantenimiento cancelado');
    loadMantenimiento();
  } catch(e) {
    showToast('Error al cancelar mantenimiento', 'danger');
  }
}
async function loadReporte() {
  const mes  = document.getElementById('reporteMes').value;
  const tipo = currentReporte;
  const cont = document.getElementById('reporteContenido');
  cont.innerHTML = `<div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-3"></i><p>Cargando reporteâ€¦</p></div>`;
  try {
    const r = await fetch(`php/api/reportes.php?tipo=${tipo}&mes=${mes}`);
    if (r.status === 401) { window.location.href = 'index.html'; return; }
    const d = await r.json();
    if (d.error) { cont.innerHTML = `<div class="alert alert-danger m-3">${d.error}</div>`; return; }
    switch (tipo) {
      case 'financiero': renderReporteFinanciero(d, cont); break;
      case 'clinico':    renderReporteCliniko(d, cont);    break;
      case 'laboratorio':renderReporteLab(d, cont);        break;
      case 'citas':      renderReporteCitas(d, cont);      break;
      case 'farmacia':   renderReporteFarmacia(d, cont);   break;
      case 'inventario': renderReporteInventario(d, cont); break;
    }
  } catch(e) {
    cont.innerHTML = `<div class="alert alert-danger m-3">Error al cargar el reporte. Intenta de nuevo.</div>`;
  }
}
function sinDatosHTML(msg='Sin datos para el perÃ­odo seleccionado') {
  return `<div class="d-flex align-items-center justify-content-center h-100 text-muted py-4">
    <div class="text-center"><i class="fas fa-chart-bar fa-2x mb-2 opacity-25"></i><p class="mb-0 small">${msg}</p></div>
  </div>`;
}
function renderReporteFinanciero(d, cont) {
  const r = d.resumen || {};
  cont.innerHTML = `
    <div class="kpi-grid mb-4">
      <div class="kpi-card"><div class="kpi-icon green"><i class="fas fa-dollar-sign"></i></div>
        <div class="kpi-info"><div class="kpi-value">${fmt(r.total_mes)}</div><div class="kpi-label">Total del mes</div></div></div>
      <div class="kpi-card"><div class="kpi-icon blue"><i class="fas fa-receipt"></i></div>
        <div class="kpi-info"><div class="kpi-value">${r.num_pagos||0}</div><div class="kpi-label">Pagos procesados</div></div></div>
      <div class="kpi-card"><div class="kpi-icon purple"><i class="fas fa-money-bill"></i></div>
        <div class="kpi-info"><div class="kpi-value">${fmt(r.efectivo)}</div><div class="kpi-label">Efectivo</div></div></div>
      <div class="kpi-card"><div class="kpi-icon orange"><i class="fas fa-credit-card"></i></div>
        <div class="kpi-info"><div class="kpi-value">${fmt(r.tarjeta)}</div><div class="kpi-label">Tarjeta</div></div></div>
    </div>
    <div class="charts-grid">
      <div class="chart-card wide"><div class="chart-header"><h3 class="chart-title">Ingresos histÃ³ricos (12 meses)</h3></div>
        <div style="min-height:180px">${(d.historial||[]).length===0 ? sinDatosHTML() : '<canvas id="rptChartHistorial" height="100"></canvas>'}</div></div>
      <div class="chart-card"><div class="chart-header"><h3 class="chart-title">Desglose por concepto</h3></div>
        <div style="min-height:200px">${(d.desglose||[]).length===0 ? sinDatosHTML() : '<canvas id="rptChartDesglose" height="200"></canvas>'}</div></div>
    </div>
  `;
  setTimeout(() => {
    if ((d.historial||[]).length > 0) {
      destroyChart('rptChartHistorial');
      chartsCreated.rptChartHistorial = new Chart(document.getElementById('rptChartHistorial').getContext('2d'), {
        type:'bar', data:{
          labels: d.historial.map(h=>h.mes),
          datasets:[{label:'Ingresos',data:d.historial.map(h=>parseFloat(h.total||0)),backgroundColor:'rgba(59,130,246,0.7)',borderColor:'#3b82f6',borderWidth:1}]
        }, options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'$'+v.toLocaleString()}}}}
      });
    }
    if ((d.desglose||[]).length > 0) {
      destroyChart('rptChartDesglose');
      chartsCreated.rptChartDesglose = new Chart(document.getElementById('rptChartDesglose').getContext('2d'), {
        type:'pie', data:{
          labels: d.desglose.map(x=>x.tipo_concepto),
          datasets:[{data:d.desglose.map(x=>parseFloat(x.total||0)),backgroundColor:['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6']}]
        }, options:{responsive:true,plugins:{legend:{position:'bottom'}}}
      });
    }
  }, 100);
}
function renderReporteCliniko(d, cont) {
  const porMedico   = d.por_medico   || [];
  const porTipo     = d.por_tipo     || [];
  const diagnosticos = d.diagnosticos || [];
  const hayDatos = porMedico.length > 0;
  const tablaRows = hayDatos
    ? porMedico.map(m=>`<tr><td>${m.medico}</td><td><span class="badge bg-secondary">${m.tipo}</span></td><td><strong>${m.total_consultas}</strong></td></tr>`).join('')
    : `<tr><td colspan="3" class="text-center text-muted py-3">Sin consultas en este perÃ­odo</td></tr>`;
  cont.innerHTML = `
    <div class="charts-grid mb-4">
      <div class="chart-card">
        <div class="chart-header"><h3 class="chart-title">Consultas por especialidad</h3></div>
        <div id="wrapChartTipo" style="min-height:220px">${porTipo.length===0 ? sinDatosHTML() : '<canvas id="rptChartTipo" height="220"></canvas>'}</div>
      </div>
      <div class="chart-card wide">
        <div class="chart-header"><h3 class="chart-title">Top diagnÃ³sticos del mes</h3></div>
        <div id="wrapChartDiag" style="min-height:220px">${diagnosticos.length===0 ? sinDatosHTML() : '<canvas id="rptChartDiag" height="220"></canvas>'}</div>
      </div>
    </div>
    <h5 class="subsection-title">Detalle por mÃ©dico</h5>
    <div class="table-container">
      <table class="data-table"><thead><tr><th>MÃ©dico</th><th>Especialidad</th><th>Consultas</th></tr></thead>
      <tbody>${tablaRows}</tbody></table>
    </div>
  `;
  setTimeout(() => {
    const colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#a78bfa'];
    if (porTipo.length > 0) {
      destroyChart('rptChartTipo');
      chartsCreated.rptChartTipo = new Chart(document.getElementById('rptChartTipo').getContext('2d'), {
        type:'doughnut',
        data:{ labels: porTipo.map(x=>x.tipo), datasets:[{ data: porTipo.map(x=>parseInt(x.total)), backgroundColor: colors }] },
        options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } } }
      });
    }
    if (diagnosticos.length > 0) {
      destroyChart('rptChartDiag');
      chartsCreated.rptChartDiag = new Chart(document.getElementById('rptChartDiag').getContext('2d'), {
        type:'bar',
        data:{
          labels: diagnosticos.map(x => x.diagnostico.length > 40 ? x.diagnostico.substring(0,38)+'â€¦' : x.diagnostico),
          datasets:[{ label:'Casos', data: diagnosticos.map(x=>parseInt(x.frecuencia)), backgroundColor:'rgba(16,185,129,0.75)', borderColor:'#10b981', borderWidth:1 }]
        },
        options:{ indexAxis:'y', responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ stepSize:1 } } } }
      });
    }
  }, 100);
}
function renderReporteLab(d, cont) {
  cont.innerHTML = `
    <div class="charts-grid mb-4">
      <div class="chart-card"><div class="chart-header"><h3 class="chart-title">Estudios por tipo</h3></div>
        <canvas id="rptChartLabTipo" height="200"></canvas></div>
      <div class="chart-card wide">
        <div class="table-container">
          <table class="data-table"><thead><tr><th>Tipo</th><th>Nombre</th><th>Total</th><th>Completados</th><th>Pendientes</th></tr></thead>
          <tbody>${(d.estudios||[]).map(e=>`<tr><td>${e.tipo_estudio}</td><td>${e.nombre_estudio}</td><td>${e.total}</td><td class="text-success">${e.completados}</td><td class="text-warning">${e.pendientes}</td></tr>`).join('')}</tbody></table>
        </div>
      </div>
    </div>
  `;
  setTimeout(() => {
    destroyChart('rptChartLabTipo');
    chartsCreated.rptChartLabTipo = new Chart(document.getElementById('rptChartLabTipo').getContext('2d'), {
      type:'pie', data:{labels:(d.por_tipo||[]).map(x=>x.tipo_estudio),datasets:[{data:(d.por_tipo||[]).map(x=>x.total),backgroundColor:['#3b82f6','#10b981','#f59e0b','#ef4444']}]},
      options:{responsive:true,plugins:{legend:{position:'bottom'}}}
    });
  }, 100);
}
function renderReporteCitas(d, cont) {
  const resumen   = d.resumen    || [];
  const porMedico = d.por_medico || [];
  const tablaRows = porMedico.length > 0
    ? porMedico.map(m=>`<tr><td>${m.medico}</td><td><span class="badge bg-secondary">${m.tipo}</span></td><td><strong>${m.total}</strong></td></tr>`).join('')
    : `<tr><td colspan="3" class="text-center text-muted py-3">Sin citas en este perÃ­odo</td></tr>`;
  cont.innerHTML = `
    <div class="charts-grid mb-4">
      <div class="chart-card"><div class="chart-header"><h3 class="chart-title">Citas por estatus</h3></div>
        <div style="min-height:220px">${resumen.length===0 ? sinDatosHTML() : '<canvas id="rptChartCitasEst" height="220"></canvas>'}</div></div>
      <div class="chart-card wide">
        <div class="chart-header"><h3 class="chart-title">Citas por mÃ©dico</h3></div>
        <div class="table-container">
          <table class="data-table"><thead><tr><th>MÃ©dico</th><th>Especialidad</th><th>Total citas</th></tr></thead>
          <tbody>${tablaRows}</tbody></table>
        </div>
      </div>
    </div>
  `;
  setTimeout(() => {
    if (resumen.length > 0) {
      destroyChart('rptChartCitasEst');
      const coloresEst = {'Completada':'#10b981','Programada':'#3b82f6','Confirmada':'#06b6d4','Cancelada':'#ef4444','Pendiente':'#f59e0b'};
      chartsCreated.rptChartCitasEst = new Chart(document.getElementById('rptChartCitasEst').getContext('2d'), {
        type:'doughnut',
        data:{ labels: resumen.map(x=>x.estatus), datasets:[{ data: resumen.map(x=>x.total), backgroundColor: resumen.map(x=>coloresEst[x.estatus]||'#8b5cf6') }] },
        options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12 } } } }
      });
    }
  }, 100);
}
function renderReporteFarmacia(d, cont) {
  cont.innerHTML = `
    <h5 class="subsection-title">Medicamentos por caducar (&lt; 90 dÃ­as)</h5>
    <div class="table-container">
      <table class="data-table"><thead><tr><th>Medicamento</th><th>Lote</th><th>Existencia</th><th>Caducidad</th><th>DÃ­as restantes</th></tr></thead>
      <tbody>${(d.por_caducar||[]).map(m=>`<tr class="${m.dias_restantes<30?'row-danger':m.dias_restantes<60?'row-warning':''}"><td>${m.nombre_sustancia}</td><td>${m.numero_lote}</td><td>${m.existencia_actual}</td><td>${m.fecha_caducidad}</td><td><strong>${m.dias_restantes}</strong></td></tr>`).join('')}</tbody></table>
    </div>
    <h5 class="subsection-title mt-4">Top 10 mÃ¡s utilizados</h5>
    <div class="charts-grid">
      <div class="chart-card wide">
        <div style="min-height:200px">${(d.mas_usados||[]).length===0 ? sinDatosHTML('Sin medicamentos dispensados registrados') : '<canvas id="rptChartMasUsados" height="100"></canvas>'}</div>
      </div>
    </div>
  `;
  setTimeout(() => {
    if ((d.mas_usados||[]).length > 0) {
      destroyChart('rptChartMasUsados');
      chartsCreated.rptChartMasUsados = new Chart(document.getElementById('rptChartMasUsados').getContext('2d'), {
        type:'bar', data:{labels:d.mas_usados.map(x=>x.nombre_sustancia),datasets:[{label:'Unidades',data:d.mas_usados.map(x=>x.total_dispensado),backgroundColor:'rgba(139,92,246,0.7)',borderColor:'#8b5cf6',borderWidth:1}]},
        options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}}}
      });
    }
  }, 100);
}
function renderReporteInventario(d, cont) {
  cont.innerHTML = `
    <h5 class="subsection-title">Estado del inventario de insumos</h5>
    <div class="table-container">
      <table class="data-table"><thead><tr><th>ArtÃ­culo</th><th>CategorÃ­a</th><th>Ãrea</th><th>Stock actual</th><th>Stock mÃ­nimo</th><th>Estado</th></tr></thead>
      <tbody>${(d.insumos||[]).map(i=>`<tr><td>${i.nombre_articulo}</td><td>${i.categoria||'â€”'}</td><td>${i.area_asignada||'â€”'}</td><td>${i.stock_actual}</td><td>${i.stock_minimo}</td><td>${estatusBadge(i.estado)}</td></tr>`).join('')}</tbody></table>
    </div>
    <h5 class="subsection-title mt-4">Equipos mÃ©dicos â€“ revisiones</h5>
    <div class="table-container">
      <table class="data-table"><thead><tr><th>Equipo</th><th>Estatus</th><th>Ãšlt. mantenimiento</th><th>PrÃ³xima revisiÃ³n</th><th>DÃ­as</th></tr></thead>
      <tbody>${(d.equipos||[]).map(e=>`<tr class="${e.dias_para_revision<7?'row-danger':e.dias_para_revision<30?'row-warning':''}"><td>${e.nombre_articulo}</td><td>${e.estatus_equipo||'â€”'}</td><td>${e.fecha_ultimo_mantenimiento||'â€”'}</td><td>${e.proxima_revision||'â€”'}</td><td><strong>${e.dias_para_revision??'â€”'}</strong></td></tr>`).join('')}</tbody></table>
    </div>
  `;
}
async function loadAuditoria() {
  const desde   = document.getElementById('auditDesde').value;
  const hasta   = document.getElementById('auditHasta').value;
  const modulo  = document.getElementById('auditModulo').value;
  const accion  = document.getElementById('auditAccion').value;
  try {
    const params = new URLSearchParams({ desde, hasta, modulo, accion, pagina: auditPage });
    const d = await (await fetch(`php/api/auditoria.php?${params}`)).json();
    const sel = document.getElementById('auditModulo');
    if (sel.options.length <= 1) {
      (d.modulos||[]).forEach(m => {
        sel.innerHTML += `<option value="${m}">${m}</option>`;
      });
    }
    const tbody = document.getElementById('tablaAudit');
    if (!d.logs || d.logs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin registros para los filtros seleccionados</td></tr>';
      document.getElementById('auditPagination').innerHTML = '';
      return;
    }
    tbody.innerHTML = d.logs.map(l => `
      <tr>
        <td><small>${fmtDate(l.fecha_hora)}</small></td>
        <td>${l.usuario_nombre||'â€”'}<br><small class="text-muted">${l.usuario_tipo||''}</small></td>
        <td><small>${l.nombre_rol||'â€”'}</small></td>
        <td><span class="badge bg-secondary">${l.modulo}</span></td>
        <td>${l.accion}</td>
        <td><small>${l.ip_equipo||'â€”'}</small></td>
        <td><small class="text-muted">${l.detalle_cambio ? l.detalle_cambio.substring(0,80)+'â€¦' : 'â€”'}</small></td>
      </tr>
    `).join('');
    renderPagination('auditPagination', auditPage, d.total_paginas, (p) => { auditPage=p; loadAuditoria(); });
  } catch(e) {
    showToast('Error al cargar la bitÃ¡cora', 'danger');
  }
}
function renderPagination(containerId, current, total, callback) {
  const cont = document.getElementById(containerId);
  if (total <= 1) { cont.innerHTML = ''; return; }
  let html = '<nav><ul class="pagination-list">';
  html += `<li class="${current<=1?'disabled':''}"><a href="#" onclick="return false;" data-p="${current-1}">â€¹</a></li>`;
  for (let i = Math.max(1,current-2); i <= Math.min(total,current+2); i++) {
    html += `<li class="${i===current?'active':''}"><a href="#" onclick="return false;" data-p="${i}">${i}</a></li>`;
  }
  html += `<li class="${current>=total?'disabled':''}"><a href="#" onclick="return false;" data-p="${current+1}">â€º</a></li>`;
  html += '</ul></nav>';
  cont.innerHTML = html;
  cont.querySelectorAll('a[data-p]').forEach(a => {
    a.addEventListener('click', () => {
      const p = parseInt(a.dataset.p);
      if (p >= 1 && p <= total) callback(p);
    });
  });
}
