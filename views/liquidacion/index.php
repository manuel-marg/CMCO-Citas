<!-- SECCIÓN: LIQUIDACIÓN DE HONORARIOS MÉDICOS -->
<section id="section-liquidacion" class="py-3 d-none">
    <div class="container-fluid px-4">
        
        <!-- Encabezado Limpio Exclusivo para Impresión (Oculto en pantalla, visible solo al imprimir) -->
        <div class="d-none d-print-block mb-3 print-header-liquidacion">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                <div class="d-flex align-items-center gap-3">
                    <img src="assets/img/logo.png" alt="CMCO" style="height: 38px;">
                    <div>
                        <h3 class="h4 fw-bold text-dark mb-0" id="print_liq_titulo">Mes de Octubre de 2026</h3>
                        <small class="text-muted">Centro Médico Comunitario OscaryAnny (CMCO / ADH)</small>
                    </div>
                </div>
                <div class="text-end text-muted small">
                    <span id="print_liq_fecha"></span>
                </div>
            </div>
        </div>

        <!-- Barra de Control (Oculta al imprimir) -->
        <div class="agenda-bar mb-3 no-print">
            <div class="row g-3 align-items-center">
                <div class="col-xl-5 col-lg-5 col-md-12">
                    <h4 class="fw-bold mb-1 text-primary">
                        <i class="bi bi-calculator me-1"></i> Honorarios Médicos
                    </h4>
                    <p class="small text-muted mb-0">
                        Cálculo automático de honorarios médicos según el porcentaje.
                    </p>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-6 d-flex align-items-center gap-2">
                    <label class="form-label mb-0 fw-bold small text-muted">Mes:</label>
                    <input type="month" class="form-control fw-bold border-primary" id="liq_mes_selector" onchange="cargarLiquidacion()">
                    <button class="btn btn-primary" onclick="cargarLiquidacion()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Recargar liquidación">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6 text-md-end d-flex align-items-center justify-content-md-end gap-2">
                    <button class="btn btn-outline-primary fw-bold shadow-sm" onclick="abrirModalReporteSemanal()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Reporte Semanal de Pagos">
                        <i class="bi bi-calendar-week"></i>
                    </button>
                    <button class="btn btn-primary fw-bold shadow-sm" onclick="exportarLiquidacionPDF()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Exportar a PDF (Liquidación Mensual)">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                    </button>
                    <button class="btn btn-outline-primary fw-bold shadow-sm" onclick="showSection('medicos')" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Gestionar Doctores">
                        <i class="bi bi-person-gear"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla Resumen por Médico -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0 text-center" id="tabla_liquidacion">
                        <thead class="table-dark align-middle">
                            <tr class="align-middle">
                                <th class="text-start ps-3 align-middle">Médico / Especialista</th>
                                <th class="align-middle">Especialidad</th>
                                <th class="align-middle">Cantidad Citas</th>
                                <th class="align-middle">Citas Pagadas</th>
                                <th class="align-middle">Total Bruto</th>
                                <th class="align-middle">Monto Cobrado</th>
                                <th class="bg-primary text-white align-middle">Monto ADH</th>
                                <th class="bg-success text-white align-middle">Monto Médico</th>
                                <th class="no-print align-middle">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_liquidacion">
                            <tr>
                                <td colspan="9" class="py-4 text-muted">Cargando reporte de liquidación...</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold fs-6" id="tfoot_liquidacion">
                            <!-- Totales inyectados por JS -->
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Deducciones de Laboratorios Externos / Insumos de Terceros -->
        <div class="card shadow-sm border-warning-subtle mb-4 d-none" id="card_deducciones_lab">
            <div class="card-header bg-warning-subtle py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark small">
                    <i class="bi bi-box-seam text-warning-emphasis me-1"></i> Deducciones a Laboratorios Externos e Insumos de Terceros (Pagos Fijos Directos Sin Porcentaje)
                </span>
                <span class="badge bg-warning text-dark border border-warning" id="badge_total_deduc_lab">$0.00</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 text-center" style="font-size: 0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-start ps-3">Paciente</th>
                                <th>Tipo</th>
                                <th>Médico Tratante</th>
                                <th>Procedimiento / Insumo</th>
                                <th>Cobrado</th>
                                <th class="text-danger fw-bold">Deduc. Lab (Fijo)</th>
                                <th class="text-success fw-bold">Neto Repartido</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_deducciones_lab">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-1 px-3 text-muted small" style="font-size: 0.72rem;">
                <i class="bi bi-info-circle me-1"></i> Los importes de <strong>Deduc. Lab</strong> corresponden a costos directos de laboratorios externos (férulas, odontología, prótesis, etc.) que se liquidan íntegramente a terceros sin porcentaje institucional ni médico.
            </div>
        </div>

        <!-- Detalle de Citas del Médico Seleccionado -->
        <div class="card shadow-sm border-0 d-none" id="card_detalle_medico">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-card-checklist text-primary me-2"></i> 
                    Desglose de Citas: <span id="detalle_nombre_medico" class="text-primary"></span>
                </h5>
                <button class="btn btn-sm btn-outline-secondary no-print" onclick="cerrarDetalleMedico()">
                    <i class="bi bi-x-lg"></i> Cerrar Detalle
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 text-center">
                        <thead class="table-light align-middle">
                            <tr class="align-middle">
                                <th class="align-middle">Fecha</th>
                                <th class="align-middle">Hora</th>
                                <th class="align-middle">Consultorio</th>
                                <th class="align-middle">Paciente</th>
                                <th class="align-middle">Cédula</th>
                                <th class="align-middle">Tipo de Cita</th>
                                <th class="align-middle">Estado</th>
                                <th class="align-middle">Precio ($)</th>
                                <th class="align-middle">Factura / Beneficio</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_detalle_citas">
                            <!-- Inyectado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal para Selección de Semana del Reporte de Pagos -->
        <div class="modal fade" id="modalReporteSemanal" tabindex="-1" aria-labelledby="modalReporteSemanalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold" id="modalReporteSemanalLabel">
                            <i class="bi bi-calendar-week me-2"></i> Reporte Semanal de Pagos
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <p class="text-muted mb-3">
                            Seleccione la semana (de lunes a domingo) para generar el reporte consolidado de ingresos por método de pago y tipo de moneda en PDF:
                        </p>

                        <!-- Controles de navegación de semana -->
                        <div class="d-flex align-items-center justify-content-between bg-light p-3 rounded-3 border mb-3 shadow-xs">
                            <button class="btn btn-outline-primary" type="button" onclick="cambiarSemanaReporte(-1)" title="Semana anterior">
                                <i class="bi bi-chevron-left fs-5"></i>
                            </button>
                            <div class="px-2">
                                <div class="fw-bold fs-5 text-primary" id="rep_sem_rango_texto">Cargando semana...</div>
                                <div class="small text-muted fw-semibold" id="rep_sem_dias_subtexto">Lunes a Domingo</div>
                            </div>
                            <button class="btn btn-outline-primary" type="button" onclick="cambiarSemanaReporte(1)" title="Semana siguiente">
                                <i class="bi bi-chevron-right fs-5"></i>
                            </button>
                        </div>

                        <!-- Acciones rápidas -->
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="irASemanaActualReporte()">
                                <i class="bi bi-calendar-day me-1"></i> Esta Semana
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer bg-light justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary fw-bold" onclick="exportarReporteSemanalPDF()">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Generar Reporte PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
