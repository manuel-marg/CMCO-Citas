/**
 * CMCO-Citas - JavaScript de Interfaz y Control
 * Centro Médico Comunitario OscaryAnny / ADH
 * 
 * Soporta Multi-Vista: Vista Diaria (3 Consultorios), Vista Semanal (7 Días) y Vista Mensual (Calendario).
 */

// Estado Global de la Aplicación
const AppState = {
    vistaActual: 'dia', // 'dia', 'semana', 'mes'
    fechaActual: new Date().toISOString().slice(0, 10),
    mesActual: new Date().toISOString().slice(0, 7),
    filtroConsultorio: '',
    filtroMedico: '',
    tasaBcv: 888.8909,
    fechaValorBcv: '',
    consultorios: [],
    medicos: [],
    horarios: {},
    estados: {},
    tipos: {},
    formasPago: {},
    citasActuales: [],
    citasMap: {},
    citasPorDia: {},
    citaSeleccionada: null,
    // Estado de paginación y búsqueda para Directorio de Pacientes
    pacientesPage: 1,
    pacientesLimit: 50,
    pacientesSearch: '',
    pacientesTotal: 0,
    pacientesTotalFiltrados: 0,
    pacientesTotalPages: 1
};

/**
 * -------------------------------------------------------------
 * UTILIDADES DE FORMATEO DE HORAS (12 Horas con AM/PM)
 * -------------------------------------------------------------
 */

/**
 * Convierte cualquier formato de hora militar ('HH:mm:ss' o 'HH:mm')
 * a formato estándar de 12 horas con indicador AM/PM (ej: '01:00 PM', '08:00 AM')
 */
function formatearHora12(horaStr, conCero = true) {
    if (!horaStr) return '';
    const partes = horaStr.split(':');
    let horas = parseInt(partes[0], 10);
    const minutos = partes[1] || '00';
    if (isNaN(horas)) return horaStr;
    const ampm = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12;
    horas = horas ? horas : 12;
    const horasStr = conCero ? String(horas).padStart(2, '0') : String(horas);
    return `${horasStr}:${minutos} ${ampm}`;
}

/**
 * Desglosa la hora en formato 12 horas separando la hora numérica y el indicador AM/PM
 * ej: { hora: '01:00', ampm: 'PM' } para '13:00:00'
 */
function obtenerComponentesHora12(horaStr) {
    if (!horaStr) return { hora: '', ampm: '' };
    const partes = horaStr.split(':');
    let horas = parseInt(partes[0], 10);
    const minutos = partes[1] || '00';
    if (isNaN(horas)) return { hora: horaStr, ampm: '' };
    const ampm = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12;
    horas = horas ? horas : 12;
    const horasStr = String(horas).padStart(2, '0');
    return {
        hora: `${horasStr}:${minutos}`,
        ampm: ampm
    };
}

/**
 * Acorta un nombre completo para mostrar como máximo un número determinado de palabras (por defecto 2)
 * ej: "María Auxiliadora Aguilera" -> "María Auxiliadora"
 */
function acortarNombre(nombreCompleto, maxPalabras = 2) {
    if (!nombreCompleto) return '';
    const palabras = nombreCompleto.trim().split(/\s+/);
    if (palabras.length <= maxPalabras) return nombreCompleto.trim();
    return palabras.slice(0, maxPalabras).join(' ');
}

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

async function initApp() {
    // Configurar fechas iniciales
    document.getElementById('agenda_fecha_selector').value = AppState.fechaActual;
    document.getElementById('agenda_mes_selector').value = AppState.mesActual;
    document.getElementById('liq_mes_selector').value = AppState.mesActual;
    document.getElementById('ben_mes_selector').value = AppState.mesActual;

    // Cargar Tasa BCV
    await cargarTasaBcv();

    // Cargar datos y renderizar vista activa
    await cargarAgendaSegunVista();

    // Configurar autocompletado de pacientes
    configurarAutocompletePacientes();

    // Soporte para navegación directa por hash de URL (ej: #medicos, #liquidacion)
    const initialHash = window.location.hash.replace('#', '');
    if (initialHash && ['agenda', 'liquidacion', 'beneficios', 'pacientes', 'medicos'].includes(initialHash)) {
        showSection(initialHash);
    }

    // Inicializar tooltips de Bootstrap
    inicializarTooltips();

    window.addEventListener('hashchange', () => {
        const h = window.location.hash.replace('#', '');
        if (h && ['agenda', 'liquidacion', 'beneficios', 'pacientes', 'medicos'].includes(h)) {
            showSection(h);
        }
    });
}

function inicializarTooltips() {
    try {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(el => {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el);
            }
        });
    } catch (e) {
        // Fallback a titles nativos
    }
}

/**
 * -------------------------------------------------------------
 * GESTIÓN DE NAVEGACIÓN ENTRE SECCIONES (TABS PRINCIPALES)
 * -------------------------------------------------------------
 */
function showSection(sectionId) {
    const sections = ['agenda', 'liquidacion', 'beneficios', 'pacientes', 'medicos'];
    sections.forEach(s => {
        const el = document.getElementById(`section-${s}`);
        const tab = document.getElementById(`tab-${s}`);
        if (el) el.classList.add('d-none');
        if (tab) tab.classList.remove('active');
    });

    const activeEl = document.getElementById(`section-${sectionId}`);
    const activeTab = document.getElementById(`tab-${sectionId}`);
    if (activeEl) activeEl.classList.remove('d-none');
    if (activeTab) activeTab.classList.add('active');

    // Mantener sincronizado el hash de la URL
    if (window.location.hash !== '#' + sectionId) {
        history.replaceState(null, '', '#' + sectionId);
    }

    // Cargas perezosas según sección
    if (sectionId === 'agenda') cargarAgendaSegunVista();
    if (sectionId === 'liquidacion') cargarLiquidacion();
    if (sectionId === 'beneficios') cargarBeneficios();
    if (sectionId === 'pacientes') cargarDirectorioPacientes();
    if (sectionId === 'medicos') cargarCatalogoMedicos();
}

/**
 * -------------------------------------------------------------
 * GESTIÓN DE SUB-VISTAS DE AGENDA: DÍA / SEMANA / MES
 * -------------------------------------------------------------
 */
function cambiarVistaAgenda(nuevaVista) {
    AppState.vistaActual = nuevaVista;

    // Actualizar botones de vista
    ['dia', 'semana', 'mes'].forEach(v => {
        const btn = document.getElementById(`btn-vista-${v}`);
        if (btn) {
            if (v === nuevaVista) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }
    });

    // Alternar wrappers de navegación
    const navDia = document.getElementById('wrapper_nav_dia');
    const navSemana = document.getElementById('wrapper_nav_semana');
    const navMes = document.getElementById('wrapper_nav_mes');

    navDia.classList.add('d-none');
    navSemana.classList.add('d-none');
    navMes.classList.add('d-none');

    if (nuevaVista === 'dia') navDia.classList.remove('d-none');
    if (nuevaVista === 'semana') navSemana.classList.remove('d-none');
    if (nuevaVista === 'mes') navMes.classList.remove('d-none');

    // Alternar contenedores de visualización
    const cDia = document.getElementById('container_vista_dia');
    const cSemana = document.getElementById('container_vista_semana');
    const cMes = document.getElementById('container_vista_mes');

    cDia.classList.add('d-none');
    cSemana.classList.add('d-none');
    cMes.classList.add('d-none');

    if (nuevaVista === 'dia') cDia.classList.remove('d-none');
    if (nuevaVista === 'semana') cSemana.classList.remove('d-none');
    if (nuevaVista === 'mes') cMes.classList.remove('d-none');

    // Cargar datos
    cargarAgendaSegunVista();
}

/**
 * Navegación temporal genérica (< / >) según la vista activa
 */
function navegarPeriodo(delta) {
    if (AppState.vistaActual === 'dia') {
        const d = parseLocalDate(AppState.fechaActual);
        d.setDate(d.getDate() + delta);
        AppState.fechaActual = formatDateISO(d);
        document.getElementById('agenda_fecha_selector').value = AppState.fechaActual;
    } else if (AppState.vistaActual === 'semana') {
        const d = parseLocalDate(AppState.fechaActual);
        d.setDate(d.getDate() + (delta * 7));
        AppState.fechaActual = formatDateISO(d);
    } else if (AppState.vistaActual === 'mes') {
        const partes = AppState.mesActual.split('-');
        let anio = parseInt(partes[0], 10);
        let mes = parseInt(partes[1], 10) - 1; // 0-based
        const d = new Date(anio, mes + delta, 1);
        const nuevoMesStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        AppState.mesActual = nuevoMesStr;
        AppState.fechaActual = `${nuevoMesStr}-01`;
        document.getElementById('agenda_mes_selector').value = nuevoMesStr;
    }

    cargarAgendaSegunVista();
}

function irAHoy() {
    const hoy = new Date();
    AppState.fechaActual = formatDateISO(hoy);
    AppState.mesActual = AppState.fechaActual.slice(0, 7);
    document.getElementById('agenda_fecha_selector').value = AppState.fechaActual;
    document.getElementById('agenda_mes_selector').value = AppState.mesActual;
    cargarAgendaSegunVista();
}

function actualizarFechaDesdeInput() {
    AppState.fechaActual = document.getElementById('agenda_fecha_selector').value;
    AppState.mesActual = AppState.fechaActual.slice(0, 7);
    document.getElementById('agenda_mes_selector').value = AppState.mesActual;
    cargarAgendaSegunVista();
}

function actualizarMesDesdeInput() {
    AppState.mesActual = document.getElementById('agenda_mes_selector').value;
    AppState.fechaActual = `${AppState.mesActual}-01`;
    document.getElementById('agenda_fecha_selector').value = AppState.fechaActual;
    cargarAgendaSegunVista();
}

function toggleFiltrosAgenda() {
    const panel = document.getElementById('panel_filtros_metricas');
    const btn = document.getElementById('btn-toggle-filtros');
    if (!panel || !btn) return;

    if (panel.classList.contains('d-none')) {
        panel.classList.remove('d-none');
        btn.classList.add('active', 'btn-primary');
        btn.classList.remove('btn-outline-primary');
    } else {
        panel.classList.add('d-none');
        btn.classList.remove('active', 'btn-primary');
        btn.classList.add('btn-outline-primary');
        // Limpiar filtros al ocultar tal como solicitó el usuario
        limpiarFiltrosAgenda();
    }
}

function aplicarFiltrosAgenda() {
    AppState.filtroConsultorio = document.getElementById('filtro_consultorio_agenda').value;
    AppState.filtroMedico = document.getElementById('filtro_medico_agenda').value;
    cargarAgendaSegunVista();
}

function limpiarFiltrosAgenda() {
    document.getElementById('filtro_consultorio_agenda').value = '';
    document.getElementById('filtro_medico_agenda').value = '';
    AppState.filtroConsultorio = '';
    AppState.filtroMedico = '';
    cargarAgendaSegunVista();
}

/**
 * -------------------------------------------------------------
 * CARGA DE DATOS CENTRALIZADA SEGÚN LA VISTA
 * -------------------------------------------------------------
 */
async function cargarAgendaSegunVista() {
    let url = 'api/citas.php';
    const params = new URLSearchParams();

    if (AppState.filtroConsultorio) params.append('consultorio_id', AppState.filtroConsultorio);
    if (AppState.filtroMedico) params.append('medico_id', AppState.filtroMedico);

    if (AppState.vistaActual === 'dia') {
        params.append('fecha', AppState.fechaActual);
    } else if (AppState.vistaActual === 'semana') {
        const { inicio, fin } = obtenerLimitesSemana(AppState.fechaActual);
        params.append('fecha_inicio', inicio);
        params.append('fecha_fin', fin);
        actualizarLabelSemana(inicio, fin);
    } else if (AppState.vistaActual === 'mes') {
        const anioMes = AppState.mesActual;
        const partes = anioMes.split('-');
        const anio = parseInt(partes[0], 10);
        const mes = parseInt(partes[1], 10);
        const ultimoDia = new Date(anio, mes, 0).getDate();
        params.append('fecha_inicio', `${anioMes}-01`);
        params.append('fecha_fin', `${anioMes}-${String(ultimoDia).padStart(2, '0')}`);
    }

    url += `?${params.toString()}`;

    try {
        const res = await fetch(url);
        const json = await res.json();

        if (json.success) {
            const data = json.data;
            AppState.citasActuales = data.citas;
            AppState.citasMap = data.citas_map || {};
            AppState.citasPorDia = data.citas_por_dia || {};
            AppState.consultorios = data.consultorios;
            AppState.medicos = data.medicos;
            AppState.horarios = data.horarios;
            AppState.estados = data.estados;
            AppState.tipos = data.tipos;
            AppState.formasPago = data.formas_pago;

            // Poblar dropdown de médicos en el filtro si está vacío
            actualizarDropdownFiltroMedicos();

            // Actualizar métricas del período
            actualizarMetricas(data.metricas, data.citas);

            // Renderizar la vista correspondiente
            if (AppState.vistaActual === 'dia') {
                renderizarVistaDia();
            } else if (AppState.vistaActual === 'semana') {
                renderizarVistaSemana();
            } else if (AppState.vistaActual === 'mes') {
                renderizarVistaMes();
            }
        }
    } catch (e) {
        console.error('Error al cargar agenda:', e);
    }
}

function actualizarDropdownFiltroMedicos() {
    const sel = document.getElementById('filtro_medico_agenda');
    if (sel && sel.options.length <= 1) {
        AppState.medicos.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.text = m.nombre;
            sel.appendChild(opt);
        });
    }
}

function actualizarMetricas(metricas, citas) {
    if (!citas) return;
    let total = citas.length;
    let confirmadas = 0;
    let pagadas = 0;
    let montoUsd = 0.0;

    citas.forEach(c => {
        if (c.estado === 'Confirmado') confirmadas++;
        if (c.estado === 'Pagado' || c.estado === 'Facturado') {
            pagadas++;
            montoUsd += parseFloat(c.precio_usd || 0);
        }
    });

    document.getElementById('metric_total').innerText = total;
    document.getElementById('metric_confirmadas').innerText = confirmadas;
    document.getElementById('metric_pagadas').innerText = pagadas;
    document.getElementById('metric_monto_usd').innerText = `$${formatearNumero(montoUsd, 2)}`;
}

/**
 * -------------------------------------------------------------
 * 1. RENDERIZADO: VISTA DIARIA (3 CONSULTORIOS)
 * -------------------------------------------------------------
 */
function renderizarVistaDia() {
    const container = document.getElementById('grid_slots_body');
    let html = '';

    const horarios = Object.keys(AppState.horarios);
    let consultorios = AppState.consultorios;

    // Si hay filtro de consultorio activo
    if (AppState.filtroConsultorio) {
        consultorios = consultorios.filter(c => c.id == AppState.filtroConsultorio);
    }

    horarios.forEach(hora => {
        const comp = obtenerComponentesHora12(hora);
        
        html += `
            <div class="row g-0 slot-row">
                <!-- Columna Hora (Formato 12h con AM/PM) -->
                <div class="col-1 time-cell">
                    <span>${comp.hora}</span>
                    <small class="text-muted" style="font-size: 0.72rem; font-weight: 600;">${comp.ampm}</small>
                </div>
        `;

        // Columnas de Consultorios
        consultorios.forEach(cons => {
            const key = `${hora}_${cons.id}`;
            const slotData = AppState.citasMap[key];
            const citasEnSlot = Array.isArray(slotData) ? slotData : (slotData ? [slotData] : []);

            // Filtrar por médico si aplica
            const citasFiltradas = AppState.filtroMedico 
                ? citasEnSlot.filter(c => c.medico_id == AppState.filtroMedico) 
                : citasEnSlot;

            html += `<div class="col slot-cell">`;

            if (citasFiltradas.length > 0) {
                citasFiltradas.forEach(cita => {
                    const estadoInfo = AppState.estados[cita.estado] || { emoji: '📌', label: cita.estado, badge: 'primary', color: '#4f90a5' };
                    const precio = parseFloat(cita.precio_usd || 0);
                    const precioBadgeClass = cita.estado === 'Facturado' ? 'bg-dark' : (cita.estado === 'Pagado' ? 'bg-success' : 'bg-primary');

                    html += `
                        <div class="cita-card mb-1" style="border-left-color: ${estadoInfo.color}; cursor: pointer;" onclick="abrirDetalleCita(${cita.id})">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="badge bg-${estadoInfo.badge} text-white" style="font-size: 0.72rem;">
                                        ${estadoInfo.emoji} ${estadoInfo.label}
                                    </span>
                                    <span class="cita-precio-badge ${precioBadgeClass} text-white">
                                        $${precio.toFixed(0)}
                                    </span>
                                </div>
                                <div class="cita-paciente text-truncate" title="${cita.paciente_nombre}">
                                    ${cita.paciente_nombre}
                                </div>
                                <div class="cita-subinfo text-truncate">
                                    ${cita.paciente_cedula ? `C.I: ${cita.paciente_cedula}` : ''} 
                                    ${cita.paciente_telefono ? `· Tel: ${cita.paciente_telefono}` : ''}
                                </div>
                            </div>
                            <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center">
                                <span class="cita-medico text-truncate" title="${cita.medico_nombre}">
                                    <i class="bi bi-person-fill"></i> ${cita.medico_nombre}
                                </span>
                                <span class="badge bg-light text-muted border" style="font-size: 0.68rem;">
                                    ${cita.tipo}
                                </span>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += `
                    <button class="btn-empty-slot" onclick="abrirModalNuevoAgendamiento('${hora}', ${cons.id}, '${AppState.fechaActual}')">
                        <i class="bi bi-plus-circle"></i> Agendar
                    </button>
                `;
            }

            html += `</div>`;
        });

        html += `</div>`;
    });

    container.innerHTML = html;
}

/**
 * -------------------------------------------------------------
 * 2. RENDERIZADO: VISTA SEMANAL (7 COLUMNAS)
 * -------------------------------------------------------------
 */
function renderizarVistaSemana() {
    const container = document.getElementById('grid_semana_body');
    const { inicio, fin, dias } = obtenerLimitesSemana(AppState.fechaActual);
    const hoyStr = new Date().toISOString().slice(0, 10);
    const nombresDias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    let html = '';

    dias.forEach((fechaDia, idx) => {
        const esHoy = fechaDia === hoyStr;
        const citasDelDia = AppState.citasPorDia[fechaDia] || [];
        const diaNum = parseLocalDate(fechaDia).getDate();
        const mesNombreCorto = parseLocalDate(fechaDia).toLocaleDateString('es-ES', { month: 'short' });

        html += `
            <div class="col-md week-day-col ${esHoy ? 'is-today' : ''}">
                <div class="week-day-header">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="week-day-name">${nombresDias[idx]}</span>
                        <button class="btn btn-sm btn-outline-primary py-0 px-1 border-0" onclick="abrirModalNuevoAgendamiento('08:00:00', 1, '${fechaDia}')" title="Agendar cita este día">
                            <i class="bi bi-plus-circle-fill"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-center gap-1">
                        <span class="week-day-number">${diaNum}</span>
                        <span class="small text-muted text-capitalize">${mesNombreCorto}</span>
                    </div>
                    <div class="mt-1">
                        <span class="badge ${citasDelDia.length > 0 ? 'bg-primary' : 'bg-light text-muted border'}" style="font-size: 0.7rem;">
                            ${citasDelDia.length} ${citasDelDia.length === 1 ? 'cita' : 'citas'}
                        </span>
                        ${esHoy ? '<span class="badge bg-info text-dark ms-1" style="font-size: 0.65rem;">HOY</span>' : ''}
                    </div>
                </div>

                <div class="week-citas-list">
        `;

        if (citasDelDia.length === 0) {
            html += `
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-calendar-x d-block fs-3 opacity-25 mb-1"></i>
                    Sin citas
                </div>
            `;
        } else {
            citasDelDia.forEach(c => {
                const estadoInfo = AppState.estados[c.estado] || { emoji: '📌', color: '#4f90a5' };
                const badgeConsClass = c.consultorio_id == 1 ? 'badge-c1' : (c.consultorio_id == 2 ? 'badge-c2' : 'badge-c3');
                const consAbbr = c.consultorio_id == 1 ? 'C1' : (c.consultorio_id == 2 ? 'C2' : 'C3');

                html += `
                    <div class="week-cita-item" style="border-left-color: ${estadoInfo.color};" onclick="abrirDetalleCita(${c.id})">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold small text-dark">${formatearHora12(c.hora)}</span>
                            <span class="badge ${badgeConsClass}" style="font-size: 0.68rem;" title="${c.consultorio_nombre}">${consAbbr}</span>
                        </div>
                        <div class="fw-bold text-truncate" style="font-size: 0.84rem;" title="${c.paciente_nombre}">
                            ${c.paciente_nombre}
                        </div>
                        <div class="small text-muted text-truncate" style="font-size: 0.75rem;">
                            ${c.medico_nombre}
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top" style="font-size: 0.72rem;">
                            <span>${estadoInfo.emoji} ${c.estado}</span>
                            <span class="fw-bold text-success">$${parseFloat(c.precio_usd).toFixed(0)}</span>
                        </div>
                    </div>
                `;
            });
        }

        html += `
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * -------------------------------------------------------------
 * 3. RENDERIZADO: VISTA MENSUAL (CALENDARIO)
 * -------------------------------------------------------------
 */
function renderizarVistaMes() {
    const container = document.getElementById('grid_mes_body');
    const anioMes = AppState.mesActual;
    const partes = anioMes.split('-');
    const anio = parseInt(partes[0], 10);
    const mes = parseInt(partes[1], 10) - 1; // 0-based

    const primerDia = new Date(anio, mes, 1);
    const ultimoDia = new Date(anio, mes + 1, 0);
    const hoyStr = new Date().toISOString().slice(0, 10);

    // Ajustar día de inicio a Lunes (1 = Lunes, 0 = Domingo en JS)
    let diaSemanaInicio = primerDia.getDay(); // 0: Dom, 1: Lun, ..., 6: Sab
    let offsetLunes = diaSemanaInicio === 0 ? 6 : diaSemanaInicio - 1; // 0 para Lun, 6 para Dom

    let celdas = [];

    // Días del mes previo
    const mesPrevioUltimoDia = new Date(anio, mes, 0).getDate();
    for (let i = offsetLunes - 1; i >= 0; i--) {
        const diaNum = mesPrevioUltimoDia - i;
        const fechaStr = formatDateISO(new Date(anio, mes - 1, diaNum));
        celdas.push({ fecha: fechaStr, numero: diaNum, otroMes: true });
    }

    // Días del mes corriente
    for (let d = 1; d <= ultimoDia.getDate(); d++) {
        const fechaStr = `${anioMes}-${String(d).padStart(2, '0')}`;
        celdas.push({ fecha: fechaStr, numero: d, otroMes: false });
    }

    // Días del mes siguiente para completar múltiplos de 7
    const resto = celdas.length % 7;
    if (resto !== 0) {
        const diasFaltantes = 7 - resto;
        for (let d = 1; d <= diasFaltantes; d++) {
            const fechaStr = formatDateISO(new Date(anio, mes + 1, d));
            celdas.push({ fecha: fechaStr, numero: d, otroMes: true });
        }
    }

    let html = '';

    celdas.forEach(c => {
        const esHoy = c.fecha === hoyStr;
        const citasDelDia = AppState.citasPorDia[c.fecha] || [];

        html += `
            <div class="calendar-day-cell ${c.otroMes ? 'other-month' : ''} ${esHoy ? 'is-today' : ''}" 
                 onclick="saltarADiaDesdeCalendario('${c.fecha}')" title="Clic para ver la agenda del ${c.fecha}">
                <div class="calendar-day-top">
                    <span class="day-number">${c.numero}</span>
                    ${citasDelDia.length > 0 ? `<span class="badge bg-primary" style="font-size: 0.68rem;">${citasDelDia.length}</span>` : ''}
                </div>

                <div class="cal-citas-chips">
        `;

        // Mostrar hasta 3 citas
        const maxChips = 3;
        const mostrar = citasDelDia.slice(0, maxChips);
        mostrar.forEach(cita => {
            const estadoInfo = AppState.estados[cita.estado] || { emoji: '📌', label: cita.estado, color: '#4f90a5' };
            const nombreCorto = acortarNombre(cita.paciente_nombre, 2);
            const emojiHtml = estadoInfo.emoji ? `<span class="cita-chip-emoji">${estadoInfo.emoji}</span>` : '';
            html += `
                <div class="cal-cita-chip" 
                     style="border-left-color: ${estadoInfo.color}; cursor: pointer;" 
                     onclick="event.stopPropagation(); abrirDetalleCita(${cita.id})"
                     title="${formatearHora12(cita.hora)} ${estadoInfo.emoji || ''} ${cita.paciente_nombre} · ${cita.medico_nombre} (${estadoInfo.label || cita.estado}) - Clic para ver y editar">
                    <strong>${formatearHora12(cita.hora)}</strong> ${emojiHtml} ${nombreCorto}
                </div>
            `;
        });

        if (citasDelDia.length > maxChips) {
            html += `
                <div class="small text-muted fw-bold ps-1" style="font-size: 0.68rem;">
                    +${citasDelDia.length - maxChips} más...
                </div>
            `;
        }

        html += `
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function saltarADiaDesdeCalendario(fechaStr) {
    AppState.fechaActual = fechaStr;
    document.getElementById('agenda_fecha_selector').value = fechaStr;
    cambiarVistaAgenda('dia');
}

/**
 * -------------------------------------------------------------
 * AUXILIARES DE FECHAS
 * -------------------------------------------------------------
 */
function parseLocalDate(fechaStr) {
    const [y, m, d] = fechaStr.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function formatDateISO(dateObj) {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function obtenerLimitesSemana(fechaStr) {
    const d = parseLocalDate(fechaStr);
    const diaSemana = d.getDay(); // 0 = Domingo, 1 = Lunes
    const diffALunes = diaSemana === 0 ? -6 : 1 - diaSemana;
    
    const lunes = new Date(d);
    lunes.setDate(d.getDate() + diffALunes);

    const dias = [];
    for (let i = 0; i < 7; i++) {
        const dia = new Date(lunes);
        dia.setDate(lunes.getDate() + i);
        dias.push(formatDateISO(dia));
    }

    return {
        inicio: dias[0],
        fin: dias[6],
        dias: dias
    };
}

function actualizarLabelSemana(inicio, fin) {
    const d1 = parseLocalDate(inicio);
    const d2 = parseLocalDate(fin);
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const texto = `Semana del ${d1.getDate()} ${meses[d1.getMonth()]} al ${d2.getDate()} ${meses[d2.getMonth()]} ${d2.getFullYear()}`;
    const el = document.getElementById('label_periodo_semana');
    if (el) el.innerText = texto;
}

/**
 * -------------------------------------------------------------
 * GESTIÓN DE TASA BCV
 * -------------------------------------------------------------
 */
async function cargarTasaBcv() {
    try {
        const res = await fetch('api/bcv.php');
        const json = await res.json();
        if (json.success && json.data) {
            AppState.tasaBcv = parseFloat(json.data.tasa_usd_ves);
            AppState.fechaValorBcv = json.data.fecha_valor;
            const rateEl = document.getElementById('nav-bcv-rate');
            if (rateEl) rateEl.innerText = `Bs. ${formatearNumero(AppState.tasaBcv, 2)}`;
        }
    } catch (e) {
        console.error('Error cargando tasa BCV:', e);
    }
}

function openBcvModal() {
    document.getElementById('bcv_input_tasa').value = AppState.tasaBcv;
    document.getElementById('bcv_input_fecha_valor').value = AppState.fechaValorBcv || AppState.fechaActual;
    const modal = new bootstrap.Modal(document.getElementById('modalBcv'));
    modal.show();
}

async function guardarTasaBcv(e) {
    e.preventDefault();
    const tasa = parseFloat(document.getElementById('bcv_input_tasa').value);
    const fecha_valor = document.getElementById('bcv_input_fecha_valor').value;
    const fuente = document.getElementById('bcv_input_fuente').value;

    try {
        const res = await fetch('api/bcv.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tasa, fecha_valor, fuente })
        });
        const json = await res.json();
        if (json.success) {
            AppState.tasaBcv = tasa;
            AppState.fechaValorBcv = fecha_valor;
            const navRate = document.getElementById('nav-bcv-rate');
            if (navRate) navRate.innerText = `Bs. ${formatearNumero(tasa, 2)}`;
            bootstrap.Modal.getInstance(document.getElementById('modalBcv')).hide();
            mostrarToast('Tasa BCV actualizada exitosamente');
            if (AppState.citaSeleccionada) recalcularConversionCobro();
        } else {
            mostrarToast(json.message || 'Error al guardar tasa', 'danger');
        }
    } catch (err) {
        mostrarToast('Error de comunicación con el servidor', 'danger');
    }
}

/**
 * -------------------------------------------------------------
 * MODAL: AGENDAR CITA
 * -------------------------------------------------------------
 */
function abrirModalNuevoAgendamiento(hora = '08:00:00', consultorioId = 1, fechaDeseada = null) {
    document.getElementById('formAgendar').reset();
    document.getElementById('agendar_paciente_id').value = '';
    document.getElementById('agendar_fecha').value = fechaDeseada || AppState.fechaActual;

    // Llenar select horas
    const selectHora = document.getElementById('agendar_hora');
    selectHora.innerHTML = '';
    Object.keys(AppState.horarios).forEach(h => {
        selectHora.innerHTML += `<option value="${h}" ${h === hora ? 'selected' : ''}>${AppState.horarios[h]}</option>`;
    });

    // Llenar select consultorios
    const selectCons = document.getElementById('agendar_consultorio_id');
    selectCons.innerHTML = '';
    AppState.consultorios.forEach(c => {
        selectCons.innerHTML += `<option value="${c.id}" ${c.id === consultorioId ? 'selected' : ''}>${c.nombre}</option>`;
    });

    // Llenar select médicos
    const selectMed = document.getElementById('agendar_medico_id');
    selectMed.innerHTML = '';
    AppState.medicos.forEach(m => {
        selectMed.innerHTML += `<option value="${m.id}">${m.nombre} (${m.especialidad})</option>`;
    });

    document.getElementById('agendar_precio_usd').value = '50.00';
    document.getElementById('agendar_tipo').value = 'Particular';

    const modal = new bootstrap.Modal(document.getElementById('modalAgendar'));
    modal.show();
}

function actualizarPrecioSegunTipo() {
    const tipo = document.getElementById('agendar_tipo').value;
    const precioInput = document.getElementById('agendar_precio_usd');
    if (tipo === 'Empleado' || tipo === 'Participante') {
        precioInput.value = '50.00';
    }
}

function configurarAutocompletePacientes() {
    const input = document.getElementById('agendar_buscar_paciente');
    const list = document.getElementById('autocomplete_pacientes');

    let debounceTimer;
    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (q.length < 2) {
            list.innerHTML = '';
            list.classList.add('d-none');
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const res = await fetch(`api/pacientes.php?q=${encodeURIComponent(q)}&limit=15`);
                const json = await res.json();
                const pacientesList = Array.isArray(json.data) ? json.data : (json.data?.pacientes || []);
                if (json.success && pacientesList.length > 0) {
                    list.innerHTML = '';
                    pacientesList.forEach(p => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.innerHTML = `<strong>${p.nombre}</strong> <span class="text-muted">(${p.cedula || 'Sin cédula'} · Tel: ${p.telefono || 'N/A'})</span> <span class="badge bg-light text-dark float-end">${p.tipo_beneficio}</span>`;
                        div.addEventListener('click', () => {
                            seleccionarPaciente(p);
                        });
                        list.appendChild(div);
                    });
                    list.classList.remove('d-none');
                } else {
                    list.innerHTML = '<div class="p-2 text-muted small">No se encontraron pacientes coincidentes. Puede registrarlo abajo.</div>';
                    list.classList.remove('d-none');
                }
            } catch (e) {
                console.error(e);
            }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !list.contains(e.target)) {
            list.classList.add('d-none');
        }
    });
}

function seleccionarPaciente(p) {
    document.getElementById('agendar_paciente_id').value = p.id;
    document.getElementById('agendar_buscar_paciente').value = p.nombre;
    document.getElementById('agendar_paciente_nombre').value = p.nombre;
    document.getElementById('agendar_paciente_cedula').value = p.cedula || '';
    document.getElementById('agendar_paciente_telefono').value = p.telefono || '';
    document.getElementById('agendar_paciente_representante').value = p.representante || '';
    if (p.tipo_beneficio && document.querySelector(`#agendar_tipo option[value="${p.tipo_beneficio}"]`)) {
        document.getElementById('agendar_tipo').value = p.tipo_beneficio;
    }
    document.getElementById('autocomplete_pacientes').classList.add('d-none');
}

function limpiarSeleccionPaciente() {
    document.getElementById('agendar_paciente_id').value = '';
    document.getElementById('agendar_buscar_paciente').value = '';
    document.getElementById('agendar_paciente_nombre').value = '';
    document.getElementById('agendar_paciente_cedula').value = '';
    document.getElementById('agendar_paciente_telefono').value = '';
    document.getElementById('agendar_paciente_representante').value = '';
    document.getElementById('autocomplete_pacientes').classList.add('d-none');
}

async function guardarCita(e) {
    e.preventDefault();
    const form = document.getElementById('formAgendar');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalAgendar')).hide();
            mostrarToast('Cita agendada exitosamente');
            cargarAgendaSegunVista();
        } else {
            mostrarToast(json.message || 'Error al agendar cita', 'danger');
        }
    } catch (err) {
        mostrarToast('Error de comunicación con el servidor', 'danger');
    }
}

/**
 * -------------------------------------------------------------
 * MODAL: DETALLE Y CAMBIO DE ESTADO
 * -------------------------------------------------------------
 */
async function abrirDetalleCita(citaId) {
    if (!citaId) return;

    // 1. Buscar en AppState.citasActuales
    let cita = AppState.citasActuales ? AppState.citasActuales.find(c => c.id == citaId) : null;

    // 2. Si no se encuentra, buscar en AppState.citasPorDia
    if (!cita && AppState.citasPorDia) {
        for (const dia in AppState.citasPorDia) {
            const encontrada = AppState.citasPorDia[dia].find(c => c.id == citaId);
            if (encontrada) {
                cita = encontrada;
                break;
            }
        }
    }

    // 3. Si no se encuentra, buscar en AppState.citasMap
    if (!cita && AppState.citasMap) {
        for (const k in AppState.citasMap) {
            const val = AppState.citasMap[k];
            if (Array.isArray(val)) {
                const encontrada = val.find(c => c.id == citaId);
                if (encontrada) {
                    cita = encontrada;
                    break;
                }
            } else if (val && val.id == citaId) {
                cita = val;
                break;
            }
        }
    }

    // 4. Si aún no está en memoria, consultar endpoint directamente
    if (!cita) {
        try {
            const res = await fetch(`api/citas.php?id=${citaId}`);
            const json = await res.json();
            if (json.success && json.data) {
                cita = json.data;
            }
        } catch (e) {
            console.error('Error al obtener cita remota:', e);
        }
    }

    if (!cita) {
        mostrarToast('No se encontró la información de la cita', 'warning');
        return;
    }

    AppState.citaSeleccionada = cita;
    document.getElementById('estado_cita_id').value = cita.id;

    document.getElementById('det_paciente_nombre').innerText = cita.paciente_nombre;
    document.getElementById('det_paciente_cedula_tel').innerText = 
        `C.I: ${cita.paciente_cedula || 'S/C'} · Teléfono: ${cita.paciente_telefono || 'No registrado'}`;
    
    document.getElementById('det_hora').innerText = formatearHora12(cita.hora);
    document.getElementById('det_consultorio').innerText = cita.consultorio_nombre;
    document.getElementById('det_medico').innerText = cita.medico_nombre;
    document.getElementById('det_tipo').innerText = cita.tipo;
    document.getElementById('det_precio').innerText = `$${parseFloat(cita.precio_usd).toFixed(2)}`;

    const estadoInfo = AppState.estados[cita.estado] || { label: cita.estado, badge: 'primary', emoji: '' };
    const badgeEl = document.getElementById('det_estado_badge');
    badgeEl.className = `badge bg-${estadoInfo.badge}`;
    badgeEl.innerText = `${estadoInfo.emoji} ${estadoInfo.label}`;

    // Representante
    const repBox = document.getElementById('det_representante_box');
    if (cita.paciente_representante) {
        document.getElementById('det_representante').innerText = cita.paciente_representante;
        repBox.classList.remove('d-none');
    } else {
        repBox.classList.add('d-none');
    }

    // Pagos
    const pagosBox = document.getElementById('det_pagos_box');
    if (cita.pagos && cita.pagos.length > 0) {
        document.getElementById('det_factura').innerText = cita.factura_o_empleado || 'Registrado';
        const lista = document.getElementById('det_pagos_lista');
        lista.innerHTML = '';
        cita.pagos.forEach(p => {
            lista.innerHTML += `<div class="text-truncate">· ${p.forma_pago}: $${parseFloat(p.monto_usd).toFixed(2)} (Bs. ${formatearNumero(p.monto_bs, 2)}) ${p.referencia ? `Ref: ${p.referencia}` : ''}</div>`;
        });
        pagosBox.classList.remove('d-none');
    } else {
        pagosBox.classList.add('d-none');
    }

    const modal = new bootstrap.Modal(document.getElementById('modalEstado'));
    modal.show();
}

async function ejecutarCambioEstado(nuevoEstado) {
    if (!AppState.citaSeleccionada) return;
    const citaId = AppState.citaSeleccionada.id;

    try {
        const res = await fetch('api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cambiar_estado', cita_id: citaId, estado: nuevoEstado })
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalEstado')).hide();
            mostrarToast(`Estado cambiado a: ${nuevoEstado}`);
            cargarAgendaSegunVista();
        } else {
            mostrarToast(json.message || 'Error al cambiar estado', 'danger');
        }
    } catch (e) {
        mostrarToast('Error de comunicación', 'danger');
    }
}

async function eliminarCitaActual() {
    if (!AppState.citaSeleccionada) return;
    if (!confirm(`¿Está seguro de cancelar la cita de ${AppState.citaSeleccionada.paciente_nombre}?`)) return;

    try {
        const res = await fetch('api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancelar', cita_id: AppState.citaSeleccionada.id })
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalEstado')).hide();
            mostrarToast('Cita cancelada y eliminada');
            cargarAgendaSegunVista();
        }
    } catch (e) {
        mostrarToast('Error al eliminar', 'danger');
    }
}

/**
 * -------------------------------------------------------------
 * MODAL: COBRO Y FACTURACIÓN
 * -------------------------------------------------------------
 */
function abrirCobroDesdeDetalle() {
    bootstrap.Modal.getInstance(document.getElementById('modalEstado')).hide();
    const cita = AppState.citaSeleccionada;
    if (!cita) return;

    document.getElementById('cobrar_cita_id').value = cita.id;
    document.getElementById('cobrar_paciente_nombre').innerText = cita.paciente_nombre;
    document.getElementById('cobrar_info_adicional').innerText = 
        `${cita.medico_nombre} · ${cita.consultorio_nombre} · ${formatearHora12(cita.hora)} · Tipo: ${cita.tipo}`;

    // Llenar formas de pago
    const sForma1 = document.getElementById('cobrar_forma_1');
    const sForma2 = document.getElementById('cobrar_forma_2');
    sForma1.innerHTML = '';
    sForma2.innerHTML = '<option value="">-- Seleccionar método --</option>';

    Object.keys(AppState.formasPago).forEach(k => {
        sForma1.innerHTML += `<option value="${k}">${AppState.formasPago[k]}</option>`;
        sForma2.innerHTML += `<option value="${k}">${AppState.formasPago[k]}</option>`;
    });

    // Configurar montos
    let precio = parseFloat(cita.precio_usd);
    if (cita.tipo === 'Familiar') precio = precio * 0.5;
    if (cita.tipo === 'Empleado' || cita.tipo === 'Participante') precio = 0.0;

    document.getElementById('cobrar_precio_display').innerText = `$${precio.toFixed(2)}`;
    document.getElementById('cobrar_tasa_bcv').value = AppState.tasaBcv.toFixed(2);
    document.getElementById('cobrar_monto_1').value = precio.toFixed(2);
    document.getElementById('cobrar_monto_2').value = '0.00';
    document.getElementById('cobrar_factura_o_empleado').value = cita.factura_o_empleado || '';

    // Pago mixto off
    document.getElementById('checkPagoMixto').checked = false;
    document.getElementById('container_pago_2').classList.add('d-none');

    recalcularConversionCobro();

    const modal = new bootstrap.Modal(document.getElementById('modalCobrar'));
    modal.show();
}

function togglePagoMixto() {
    const check = document.getElementById('checkPagoMixto').checked;
    const box2 = document.getElementById('container_pago_2');
    if (check) {
        box2.classList.remove('d-none');
    } else {
        box2.classList.add('d-none');
        document.getElementById('cobrar_monto_2').value = '0.00';
        recalcularConversionCobro();
    }
}

function recalcularConversionCobro() {
    const tasa = parseFloat(document.getElementById('cobrar_tasa_bcv').value) || AppState.tasaBcv;
    const m1 = parseFloat(document.getElementById('cobrar_monto_1').value) || 0.0;
    const m2 = parseFloat(document.getElementById('cobrar_monto_2').value) || 0.0;

    const bs1 = m1 * tasa;
    const bs2 = m2 * tasa;

    document.getElementById('cobrar_monto_bs_1').value = `Bs. ${formatearNumero(bs1, 2)}`;
    document.getElementById('cobrar_monto_bs_2').value = `Bs. ${formatearNumero(bs2, 2)}`;
    document.getElementById('badge_total_f1').innerText = `$${m1.toFixed(2)}`;
    document.getElementById('badge_total_f2').innerText = `$${m2.toFixed(2)}`;

    const totalCobradoBs = (m1 + m2) * tasa;
    document.getElementById('cobrar_precio_bs_display').innerText = `Total: Bs. ${formatearNumero(totalCobradoBs, 2)}`;
}

async function guardarCobro(e) {
    e.preventDefault();
    const form = document.getElementById('formCobrar');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/pagos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalCobrar')).hide();
            mostrarToast('Cobro registrado y cita actualizada exitosamente');
            cargarAgendaSegunVista();
        } else {
            mostrarToast(json.message || 'Error al procesar cobro', 'danger');
        }
    } catch (err) {
        mostrarToast('Error de comunicación con el servidor', 'danger');
    }
}

/**
 * -------------------------------------------------------------
 * LIQUIDACIÓN DE HONORARIOS MÉDICOS (Cálculo AE-AL)
 * -------------------------------------------------------------
 */

/**
 * Convierte un formato 'YYYY-MM' al texto 'Mes de [Nombre del Mes] de [Año]'
 */
function obtenerTextoMesEs(mesStr) {
    if (!mesStr) return '';
    const partes = mesStr.split('-');
    const ano = partes[0];
    const mesNum = partes[1];
    const meses = {
        '01': 'Enero', '02': 'Febrero', '03': 'Marzo', '04': 'Abril',
        '05': 'Mayo', '06': 'Junio', '07': 'Julio', '08': 'Agosto',
        '09': 'Septiembre', '10': 'Octubre', '11': 'Noviembre', '12': 'Diciembre'
    };
    return `Mes de ${meses[mesNum] || mesNum} de ${ano}`;
}

/**
 * Genera y abre el archivo PDF oficial de liquidación médica en orientación horizontal
 */
function exportarLiquidacionPDF() {
    const mes = document.getElementById('liq_mes_selector').value || AppState.mesActual;
    window.open(`api/liquidacion_pdf.php?mes=${mes}`, '_blank');
}

async function cargarLiquidacion() {
    const mes = document.getElementById('liq_mes_selector').value;
    const tbody = document.getElementById('tbody_liquidacion');
    const tfoot = document.getElementById('tfoot_liquidacion');
    tbody.innerHTML = '<tr><td colspan="9" class="py-4 text-muted">Calculando liquidación médica...</td></tr>';

    // Actualizar encabezado para impresión nativa web
    const printTitulo = document.getElementById('print_liq_titulo');
    if (printTitulo) {
        printTitulo.innerText = obtenerTextoMesEs(mes);
    }
    const printFecha = document.getElementById('print_liq_fecha');
    if (printFecha) {
        const ahora = new Date();
        printFecha.innerText = `Emitido: ${ahora.toLocaleDateString('es-VE')} ${ahora.toLocaleTimeString('es-VE', {hour: '2-digit', minute:'2-digit'})}`;
    }

    try {
        const res = await fetch(`api/liquidacion.php?mes=${mes}`);
        const json = await res.json();

        if (json.success) {
            const data = json.data;
            if (data.liquidaciones.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="py-4 text-muted">No hay registros de citas en este mes.</td></tr>';
                tfoot.innerHTML = '';
                return;
            }

            let html = '';
            data.liquidaciones.forEach(l => {
                html += `
                    <tr>
                        <td class="text-start ps-3 fw-bold text-primary">${l.medico}</td>
                        <td class="small text-muted">${l.especialidad || 'General'}</td>
                        <td><span class="badge bg-light text-dark border">${l.cantidad_citas}</span></td>
                        <td><span class="badge bg-success text-white">${l.citas_pagadas}</span></td>
                        <td>$${parseFloat(l.total_bruto).toFixed(2)}</td>
                        <td class="fw-bold">$${parseFloat(l.monto_cobrado).toFixed(2)}</td>
                        <td class="col-monto-adh fw-bold">$${parseFloat(l.monto_adh).toFixed(2)}</td>
                        <td class="bg-success bg-opacity-10 fw-bold text-success">$${parseFloat(l.monto_medico).toFixed(2)}</td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-outline-primary py-0" onclick="verDetalleMedico(${l.medico_id}, '${l.medico}')">
                                <i class="bi bi-eye"></i> Ver
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;

            // Fila de Totales
            const tot = data.totales;
            tfoot.innerHTML = `
                <tr class="table-dark">
                    <td colspan="2" class="text-start ps-3">TOTALES DEL MES:</td>
                    <td>${tot.cantidad_citas}</td>
                    <td>${tot.citas_pagadas}</td>
                    <td>$${parseFloat(tot.total_bruto).toFixed(2)}</td>
                    <td>$${parseFloat(tot.monto_cobrado).toFixed(2)}</td>
                    <td class="col-monto-adh fw-bold">$${parseFloat(tot.monto_adh).toFixed(2)}</td>
                    <td class="bg-success text-white">$${parseFloat(tot.monto_medico).toFixed(2)}</td>
                    <td class="no-print"></td>
                </tr>
            `;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="py-4 text-danger">Error al cargar liquidación: ${e.message}</td></tr>`;
    }
}

async function verDetalleMedico(medicoId, nombreMedico) {
    const mes = document.getElementById('liq_mes_selector').value;
    const card = document.getElementById('card_detalle_medico');
    const tbody = document.getElementById('tbody_detalle_citas');
    document.getElementById('detalle_nombre_medico').innerText = nombreMedico;
    card.classList.remove('d-none');
    tbody.innerHTML = '<tr><td colspan="9" class="py-3 text-muted">Cargando citas...</td></tr>';

    try {
        const res = await fetch(`api/liquidacion.php?mes=${mes}&medico_id=${medicoId}`);
        const json = await res.json();
        if (json.success) {
            const citas = json.data.citas_detalle;
            if (citas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="py-3 text-muted">El médico no tiene citas registradas en este período.</td></tr>';
                return;
            }
            let html = '';
            citas.forEach(c => {
                html += `
                    <tr>
                        <td>${c.fecha}</td>
                        <td>${formatearHora12(c.hora)}</td>
                        <td>${c.consultorio_nombre}</td>
                        <td class="text-start ps-2 fw-bold">${c.paciente_nombre}</td>
                        <td>${c.paciente_cedula || '-'}</td>
                        <td><span class="badge bg-light text-dark border">${c.tipo}</span></td>
                        <td><span class="badge bg-secondary">${c.estado}</span></td>
                        <td class="fw-bold">$${parseFloat(c.precio_usd).toFixed(2)}</td>
                        <td>${c.factura_o_empleado || '-'}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-danger py-3">Error: ${e.message}</td></tr>`;
    }
}

function cerrarDetalleMedico() {
    document.getElementById('card_detalle_medico').classList.add('d-none');
}

/**
 * -------------------------------------------------------------
 * CONTROL DE BENEFICIOS (Columnas BA - BN)
 * -------------------------------------------------------------
 */
async function cargarBeneficios() {
    const mes = document.getElementById('ben_mes_selector').value;

    try {
        const res = await fetch(`api/beneficios.php?mes=${mes}`);
        const json = await res.json();

        if (json.success) {
            const data = json.data;

            // 1. Empleados
            document.getElementById('count_emp').innerText = data.empleados.length;
            const tbEmp = document.getElementById('tbody_beneficio_empleados');
            if (data.empleados.length === 0) {
                tbEmp.innerHTML = '<tr><td colspan="7" class="py-4 text-muted">No se registraron consultas de empleados este mes.</td></tr>';
            } else {
                let html = '';
                data.empleados.forEach(c => {
                    html += `
                        <tr>
                            <td>${c.fecha}</td>
                            <td class="text-start ps-3 fw-bold text-primary">${c.paciente_nombre}</td>
                            <td>${c.cedula || '-'}</td>
                            <td>${c.telefono || '-'}</td>
                            <td>${c.medico_nombre}</td>
                            <td class="fw-bold">$${parseFloat(c.precio_usd).toFixed(2)}</td>
                            <td><span class="badge bg-success">${c.estado}</span></td>
                        </tr>
                    `;
                });
                tbEmp.innerHTML = html;
            }

            // 2. Familiares
            document.getElementById('count_fam').innerText = data.familiares.length;
            const tbFam = document.getElementById('tbody_beneficio_familiares');
            if (data.familiares.length === 0) {
                tbFam.innerHTML = '<tr><td colspan="8" class="py-4 text-muted">No se registraron consultas de familiares directos este mes.</td></tr>';
            } else {
                let html = '';
                data.familiares.forEach(c => {
                    html += `
                        <tr>
                            <td>${c.fecha}</td>
                            <td class="text-start ps-3 fw-bold text-primary">${c.paciente_nombre}</td>
                            <td>${c.representante || c.factura_o_empleado || '-'}</td>
                            <td>${c.cedula || '-'}</td>
                            <td>${c.telefono || '-'}</td>
                            <td>${c.medico_nombre}</td>
                            <td class="fw-bold">$${parseFloat(c.precio_usd).toFixed(2)}</td>
                            <td><span class="badge bg-success">${c.estado}</span></td>
                        </tr>
                    `;
                });
                tbFam.innerHTML = html;
            }

            // 3. Participantes
            document.getElementById('count_part').innerText = data.participantes.length;
            const tbPart = document.getElementById('tbody_beneficio_participantes');
            if (data.participantes.length === 0) {
                tbPart.innerHTML = '<tr><td colspan="7" class="py-4 text-muted">No se registraron consultas de participantes este mes.</td></tr>';
            } else {
                let html = '';
                data.participantes.forEach(c => {
                    html += `
                        <tr>
                            <td>${c.fecha}</td>
                            <td class="text-start ps-3 fw-bold text-primary">${c.paciente_nombre}</td>
                            <td>${c.cedula || '-'}</td>
                            <td>${c.telefono || '-'}</td>
                            <td>${c.medico_nombre}</td>
                            <td class="fw-bold">$${parseFloat(c.precio_usd).toFixed(2)}</td>
                            <td><span class="badge bg-success">${c.estado}</span></td>
                        </tr>
                    `;
                });
                tbPart.innerHTML = html;
            }

            // 4. Exonerados
            document.getElementById('count_exon').innerText = data.exonerados.length;
            const tbExon = document.getElementById('tbody_beneficio_exonerados');
            if (data.exonerados.length === 0) {
                tbExon.innerHTML = '<tr><td colspan="7" class="py-4 text-muted">No se registraron consultas exoneradas este mes.</td></tr>';
            } else {
                let html = '';
                data.exonerados.forEach(c => {
                    html += `
                        <tr>
                            <td>${c.fecha}</td>
                            <td class="text-start ps-3 fw-bold text-primary">${c.paciente_nombre}</td>
                            <td><span class="badge bg-warning text-dark">${c.tipo}</span></td>
                            <td>${c.cedula || '-'}</td>
                            <td>${c.medico_nombre}</td>
                            <td class="fw-bold">$${parseFloat(c.precio_usd).toFixed(2)}</td>
                            <td><span class="badge bg-success">${c.estado}</span></td>
                        </tr>
                    `;
                });
                tbExon.innerHTML = html;
            }
        }
    } catch (e) {
        console.error('Error cargando beneficios:', e);
    }
}

/**
 * -------------------------------------------------------------
 * DIRECTORIO DE PACIENTES
 * -------------------------------------------------------------
 */
let debounceBuscarDirectorio;
function buscarPacientesDirectorio(q) {
    clearTimeout(debounceBuscarDirectorio);
    debounceBuscarDirectorio = setTimeout(() => {
        AppState.pacientesSearch = (q || '').trim();
        AppState.pacientesPage = 1;
        cargarDirectorioPacientes();
    }, 300);
}

function cambiarPaginaPacientes(numPagina) {
    if (numPagina < 1 || (AppState.pacientesTotalPages && numPagina > AppState.pacientesTotalPages)) return;
    AppState.pacientesPage = numPagina;
    cargarDirectorioPacientes();
}

function cambiarLimitePacientes(nuevoLimite) {
    AppState.pacientesLimit = nuevoLimite;
    AppState.pacientesPage = 1;
    cargarDirectorioPacientes();
}

async function cargarDirectorioPacientes() {
    const tbody = document.getElementById('tbody_pacientes_directorio');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-muted"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Cargando pacientes...</td></tr>';

    try {
        const busqueda = AppState.pacientesSearch || '';
        const page = AppState.pacientesPage || 1;
        const limit = AppState.pacientesLimit || 50;

        const params = new URLSearchParams();
        if (busqueda !== '') params.append('q', busqueda);
        params.append('page', page);
        params.append('limit', limit);

        const res = await fetch(`api/pacientes.php?${params.toString()}`);
        const json = await res.json();

        if (json.success && json.data) {
            const list = Array.isArray(json.data) ? json.data : (json.data.pacientes || []);
            const total = json.data.total ?? list.length;
            const totalFiltrados = json.data.total_filtrados ?? list.length;
            const currentPage = json.data.page ?? page;
            const totalPages = json.data.pages ?? 1;

            AppState.pacientesTotal = total;
            AppState.pacientesTotalFiltrados = totalFiltrados;
            AppState.pacientesPage = currentPage;
            AppState.pacientesTotalPages = totalPages;

            // Actualizar badge del encabezado
            const badgeTotal = document.getElementById('badge_total_pacientes');
            if (badgeTotal) {
                badgeTotal.innerText = `${total} Registrados`;
            }

            // Renderizar paginación e información
            renderPaginacionPacientes(total, totalFiltrados, currentPage, totalPages, limit);

            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-muted">No se encontraron pacientes coincidentes.</td></tr>';
                return;
            }

            let html = '';
            list.forEach(p => {
                const tipoBadgeClass = p.tipo_beneficio === 'Empleado' ? 'bg-info text-dark' :
                                       p.tipo_beneficio === 'Participante' ? 'bg-success text-white' :
                                       p.tipo_beneficio === 'Familiar' ? 'bg-warning text-dark' : 'bg-light text-dark border';
                html += `
                    <tr>
                        <td class="ps-3 fw-bold text-primary">
                            <i class="bi bi-person-fill text-secondary me-1"></i>${p.nombre}
                        </td>
                        <td>${p.cedula ? `<span class="badge bg-light text-dark border">${p.cedula}</span>` : '<span class="text-muted">S/C</span>'}</td>
                        <td>${p.telefono ? `<i class="bi bi-telephone text-muted me-1"></i>${p.telefono}` : '<span class="text-muted">-</span>'}</td>
                        <td class="small">${p.representante || '<span class="text-muted">-</span>'}</td>
                        <td><span class="badge ${tipoBadgeClass}">${p.tipo_beneficio || 'Particular'}</span></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary py-0" onclick="verHistorialPaciente(${p.id})">
                                <i class="bi bi-clock-history"></i> Historial
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="text-danger py-4 text-center">${json.message || 'Error al obtener pacientes'}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-danger py-4 text-center">Error: ${e.message}</td></tr>`;
    }
}

function renderPaginacionPacientes(totalGeneral, totalFiltrados, page, pages, limit) {
    const infoEl = document.getElementById('pacientes_info_registros');
    const ulEl = document.getElementById('pacientes_pagination_ul');
    if (!infoEl || !ulEl) return;

    // Calcular rango visible
    const esTodos = limit === 'all' || limit === 'todos';
    let desde = 0;
    let hasta = 0;
    if (totalFiltrados > 0) {
        if (esTodos) {
            desde = 1;
            hasta = totalFiltrados;
        } else {
            const limNum = parseInt(limit, 10) || 50;
            desde = (page - 1) * limNum + 1;
            hasta = Math.min(page * limNum, totalFiltrados);
        }
    }

    if (AppState.pacientesSearch) {
        infoEl.innerHTML = `Mostrando <strong>${desde}</strong> - <strong>${hasta}</strong> de <strong>${totalFiltrados}</strong> filtrados (de <strong>${totalGeneral}</strong> en total)`;
    } else {
        infoEl.innerHTML = `Mostrando <strong>${desde}</strong> - <strong>${hasta}</strong> de <strong>${totalGeneral}</strong> pacientes`;
    }

    if (esTodos || pages <= 1) {
        ulEl.innerHTML = '';
        return;
    }

    let pagHtml = '';

    // Botón Anterior
    pagHtml += `
        <li class="page-item ${page <= 1 ? 'disabled' : ''}">
            <button class="page-link" onclick="cambiarPaginaPacientes(${page - 1})" aria-label="Anterior">
                &laquo;
            </button>
        </li>
    `;

    // Ventana inteligente de números de página
    const delta = 2; // Cantidad de páginas adyacentes a mostrar
    const rango = [];
    for (let i = Math.max(2, page - delta); i <= Math.min(pages - 1, page + delta); i++) {
        rango.push(i);
    }

    // Siempre página 1
    pagHtml += `
        <li class="page-item ${page === 1 ? 'active' : ''}">
            <button class="page-link" onclick="cambiarPaginaPacientes(1)">1</button>
        </li>
    `;

    if (rango.length > 0 && rango[0] > 2) {
        pagHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }

    rango.forEach(pNum => {
        pagHtml += `
            <li class="page-item ${page === pNum ? 'active' : ''}">
                <button class="page-link" onclick="cambiarPaginaPacientes(${pNum})">${pNum}</button>
            </li>
        `;
    });

    if (rango.length > 0 && rango[rango.length - 1] < pages - 1) {
        pagHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }

    // Siempre última página si pages > 1
    if (pages > 1) {
        pagHtml += `
            <li class="page-item ${page === pages ? 'active' : ''}">
                <button class="page-link" onclick="cambiarPaginaPacientes(${pages})">${pages}</button>
            </li>
        `;
    }

    // Botón Siguiente
    pagHtml += `
        <li class="page-item ${page >= pages ? 'disabled' : ''}">
            <button class="page-link" onclick="cambiarPaginaPacientes(${page + 1})" aria-label="Siguiente">
                &raquo;
            </button>
        </li>
    `;

    ulEl.innerHTML = pagHtml;
}

async function verHistorialPaciente(pacienteId) {
    try {
        const res = await fetch(`api/pacientes.php?id=${pacienteId}`);
        const json = await res.json();
        if (json.success && json.data) {
            const p = json.data;
            document.getElementById('hist_paciente_nombre').innerText = p.nombre;
            document.getElementById('hist_paciente_cedula').innerText = p.cedula || 'Sin cédula';
            document.getElementById('hist_paciente_telefono').innerText = p.telefono || 'Sin teléfono';
            document.getElementById('hist_paciente_tipo').innerText = p.tipo_beneficio || 'Particular';
            document.getElementById('hist_paciente_repres').innerText = p.representante || 'Ninguno';

            const tbody = document.getElementById('tbody_historial_citas');
            if (!p.citas || p.citas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-3 text-muted">El paciente no tiene consultas previas registradas.</td></tr>';
            } else {
                let html = '';
                p.citas.forEach(c => {
                    html += `
                        <tr>
                            <td>${c.fecha}</td>
                            <td>${formatearHora12(c.hora)}</td>
                            <td>${c.consultorio_nombre}</td>
                            <td>${c.medico_nombre}</td>
                            <td><span class="badge bg-secondary">${c.estado}</span></td>
                            <td class="fw-bold">$${parseFloat(c.precio_usd).toFixed(2)}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            }

            const modal = new bootstrap.Modal(document.getElementById('modalHistorialPaciente'));
            modal.show();
        }
    } catch (e) {
        mostrarToast('Error al cargar historial', 'danger');
    }
}

function abrirModalNuevoPaciente() {
    document.getElementById('formPacienteDirectorio').reset();
    document.getElementById('pac_form_id').value = '';
    const modal = new bootstrap.Modal(document.getElementById('modalPacienteForm'));
    modal.show();
}

async function guardarPacienteDirectorio(e) {
    e.preventDefault();
    const form = document.getElementById('formPacienteDirectorio');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/pacientes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalPacienteForm')).hide();
            mostrarToast('Paciente registrado exitosamente');
            cargarDirectorioPacientes();
        } else {
            mostrarToast(json.message || 'Error al guardar paciente', 'danger');
        }
    } catch (err) {
        mostrarToast('Error de comunicación', 'danger');
    }
}

/**
 * -------------------------------------------------------------
 * GESTIÓN Y CATÁLOGO DE MÉDICOS Y ESPECIALISTAS
 * -------------------------------------------------------------
 */
let debounceBuscarMedicos = null;

async function cargarCatalogoMedicos() {
    const tbody = document.getElementById('tbody_medicos_catalogo');
    if (!tbody) return;

    const searchInput = document.getElementById('medicos_search_input');
    const filtroEstado = document.getElementById('medicos_filtro_estado');

    const search = searchInput ? searchInput.value.trim() : '';
    const activo = filtroEstado ? filtroEstado.value : 'all';

    tbody.innerHTML = '<tr><td colspan="7" class="py-4 text-muted">Cargando catálogo de médicos...</td></tr>';

    try {
        const queryParams = new URLSearchParams();
        if (search) queryParams.append('q', search);
        if (activo) queryParams.append('activo', activo);

        const res = await fetch(`api/medicos.php?${queryParams.toString()}`);
        const json = await res.json();

        if (json.success) {
            const data = json.data;
            AppState.catalogoMedicos = data.medicos;

            if (data.medicos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="py-4 text-muted">No se encontraron médicos con los filtros aplicados.</td></tr>';
                return;
            }

            let html = '';
            data.medicos.forEach(m => {
                const esActivo = parseInt(m.activo) === 1;
                const estadoBadge = esActivo
                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Activo</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-dash-circle me-1"></i>Inactivo</span>';

                const toggleBtn = esActivo
                    ? `<button class="btn btn-sm btn-outline-warning py-0 px-2" onclick="toggleActivoMedico(${m.id}, 0)" title="Desactivar médico de la agenda">
                         <i class="bi bi-pause-circle me-1"></i>Desactivar
                       </button>`
                    : `<button class="btn btn-sm btn-outline-success py-0 px-2" onclick="toggleActivoMedico(${m.id}, 1)" title="Activar médico en la agenda">
                         <i class="bi bi-play-circle me-1"></i>Activar
                       </button>`;

                const totalCitas = parseInt(m.total_citas) || 0;
                const citasBadge = totalCitas > 0
                    ? `<span class="badge bg-light text-primary border fw-semibold">${totalCitas} citas</span>`
                    : '<span class="text-muted small">Sin citas</span>';

                const pctHonorario = parseFloat(m.porcentaje_honorario) || 50.0;
                const pctAdh = Math.max(0, 100.0 - pctHonorario);
                const nombreLower = (m.nombre || '').toLowerCase();
                const espLower = (m.especialidad || '').toLowerCase();
                const esLab = nombreLower.includes('lab') || espLower.includes('lab');
                const etiquetaRol = esLab ? 'Lab.' : 'Méd.';

                html += `
                    <tr class="${esActivo ? '' : 'opacity-75 bg-light'}">
                        <td class="text-start ps-4 fw-bold ${esActivo ? 'text-primary' : 'text-muted'}">
                            <i class="bi bi-person-fill me-1 text-muted"></i> ${m.nombre}
                        </td>
                        <td>
                            <span class="badge-especialidad">${m.especialidad || 'General'}</span>
                        </td>
                        <td class="small text-muted">
                            ${m.telefono ? `<i class="bi bi-telephone text-muted me-1"></i>${m.telefono}` : '<span class="text-muted fst-italic">No registrado</span>'}
                        </td>
                        <td>
                            <span class="fw-bold">${pctHonorario.toFixed(0)}%</span>
                            <small class="text-muted d-block" style="font-size: 0.72rem;">(${pctAdh.toFixed(0)}% ADH / ${pctHonorario.toFixed(0)}% ${etiquetaRol})</small>
                        </td>
                        <td>${citasBadge}</td>
                        <td>${estadoBadge}</td>
                        <td>
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="abrirModalEditarMedico(${m.id})" title="Editar especialidad o datos">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                ${toggleBtn}
                                <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="eliminarMedico(${m.id}, '${m.nombre.replace(/'/g, "\\'")}', ${totalCitas})" title="Eliminar o retirar del catálogo">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            // Mantener sincronizados los médicos activos en AppState y selects de la agenda
            actualizarSelectsMedicos(data.medicos.filter(m => parseInt(m.activo) === 1));
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="py-4 text-danger">${json.message || 'Error al cargar catálogo'}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-4 text-danger">Error de comunicación: ${e.message}</td></tr>`;
    }
}

function filtrarMedicosCatalogo(val) {
    clearTimeout(debounceBuscarMedicos);
    debounceBuscarMedicos = setTimeout(() => {
        cargarCatalogoMedicos();
    }, 250);
}

function abrirModalNuevoMedico() {
    document.getElementById('medico_form_id').value = '0';
    document.getElementById('medico_form_nombre').value = '';
    document.getElementById('medico_form_especialidad').value = '';
    document.getElementById('medico_form_porcentaje').value = '50.00';
    document.getElementById('medico_form_telefono').value = '';
    
    document.getElementById('modalGuardarMedicoLabel').innerHTML = '<i class="bi bi-person-plus-fill me-1"></i> Registrar Doctor / Especialista';
    document.getElementById('btn_guardar_medico').innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar Doctor';

    const modal = new bootstrap.Modal(document.getElementById('modalGuardarMedico'));
    modal.show();
}

function abrirModalEditarMedico(id) {
    if (!AppState.catalogoMedicos) return;
    const medico = AppState.catalogoMedicos.find(m => m.id == id);
    if (!medico) return;

    document.getElementById('medico_form_id').value = medico.id;
    document.getElementById('medico_form_nombre').value = medico.nombre;
    document.getElementById('medico_form_especialidad').value = medico.especialidad || '';
    document.getElementById('medico_form_porcentaje').value = parseFloat(medico.porcentaje_honorario || 50).toFixed(2);
    document.getElementById('medico_form_telefono').value = medico.telefono || '';

    document.getElementById('modalGuardarMedicoLabel').innerHTML = '<i class="bi bi-pencil-square me-1"></i> Editar Doctor / Especialista';
    document.getElementById('btn_guardar_medico').innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Actualizar Doctor';

    const modal = new bootstrap.Modal(document.getElementById('modalGuardarMedico'));
    modal.show();
}

async function guardarMedico(event) {
    event.preventDefault();
    const id = parseInt(document.getElementById('medico_form_id').value) || 0;
    const nombre = document.getElementById('medico_form_nombre').value.trim();
    const especialidad = document.getElementById('medico_form_especialidad').value.trim();
    const porcentaje = parseFloat(document.getElementById('medico_form_porcentaje').value) || 50.00;
    const telefono = document.getElementById('medico_form_telefono').value.trim();

    if (!nombre) {
        mostrarToast('El nombre del médico es obligatorio', 'warning');
        return;
    }

    const payload = {
        id: id,
        nombre: nombre,
        especialidad: especialidad || 'Medicina General',
        porcentaje_honorario: porcentaje,
        telefono: telefono
    };

    try {
        const btn = document.getElementById('btn_guardar_medico');
        const btnHtmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        const res = await fetch('api/medicos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        btn.disabled = false;
        btn.innerHTML = btnHtmlOriginal;

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalGuardarMedico')).hide();
            mostrarToast(json.message || 'Doctor guardado exitosamente');
            cargarCatalogoMedicos();
        } else {
            mostrarToast(json.message || 'Error al guardar médico', 'danger');
        }
    } catch (err) {
        mostrarToast('Error de comunicación con el servidor', 'danger');
    }
}

async function toggleActivoMedico(id, nuevoEstado) {
    try {
        const res = await fetch('api/medicos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_activo', id: id, activo: nuevoEstado })
        });
        const json = await res.json();
        if (json.success) {
            mostrarToast(json.message);
            cargarCatalogoMedicos();
        } else {
            mostrarToast(json.message || 'Error al cambiar estado', 'danger');
        }
    } catch (err) {
        mostrarToast('Error de comunicación', 'danger');
    }
}

async function eliminarMedico(id, nombre, totalCitas) {
    let mensajeConfirm = '';
    if (totalCitas > 0) {
        mensajeConfirm = `El doctor "${nombre}" tiene ${totalCitas} citas asociadas en el historial.\n\nPara no alterar las liquidaciones y reportes pasados, se DESACTIVARÁ del catálogo (ya no aparecerá para agendar nuevas citas).\n\n¿Deseas continuar?`;
    } else {
        mensajeConfirm = `¿Estás seguro de que deseas eliminar permanentemente al doctor "${nombre}" del catálogo?`;
    }

    if (!confirm(mensajeConfirm)) return;

    try {
        const res = await fetch('api/medicos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'eliminar', id: id })
        });
        const json = await res.json();
        if (json.success) {
            mostrarToast(json.message);
            cargarCatalogoMedicos();
        } else {
            mostrarToast(json.message || 'Error al procesar la solicitud', 'danger');
        }
    } catch (err) {
        mostrarToast('Error de comunicación', 'danger');
    }
}

function actualizarSelectsMedicos(medicosActivos) {
    if (!Array.isArray(medicosActivos)) return;
    AppState.medicos = medicosActivos;

    // Actualizar select de agendar cita
    const selectMed = document.getElementById('agendar_medico_id');
    if (selectMed) {
        const valorActual = selectMed.value;
        selectMed.innerHTML = '';
        medicosActivos.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.text = `${m.nombre} (${m.especialidad})`;
            selectMed.appendChild(opt);
        });
        if (valorActual) selectMed.value = valorActual;
    }

    // Actualizar filtro de médicos de la agenda
    const selFiltro = document.getElementById('filtro_medico_agenda');
    if (selFiltro) {
        const valorActual = selFiltro.value;
        selFiltro.innerHTML = '<option value="">Todos los médicos</option>';
        medicosActivos.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.text = m.nombre;
            selFiltro.appendChild(opt);
        });
        if (valorActual) selFiltro.value = valorActual;
    }
}

/**
 * -------------------------------------------------------------
 * UTILIDADES
 * -------------------------------------------------------------
 */
function formatearNumero(num, decimales = 2) {
    return new Intl.NumberFormat('de-DE', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales
    }).format(num);
}

function mostrarToast(mensaje, tipo = 'success') {
    const toastEl = document.getElementById('liveToast');
    const toastMsg = document.getElementById('toastMessage');
    toastEl.className = `toast align-items-center text-white bg-${tipo} border-0 shadow-lg`;
    toastMsg.innerText = mensaje;
    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}
