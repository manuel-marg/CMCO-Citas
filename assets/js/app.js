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
    mostrarFinesSemana: localStorage.getItem('cmco_mostrar_fines_semana') === 'true', // Oculto por defecto (L-V)
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

    // Inicializar estado del botón de fines de semana
    actualizarBotonFinesSemana();

    // Configurar autocompletado de pacientes
    configurarAutocompletePacientes();

    // Soporte para navegación directa por hash de URL (ej: #medicos, #liquidacion)
    const initialHash = window.location.hash.replace('#', '');
    if (initialHash && ['agenda', 'liquidacion', 'beneficios', 'pacientes', 'medicos'].includes(initialHash)) {
        showSection(initialHash);
    }

    // Inicializar tooltips de Bootstrap
    inicializarTooltips();

    // Listener para guardar precio pendiente al cerrar el modal de detalle
    const modalEstadoEl = document.getElementById('modalEstado');
    if (modalEstadoEl) {
        modalEstadoEl.addEventListener('hide.bs.modal', () => {
            if (AppState.citaSeleccionada) {
                manejarCambioPrecioCita();
            }
        });
    }

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

    inicializarTooltips();
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

    // Alternar visibilidad del botón de fines de semana (solo visible en semana y mes)
    const btnFinde = document.getElementById('btn-toggle-finde');
    if (btnFinde) {
        if (nuevaVista === 'dia') {
            btnFinde.classList.add('d-none');
        } else {
            btnFinde.classList.remove('d-none');
        }
    }

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

function toggleFinesDeSemanaAgenda() {
    AppState.mostrarFinesSemana = !AppState.mostrarFinesSemana;
    localStorage.setItem('cmco_mostrar_fines_semana', AppState.mostrarFinesSemana ? 'true' : 'false');
    actualizarBotonFinesSemana();

    if (AppState.mostrarFinesSemana) {
        mostrarToast('Mostrando días Sábado y Domingo (Semana completa)', 'info', 'Fines de Semana Visibles');
    } else {
        mostrarToast('Ocultando Sábado y Domingo (Modo Lunes a Viernes)', 'info', 'Semana Laboral');
    }

    if (AppState.vistaActual === 'semana') {
        renderizarVistaSemana();
    } else if (AppState.vistaActual === 'mes') {
        renderizarVistaMes();
    }
}

function actualizarBotonFinesSemana() {
    const btn = document.getElementById('btn-toggle-finde');
    if (!btn) return;

    if (AppState.mostrarFinesSemana) {
        btn.classList.add('active', 'btn-primary');
        btn.classList.remove('btn-outline-secondary');
        btn.title = 'Fines de semana visibles (Clic para ocultar Sáb y Dom)';
        btn.setAttribute('aria-label', 'Fines de semana visibles');
    } else {
        btn.classList.remove('active', 'btn-primary');
        btn.classList.add('btn-outline-secondary');
        btn.title = 'Fines de semana ocultos (Clic para mostrar Sáb y Dom)';
        btn.setAttribute('aria-label', 'Fines de semana ocultos');
    }
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
 * Determina si una cita ocurrió antes del día actual y cuenta con pago registrado.
 * En tal caso, se le aplica un fondo verde claro distintivo a la tarjeta.
 */
function esCitaPasadaPagada(cita, fechaReferencia) {
    if (!cita) return false;
    const hoyStr = new Date().toISOString().slice(0, 10);
    const fechaCita = cita.fecha || fechaReferencia;
    if (!fechaCita || fechaCita >= hoyStr) {
        return false;
    }
    const estado = cita.estado || '';
    return estado === 'Pagado' || estado === 'Facturado' || (Array.isArray(cita.pagos) && cita.pagos.length > 0);
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
                    const pagadaPasada = esCitaPasadaPagada(cita, AppState.fechaActual);

                    html += `
                        <div class="cita-card mb-1 ${pagadaPasada ? 'cita-pagada-pasada' : ''}" style="border-left-color: ${estadoInfo.color}; cursor: pointer;" onclick="abrirDetalleCita(${cita.id})">
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

    // Si los fines de semana están ocultos, mostrar sólo Lunes a Viernes (5 días)
    const diasMostrar = AppState.mostrarFinesSemana ? dias : dias.slice(0, 5);

    let html = '';

    diasMostrar.forEach((fechaDia, idx) => {
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
                const pagadaPasada = esCitaPasadaPagada(c, fechaDia);

                html += `
                    <div class="week-cita-item ${pagadaPasada ? 'cita-pagada-pasada' : ''}" style="border-left-color: ${estadoInfo.color};" onclick="abrirDetalleCita(${c.id})">
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

    // Configurar columnas y filtrar fines de semana según preferencia
    const header = document.querySelector('.calendar-days-header');
    if (!AppState.mostrarFinesSemana) {
        // Filtrar sábados y domingos (índices 5 y 6 de cada bloque de 7 días)
        const celdasFiltradas = [];
        for (let i = 0; i < celdas.length; i++) {
            const posSemana = i % 7; // 0=Lun, 1=Mar, 2=Mie, 3=Jue, 4=Vie, 5=Sab, 6=Dom
            if (posSemana < 5) {
                celdasFiltradas.push(celdas[i]);
            }
        }
        celdas = celdasFiltradas;

        if (header) {
            header.innerHTML = `
                <div>Lunes</div>
                <div>Martes</div>
                <div>Miércoles</div>
                <div>Jueves</div>
                <div>Viernes</div>
            `;
            header.style.gridTemplateColumns = 'repeat(5, minmax(0, 1fr))';
        }
        if (container) {
            container.style.gridTemplateColumns = 'repeat(5, minmax(0, 1fr))';
        }
    } else {
        if (header) {
            header.innerHTML = `
                <div>Lunes</div>
                <div>Martes</div>
                <div>Miércoles</div>
                <div>Jueves</div>
                <div>Viernes</div>
                <div class="text-secondary opacity-75">Sábado</div>
                <div class="text-secondary opacity-75">Domingo</div>
            `;
            header.style.gridTemplateColumns = 'repeat(7, minmax(0, 1fr))';
        }
        if (container) {
            container.style.gridTemplateColumns = 'repeat(7, minmax(0, 1fr))';
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
            const pagadaPasada = esCitaPasadaPagada(cita, c.fecha);
            html += `
                <div class="cal-cita-chip ${pagadaPasada ? 'cita-pagada-pasada' : ''}" 
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
            if (rateEl) rateEl.innerText = formatearNumero(AppState.tasaBcv, 2);
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
            if (navRate) navRate.innerText = formatearNumero(tasa, 2);
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
    const numConsultorio = cita.consultorio_numero || (cita.consultorio_nombre ? cita.consultorio_nombre.replace(/\D/g, '') : '') || cita.consultorio_id || '1';
    document.getElementById('det_consultorio').innerText = numConsultorio;

    // Poblar y seleccionar Médico
    const selectMed = document.getElementById('det_medico_id');
    if (selectMed) {
        selectMed.innerHTML = '';
        if (Array.isArray(AppState.medicos)) {
            AppState.medicos.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.text = `${m.nombre} (${m.especialidad})`;
                if (m.id == cita.medico_id) opt.selected = true;
                selectMed.appendChild(opt);
            });
            // Si el médico asignado a la cita no está en la lista de activos, agregarlo como primera opción
            if (cita.medico_id && !AppState.medicos.some(m => m.id == cita.medico_id)) {
                const opt = document.createElement('option');
                opt.value = cita.medico_id;
                opt.text = `${cita.medico_nombre} (${cita.medico_especialidad || 'Especialista'})`;
                opt.selected = true;
                selectMed.insertBefore(opt, selectMed.firstChild);
            }
        }
        selectMed.value = cita.medico_id;
    }

    // Tipo de cita
    const selectTipo = document.getElementById('det_tipo');
    if (selectTipo) {
        selectTipo.value = cita.tipo || 'Particular';
    }

    // Precio
    const inputPrecio = document.getElementById('det_precio');
    if (inputPrecio) {
        inputPrecio.value = parseFloat(cita.precio_usd || 50.00).toFixed(2);
    }

    // Estado de la cita
    const selectEstado = document.getElementById('det_estado');
    if (selectEstado) {
        selectEstado.value = cita.estado || 'Agendada';
    }

    // Ocultar indicador de guardado si estaba visible
    const ind = document.getElementById('det_auto_save_indicator');
    if (ind) ind.classList.add('d-none');

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

/**
 * Controladores de autoguardado para Médico, Tipo y Precio en Detalle de Cita
 */
let timerDetalleAutoSave = null;

function manejarCambioMedicoCita() {
    if (!AppState.citaSeleccionada) return;
    const selectMed = document.getElementById('det_medico_id');
    if (!selectMed) return;
    const nuevoMedId = parseInt(selectMed.value);
    if (nuevoMedId && nuevoMedId != AppState.citaSeleccionada.medico_id) {
        autoGuardarDetalleCita('medico');
    }
}

function manejarCambioTipoCita() {
    if (!AppState.citaSeleccionada) return;
    const selectTipo = document.getElementById('det_tipo');
    if (!selectTipo) return;
    const nuevoTipo = selectTipo.value;
    if (nuevoTipo && nuevoTipo !== AppState.citaSeleccionada.tipo) {
        autoGuardarDetalleCita('tipo');
    }
}

let timerPrecioDebounce = null;
function manejarCambioPrecioCita() {
    if (!AppState.citaSeleccionada) return;
    const inputPrecio = document.getElementById('det_precio');
    if (!inputPrecio) return;
    const rawVal = inputPrecio.value;
    const nuevoPrecio = parseFloat(rawVal);
    const precioActual = parseFloat(AppState.citaSeleccionada.precio_usd);
    if (!isNaN(nuevoPrecio) && nuevoPrecio >= 0 && Math.abs(nuevoPrecio - precioActual) > 0.001) {
        if (timerPrecioDebounce) clearTimeout(timerPrecioDebounce);
        timerPrecioDebounce = setTimeout(() => {
            const precioActualRevisado = parseFloat(AppState.citaSeleccionada.precio_usd);
            if (Math.abs(nuevoPrecio - precioActualRevisado) > 0.001) {
                autoGuardarDetalleCita('precio');
            }
        }, 60);
    }
}

async function manejarCambioEstadoCita() {
    if (!AppState.citaSeleccionada) return;
    const selectEstado = document.getElementById('det_estado');
    if (!selectEstado) return;
    const nuevoEstado = selectEstado.value;
    if (!nuevoEstado || nuevoEstado === AppState.citaSeleccionada.estado) return;

    const ind = document.getElementById('det_auto_save_indicator');
    if (ind) {
        ind.className = 'text-primary small fw-semibold';
        ind.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" style="width: 0.75rem; height: 0.75rem;"></span> Guardando...';
        ind.classList.remove('d-none');
    }

    try {
        const res = await fetch('api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'cambiar_estado',
                cita_id: AppState.citaSeleccionada.id,
                estado: nuevoEstado
            })
        });

        const json = await res.json();
        if (json.success) {
            AppState.citaSeleccionada.estado = nuevoEstado;

            // Actualizar badge superior
            const estadoInfo = AppState.estados[nuevoEstado] || { label: nuevoEstado, badge: 'primary', emoji: '' };
            const badgeEl = document.getElementById('det_estado_badge');
            if (badgeEl) {
                badgeEl.className = `badge bg-${estadoInfo.badge}`;
                badgeEl.innerText = `${estadoInfo.emoji} ${estadoInfo.label}`;
            }

            // Indicador de autoguardado
            if (ind) {
                ind.className = 'text-success small fw-semibold';
                ind.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Guardado';
                if (timerDetalleAutoSave) clearTimeout(timerDetalleAutoSave);
                timerDetalleAutoSave = setTimeout(() => {
                    if (ind) ind.classList.add('d-none');
                }, 2500);
            }

            mostrarToast(`Estado actualizado a: ${nuevoEstado}`, 'success');

            // Refrescar agenda de fondo
            cargarAgendaSegunVista();
        } else {
            if (ind) ind.classList.add('d-none');
            selectEstado.value = AppState.citaSeleccionada.estado;
            mostrarToast(json.message || 'Error al cambiar estado', 'danger');
        }
    } catch (e) {
        console.error('Error al cambiar estado:', e);
        if (ind) ind.classList.add('d-none');
        selectEstado.value = AppState.citaSeleccionada.estado;
        mostrarToast('Error de comunicación al cambiar estado', 'danger');
    }
}

async function autoGuardarDetalleCita(campoModificado) {
    if (!AppState.citaSeleccionada) return;
    const citaId = AppState.citaSeleccionada.id;

    const selectMed = document.getElementById('det_medico_id');
    const selectTipo = document.getElementById('det_tipo');
    const inputPrecio = document.getElementById('det_precio');
    const selectEstado = document.getElementById('det_estado');

    const medicoId = selectMed ? (parseInt(selectMed.value) || AppState.citaSeleccionada.medico_id) : AppState.citaSeleccionada.medico_id;
    const tipo = selectTipo ? (selectTipo.value || AppState.citaSeleccionada.tipo) : AppState.citaSeleccionada.tipo;
    const precio = inputPrecio ? (parseFloat(inputPrecio.value) || 0) : AppState.citaSeleccionada.precio_usd;
    const estado = selectEstado ? (selectEstado.value || AppState.citaSeleccionada.estado) : AppState.citaSeleccionada.estado;

    if (isNaN(precio) || precio < 0) {
        mostrarToast('Por favor ingrese un precio válido (mayor o igual a 0)', 'warning');
        return;
    }

    const ind = document.getElementById('det_auto_save_indicator');
    if (ind) {
        ind.className = 'text-primary small fw-semibold';
        ind.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" style="width: 0.75rem; height: 0.75rem;"></span> Guardando...';
        ind.classList.remove('d-none');
    }

    try {
        const res = await fetch('api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'editar',
                cita_id: citaId,
                medico_id: medicoId,
                tipo: tipo,
                precio_usd: precio,
                estado: estado
            })
        });

        const json = await res.json();
        if (json.success) {
            // Actualizar AppState.citaSeleccionada
            if (json.data) {
                AppState.citaSeleccionada = json.data;
            } else {
                AppState.citaSeleccionada.medico_id = medicoId;
                AppState.citaSeleccionada.tipo = tipo;
                AppState.citaSeleccionada.precio_usd = precio;
                const med = (AppState.medicos || []).find(m => m.id == medicoId);
                if (med) {
                    AppState.citaSeleccionada.medico_nombre = med.nombre;
                    AppState.citaSeleccionada.medico_especialidad = med.especialidad;
                }
            }

            // Mostrar feedback de guardado exitoso
            if (ind) {
                ind.className = 'text-success small fw-semibold';
                ind.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Guardado';
                if (timerDetalleAutoSave) clearTimeout(timerDetalleAutoSave);
                timerDetalleAutoSave = setTimeout(() => {
                    if (ind) ind.classList.add('d-none');
                }, 2500);
            }

            // Notificación breve toast
            let msjToast = 'Cambio guardado automáticamente';
            let titToast = 'Cita Actualizada';
            if (campoModificado === 'medico') {
                titToast = 'Médico Asignado';
                msjToast = AppState.citaSeleccionada.medico_nombre;
            } else if (campoModificado === 'tipo') {
                titToast = 'Tipo de Cita';
                msjToast = tipo;
            } else if (campoModificado === 'precio') {
                titToast = 'Precio Actualizado';
                msjToast = `$${precio.toFixed(2)} USD`;
            }
            mostrarToast(msjToast, 'success', titToast);

            // Refrescar la agenda de fondo para reflejar de inmediato los cambios en la matriz
            cargarAgendaSegunVista();
        } else {
            if (ind) ind.classList.add('d-none');
            mostrarToast(json.message || 'Error al guardar cambios', 'danger');
        }
    } catch (e) {
        console.error('Error al autoguardar cita:', e);
        if (ind) ind.classList.add('d-none');
        mostrarToast('Error de comunicación al guardar cambios', 'danger');
    }
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
    const selEstadoFinal = document.getElementById('cobrar_estado_final');
    if (selEstadoFinal) {
        selEstadoFinal.value = (cita.estado === 'Facturado' || cita.estado === 'Pagado') ? cita.estado : 'Facturado';
    }

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
 * -------------------------------------------------------------
 * GESTIÓN DE REPORTE SEMANAL DE INGRESOS (PDF)
 * -------------------------------------------------------------
 */
let fechaSemanaReporteActual = null;

function abrirModalReporteSemanal() {
    fechaSemanaReporteActual = AppState.fechaActual || new Date().toISOString().slice(0, 10);
    actualizarVistaSemanaReporte();
    
    const modalEl = document.getElementById('modalReporteSemanal');
    if (modalEl) {
        let bsModal = bootstrap.Modal.getInstance(modalEl);
        if (!bsModal) {
            bsModal = new bootstrap.Modal(modalEl);
        }
        bsModal.show();
    }
}

function cambiarSemanaReporte(deltaSemanas) {
    if (!fechaSemanaReporteActual) {
        fechaSemanaReporteActual = AppState.fechaActual || new Date().toISOString().slice(0, 10);
    }
    const d = parseLocalDate(fechaSemanaReporteActual);
    d.setDate(d.getDate() + (deltaSemanas * 7));
    fechaSemanaReporteActual = formatDateISO(d);
    actualizarVistaSemanaReporte();
}

function irASemanaActualReporte() {
    fechaSemanaReporteActual = AppState.fechaActual || new Date().toISOString().slice(0, 10);
    actualizarVistaSemanaReporte();
}

function actualizarVistaSemanaReporte() {
    if (!fechaSemanaReporteActual) {
        fechaSemanaReporteActual = AppState.fechaActual || new Date().toISOString().slice(0, 10);
    }
    const { inicio, fin } = obtenerLimitesSemana(fechaSemanaReporteActual);
    const d1 = parseLocalDate(inicio);
    const d2 = parseLocalDate(fin);
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    
    const rangoTexto = `Semana del ${d1.getDate()} ${meses[d1.getMonth()]} al ${d2.getDate()} ${meses[d2.getMonth()]} ${d2.getFullYear()}`;
    const diasTexto = `Lunes ${String(d1.getDate()).padStart(2, '0')}/${String(d1.getMonth() + 1).padStart(2, '0')} a Domingo ${String(d2.getDate()).padStart(2, '0')}/${String(d2.getMonth() + 1).padStart(2, '0')}`;
    
    const elRango = document.getElementById('rep_sem_rango_texto');
    const elDias = document.getElementById('rep_sem_dias_subtexto');
    
    if (elRango) elRango.innerText = rangoTexto;
    if (elDias) elDias.innerText = diasTexto;
}

function exportarReporteSemanalPDF() {
    if (!fechaSemanaReporteActual) {
        fechaSemanaReporteActual = AppState.fechaActual || new Date().toISOString().slice(0, 10);
    }
    const { inicio, fin } = obtenerLimitesSemana(fechaSemanaReporteActual);
    
    const modalEl = document.getElementById('modalReporteSemanal');
    if (modalEl) {
        const bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) bsModal.hide();
    }
    
    window.open(`api/reporte_semanal_pdf.php?inicio=${inicio}&fin=${fin}`, '_blank');
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
/**
 * Genera y abre el archivo PDF oficial de control de beneficios en orientación horizontal
 */
function exportarBeneficiosPDF() {
    const mes = document.getElementById('ben_mes_selector').value || AppState.mesActual;
    window.open(`api/beneficios_pdf.php?mes=${mes}`, '_blank');
}

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

    // Actualizar select de médicos en detalle de cita
    const selDetMed = document.getElementById('det_medico_id');
    if (selDetMed && selDetMed.options.length > 0) {
        const valorActual = selDetMed.value;
        selDetMed.innerHTML = '';
        medicosActivos.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.text = `${m.nombre} (${m.especialidad})`;
            selDetMed.appendChild(opt);
        });
        if (valorActual) selDetMed.value = valorActual;
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

/**
 * -------------------------------------------------------------
 * SISTEMA PROFESIONAL DE NOTIFICACIONES TOAST (WHITE THEME)
 * Inspirado en Toastify-Pro (teguig-med / Codepen)
 * -------------------------------------------------------------
 */
const ToastProManager = {
    activeToasts: [],
    maxVisible: 4,
    lastToastKey: '',
    lastToastTime: 0,

    getIconSVG(tipo) {
        switch (tipo) {
            case 'success':
                return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>`;
            case 'danger':
            case 'error':
                return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>`;
            case 'warning':
                return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>`;
            case 'info':
            default:
                return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>`;
        }
    },

    formatContent(mensaje, tipo, tituloPersonalizado) {
        let titulo = tituloPersonalizado || '';
        let descripcion = mensaje || '';

        // Si ya viene título explícito, respetar
        if (titulo) {
            return { titulo, descripcion };
        }

        // Detección inteligente de textos habituales de la aplicación
        if (typeof mensaje === 'string') {
            if (mensaje.startsWith('Estado actualizado a:') || mensaje.startsWith('Estado cambiado a:')) {
                const partes = mensaje.split(':');
                const estado = partes[1] ? partes[1].trim() : '';
                const estadoInfo = (window.AppState && AppState.estados && AppState.estados[estado]) || { label: estado, badge: 'primary', emoji: '📌' };
                titulo = 'Estado Actualizado';
                descripcion = `Cita cambiada a <span class="badge bg-${estadoInfo.badge} ms-1">${estadoInfo.emoji || ''} ${estadoInfo.label || estado}</span>`;
            } else if (mensaje.startsWith('Médico actualizado:')) {
                titulo = 'Médico Actualizado';
                descripcion = `<strong>${mensaje.replace('Médico actualizado:', '').trim()}</strong>`;
            } else if (mensaje.startsWith('Tipo de cita actualizado a:')) {
                titulo = 'Tipo de Cita';
                descripcion = `Actualizado a <strong>${mensaje.replace('Tipo de cita actualizado a:', '').trim()}</strong>`;
            } else if (mensaje.startsWith('Precio actualizado a:')) {
                titulo = 'Precio Actualizado';
                descripcion = `Monto fijado en <strong>${mensaje.replace('Precio actualizado a:', '').trim()}</strong>`;
            } else if (mensaje === 'Cita agendada exitosamente') {
                titulo = '¡Cita Agendada!';
                descripcion = 'El turno fue reservado exitosamente.';
            } else if (mensaje === 'Cobro registrado y cita actualizada exitosamente') {
                titulo = '¡Cobro Procesado!';
                descripcion = 'Pago registrado y cita actualizada con éxito.';
            } else if (mensaje === 'Tasa BCV actualizada exitosamente') {
                titulo = 'Tasa BCV Guardada';
                descripcion = 'La tasa oficial del BCV fue actualizada correctamente.';
            } else if (mensaje === 'Cita cancelada y eliminada') {
                titulo = 'Cita Cancelada';
                descripcion = 'El turno ha sido cancelado y liberado.';
            } else if (mensaje === 'Paciente registrado exitosamente') {
                titulo = 'Paciente Registrado';
                descripcion = 'La ficha médica se guardó correctamente.';
            } else if (tipo === 'danger' || tipo === 'error') {
                titulo = 'Error en la Operación';
            } else if (tipo === 'warning') {
                titulo = 'Atención';
            } else if (tipo === 'info') {
                titulo = 'Información';
            } else {
                titulo = '¡Operación Exitosa!';
            }
        }

        return { titulo, descripcion };
    },

    removeToast(toastEl) {
        if (!toastEl || toastEl.dataset.removing === 'true') return;
        toastEl.dataset.removing = 'true';
        toastEl.classList.add('removing');

        const index = this.activeToasts.findIndex(t => t.element === toastEl);
        if (index > -1) {
            const tData = this.activeToasts[index];
            if (tData.timeoutId) clearTimeout(tData.timeoutId);
            this.activeToasts.splice(index, 1);
        }

        setTimeout(() => {
            if (toastEl.parentNode) {
                toastEl.parentNode.removeChild(toastEl);
            }
        }, 380);
    }
};

function mostrarToast(mensaje, tipo = 'success', titulo = null, duracion = 3500) {
    // Soporte para paso de objeto de configuración
    if (typeof mensaje === 'object' && mensaje !== null) {
        tipo = mensaje.tipo || mensaje.type || tipo;
        titulo = mensaje.titulo || mensaje.title || titulo;
        duracion = mensaje.duracion || mensaje.duration || duracion;
        mensaje = mensaje.mensaje || mensaje.message || '';
    }

    // Normalizar tipos
    if (tipo === 'error') tipo = 'danger';

    // Obtener o crear contenedor dinámicamente
    let container = document.getElementById('toastProContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastProContainer';
        container.className = 'toast-pro-container bottom-right no-print';
        document.body.appendChild(container);
    }

    // Limitar cantidad de toasts visibles simultáneamente
    if (ToastProManager.activeToasts.length >= ToastProManager.maxVisible) {
        const oldest = ToastProManager.activeToasts.shift();
        if (oldest && oldest.element) {
            ToastProManager.removeToast(oldest.element);
        }
    }

    const { titulo: finalTitulo, descripcion: finalDescripcion } = ToastProManager.formatContent(mensaje, tipo, titulo);

    // Prevención de duplicados rápidos (deduplicación en ventana de 700ms)
    const toastSignature = `${tipo}__${finalTitulo}__${finalDescripcion}`;
    const ahora = Date.now();
    if (toastSignature === ToastProManager.lastToastKey && (ahora - ToastProManager.lastToastTime) < 700) {
        return null;
    }
    ToastProManager.lastToastKey = toastSignature;
    ToastProManager.lastToastTime = ahora;

    // Crear elemento toast profesional
    const toast = document.createElement('div');
    toast.className = `toast-pro toast-pro-${tipo}`;
    toast.setAttribute('role', tipo === 'danger' ? 'alert' : 'status');
    toast.style.setProperty('--duration', `${duracion}ms`);

    toast.innerHTML = `
        <div class="toast-pro-shimmer"></div>
        <div class="toast-pro-icon" aria-hidden="true">
            ${ToastProManager.getIconSVG(tipo)}
        </div>
        <div class="toast-pro-content">
            <div class="toast-pro-title">${finalTitulo}</div>
            <div class="toast-pro-message">${finalDescripcion}</div>
        </div>
        <button type="button" class="toast-pro-close" aria-label="Cerrar notificación">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <div class="toast-pro-progress"></div>
    `;

    // Evento de cierre manual al hacer clic en la X
    const closeBtn = toast.querySelector('.toast-pro-close');
    if (closeBtn) {
        closeBtn.onclick = (e) => {
            e.stopPropagation();
            ToastProManager.removeToast(toast);
        };
    }

    // Control de tiempo y pausa al posar el cursor (Hover Pause)
    const toastData = {
        element: toast,
        timeoutId: null,
        startTime: Date.now(),
        remainingTime: duracion,
        isPaused: false
    };

    if (duracion > 0) {
        toast.addEventListener('mouseenter', () => {
            if (toast.dataset.removing === 'true') return;
            if (toastData.timeoutId) {
                clearTimeout(toastData.timeoutId);
                toastData.remainingTime -= (Date.now() - toastData.startTime);
                toastData.isPaused = true;
            }
        });

        toast.addEventListener('mouseleave', () => {
            if (toast.dataset.removing === 'true') return;
            if (toastData.isPaused && toastData.remainingTime > 0) {
                toastData.isPaused = false;
                toastData.startTime = Date.now();
                toastData.timeoutId = setTimeout(() => {
                    ToastProManager.removeToast(toast);
                }, toastData.remainingTime);
            }
        });

        toastData.timeoutId = setTimeout(() => {
            ToastProManager.removeToast(toast);
        }, duracion);
    }

    ToastProManager.activeToasts.push(toastData);

    // Insertar en contenedor
    container.appendChild(toast);

    // Animación de entrada Airdrop Pop
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    return toast;
}

// Atajo accesible: cerrar el toast superior con tecla Escape (si no hay modal abierto)
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && ToastProManager.activeToasts.length > 0) {
        const modalAbierto = document.querySelector('.modal.show');
        if (!modalAbierto) {
            const lastToast = ToastProManager.activeToasts[ToastProManager.activeToasts.length - 1];
            if (lastToast && lastToast.element) {
                ToastProManager.removeToast(lastToast.element);
            }
        }
    }
});
