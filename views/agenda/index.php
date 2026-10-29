<!-- SECCIÓN: AGENDA MULTI-VISTA (DÍA, SEMANA, MES) -->
<section id="section-agenda" class="py-3">
    <div class="container-fluid px-4">
        
        <!-- Barra Superior de Control de Vistas, Fecha y Filtros -->
        <div class="agenda-bar mb-3">
            <div class="row g-3 align-items-center">
                
                <!-- Selector de Tipo de Vista (Día / Semana / Mes) -->
                <div class="col-xl-3 col-lg-4 col-md-12">
                    <div class="btn-group w-100 shadow-sm" role="group" aria-label="Selector de Vista">
                        <button type="button" class="btn btn-outline-primary active fw-bold" id="btn-vista-dia" onclick="cambiarVistaAgenda('dia')">
                            <i class="bi bi-view-list me-1"></i> Día
                        </button>
                        <button type="button" class="btn btn-outline-primary fw-bold" id="btn-vista-semana" onclick="cambiarVistaAgenda('semana')">
                            <i class="bi bi-calendar-week me-1"></i> Semana
                        </button>
                        <button type="button" class="btn btn-outline-primary fw-bold" id="btn-vista-mes" onclick="cambiarVistaAgenda('mes')">
                            <i class="bi bi-calendar3 me-1"></i> Mes
                        </button>
                    </div>
                </div>

                <!-- Controles de Navegación Temporal (Anterior, Selector, Siguiente, Hoy) -->
                <div class="col-xl-5 col-lg-5 col-md-8 d-flex align-items-center gap-2">
                    <button class="btn btn-outline-primary" onclick="navegarPeriodo(-1)" title="Período Anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <!-- Selector para Vista Día -->
                    <div id="wrapper_nav_dia" class="flex-grow-1">
                        <input type="date" class="form-control fw-bold text-center border-primary" id="agenda_fecha_selector" onchange="actualizarFechaDesdeInput()">
                    </div>

                    <!-- Indicador para Vista Semana -->
                    <div id="wrapper_nav_semana" class="flex-grow-1 d-none text-center">
                        <div class="badge bg-light text-primary border border-primary px-3 py-2 fs-6 w-100 text-truncate" id="label_periodo_semana">
                            Cargando semana...
                        </div>
                    </div>

                    <!-- Selector para Vista Mes -->
                    <div id="wrapper_nav_mes" class="flex-grow-1 d-none">
                        <input type="month" class="form-control fw-bold text-center border-primary" id="agenda_mes_selector" onchange="actualizarMesDesdeInput()">
                    </div>

                    <button class="btn btn-outline-primary" onclick="navegarPeriodo(1)" title="Período Siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="btn btn-primary fw-bold" onclick="irAHoy()" title="Ir al día de hoy">
                        Hoy
                    </button>
                </div>

                <!-- Botones de Acción: Filtrar y + Agendar Cita -->
                <div class="col-xl-4 col-lg-3 col-md-12 text-md-end d-flex justify-content-md-end justify-content-start gap-2">
                    <button type="button" class="btn btn-outline-primary fw-bold shadow-sm px-3" id="btn-toggle-filtros" onclick="toggleFiltrosAgenda()" title="Mostrar u ocultar filtros y métricas">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                    <button type="button" class="btn btn-success fw-bold shadow-sm px-3" onclick="abrirModalNuevoAgendamiento()">
                        <i class="bi bi-calendar-plus me-1"></i> + Agendar Cita
                    </button>
                </div>

            </div>

            <!-- Panel Desplegable: Línea Divisoria, Filtros y Métricas (Oculto por defecto para ahorrar espacio) -->
            <div id="panel_filtros_metricas" class="d-none mt-2 pt-2 border-top">
                <div class="row g-2 align-items-center">
                    <!-- Filtros para Consultorio y Médico -->
                    <div class="col-lg-6 col-md-12 d-flex flex-wrap gap-2 align-items-center">
                        <span class="small fw-bold text-muted me-1"><i class="bi bi-funnel me-1"></i>Filtros:</span>
                        <select class="form-select form-select-sm w-auto" id="filtro_consultorio_agenda" onchange="aplicarFiltrosAgenda()">
                            <option value="">Todos los Consultorios</option>
                            <option value="1">Consultorio N° 1</option>
                            <option value="2">Consultorio N° 2</option>
                            <option value="3">Consultorio N° 3</option>
                        </select>
                        <select class="form-select form-select-sm w-auto" id="filtro_medico_agenda" onchange="aplicarFiltrosAgenda()">
                            <option value="">Todos los Médicos</option>
                            <!-- Se llena dinámicamente -->
                        </select>
                        <button class="btn btn-sm btn-link text-decoration-none text-muted p-0" onclick="limpiarFiltrosAgenda()" title="Limpiar Filtros">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </button>
                    </div>

                    <!-- Métricas Rápidas del Período Visualizado -->
                    <div class="col-lg-6 col-md-12">
                        <div class="d-flex justify-content-lg-end justify-content-start gap-2 text-center">
                            <div class="metric-box py-1 px-2">
                                <span class="d-block small text-muted" style="font-size: 0.72rem;">Citas</span>
                                <span class="fw-bold" id="metric_total">0</span>
                            </div>
                            <div class="metric-box py-1 px-2 border-primary">
                                <span class="d-block small text-muted" style="font-size: 0.72rem;">Confirmadas</span>
                                <span class="fw-bold text-primary" id="metric_confirmadas">0</span>
                            </div>
                            <div class="metric-box py-1 px-2 border-success">
                                <span class="d-block small text-muted" style="font-size: 0.72rem;">Pagadas/Fact.</span>
                                <span class="fw-bold text-success" id="metric_pagadas">0</span>
                            </div>
                            <div class="metric-box py-1 px-2 bg-light">
                                <span class="d-block small text-muted" style="font-size: 0.72rem;">Total USD ($)</span>
                                <span class="fw-bold text-success" id="metric_monto_usd">$0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- ========================================== -->
        <!-- CONTENEDOR 1: VISTA DIARIA (3 CONSULTORIOS) -->
        <!-- ========================================== -->
        <div id="container_vista_dia" class="grid-container">
            <div class="row g-0 grid-header">
                <div class="col-1 text-center py-3 fw-bold border-end bg-light text-muted" style="min-width: 90px;">
                    <i class="bi bi-clock me-1"></i> Hora
                </div>
                <div class="col consultorio-col-header consultorio-1 border-end">
                    <i class="bi bi-door-open-fill text-primary me-1"></i> Consultorio N° 1
                </div>
                <div class="col consultorio-col-header consultorio-2 border-end">
                    <i class="bi bi-door-open-fill text-success me-1"></i> Consultorio N° 2
                </div>
                <div class="col consultorio-col-header consultorio-3">
                    <i class="bi bi-door-open-fill text-purple me-1"></i> Consultorio N° 3
                </div>
            </div>
            <div id="grid_slots_body">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Cargando agenda de citas...</p>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- CONTENEDOR 2: VISTA SEMANAL (7 COLUMNAS)   -->
        <!-- ========================================== -->
        <div id="container_vista_semana" class="d-none">
            <div class="row g-2" id="grid_semana_body">
                <!-- Se inyecta dinámicamente con las 7 columnas de Lunes a Domingo -->
            </div>
        </div>

        <!-- ========================================== -->
        <!-- CONTENEDOR 3: VISTA MENSUAL (CALENDARIO)   -->
        <!-- ========================================== -->
        <div id="container_vista_mes" class="d-none">
            <div class="calendar-container shadow-sm">
                <!-- Cabecera de Días de la Semana -->
                <div class="calendar-days-header">
                    <div>Lunes</div>
                    <div>Martes</div>
                    <div>Miércoles</div>
                    <div>Jueves</div>
                    <div>Viernes</div>
                    <div>Sábado</div>
                    <div>Domingo</div>
                </div>
                <!-- Grilla de Días del Mes -->
                <div class="calendar-grid" id="grid_mes_body">
                    <!-- Se inyecta dinámicamente con los 35-42 cuadros del mes -->
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Modales Asociados -->
<?php require_once __DIR__ . '/modals/agendar_modal.php'; ?>
<?php require_once __DIR__ . '/modals/cobrar_modal.php'; ?>
<?php require_once __DIR__ . '/modals/estado_modal.php'; ?>
<?php require_once __DIR__ . '/modals/bcv_modal.php'; ?>
