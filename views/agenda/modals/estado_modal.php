<!-- Modal Detalle y Cambio de Estado de Cita (Diseño Compacto y Equilibrado) -->
<div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalEstadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content shadow border-0">
            <!-- Header Compacto -->
            <div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #1d4353 0%, #387387 100%);">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" id="modalEstadoLabel">
                    <i class="bi bi-calendar2-check text-info"></i> Detalle de la Cita
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-3">
                <input type="hidden" id="estado_cita_id">
                
                <!-- Ficha Rápida Ejecutiva y Compacta -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="text-truncate">
                            <div class="d-flex align-items-center gap-1">
                                <h6 class="fw-bold mb-0 text-primary text-truncate" id="det_paciente_nombre">Nombre del Paciente</h6>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 ms-1" title="Editar paciente" onclick="editarPacienteDesdeCita()">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </div>
                            <div class="text-muted small" style="font-size: 0.78rem;" id="det_paciente_cedula_tel">C.I / Teléfono</div>
                        </div>
                        <div class="text-end text-nowrap">
                            <span id="det_estado_badge" class="badge bg-primary" style="font-size: 0.75rem;">📌 Agendada</span>
                        </div>
                    </div>
                    
                    <div class="row g-1 pt-1.5 pb-2 border-top small" style="font-size: 0.8rem;">
                        <div class="col-6 text-truncate">
                            <i class="bi bi-clock me-1 text-primary"></i><strong>Hora:</strong> <span id="det_hora" class="text-dark fw-semibold">08:00 AM</span>
                        </div>
                        <div class="col-6 text-truncate">
                            <i class="bi bi-door-closed me-1 text-primary"></i><strong>Consultorio:</strong> <span id="det_consultorio" class="text-dark fw-semibold">1</span>
                        </div>
                    </div>

                    <!-- Campos Editables Rápidos con Autoguardado -->
                    <div class="modal-parametros-cita bg-white rounded-3 border border-secondary-subtle my-2" style="padding: 12px 14px;">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                <i class="bi bi-pencil-square text-primary me-1"></i> Parámetros de Cita:
                            </span>
                            <span id="det_auto_save_indicator" class="text-success small fw-semibold d-none" style="font-size: 0.72rem;">
                                <i class="bi bi-check-circle-fill me-1"></i> Guardado
                            </span>
                        </div>

                        <!-- Selector de Médico -->
                        <div class="mb-2">
                            <label for="det_medico_id" class="form-label mb-1 text-secondary fw-semibold small" style="font-size: 0.76rem;">
                                <i class="bi bi-person-badge text-primary me-1"></i> Médico / Especialista:
                            </label>
                            <select class="form-select form-select-sm fw-bold border-secondary-subtle" id="det_medico_id" onchange="manejarCambioMedicoCita()">
                                <!-- Se llena dinámicamente -->
                            </select>
                        </div>

                        <!-- Selectores de Tipo y Precio -->
                        <div class="row g-2">
                            <div class="col-7">
                                <label for="det_tipo" class="form-label mb-1 text-secondary fw-semibold small" style="font-size: 0.76rem;">
                                    <i class="bi bi-tag text-primary me-1"></i> Tipo de Cita:
                                </label>
                                <select class="form-select form-select-sm fw-bold border-secondary-subtle" id="det_tipo" onchange="manejarCambioTipoCita()">
                                    <option value="Particular">Particular</option>
                                    <option value="Empleado">Empleado</option>
                                    <option value="Familiar">Familiar</option>
                                    <option value="Participante">Participante</option>
                                    <option value="Exonerado ADH">Exonerado ADH</option>
                                    <option value="Exonerado Doctor">Exonerado Doctor</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="det_precio" class="form-label mb-1 text-secondary fw-semibold small" style="font-size: 0.76rem;">
                                    <i class="bi bi-currency-dollar text-success me-1"></i> Precio ($ USD):
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-success fw-bold px-1.5">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm fw-bold text-end border-secondary-subtle" id="det_precio" onchange="manejarCambioPrecioCita()" onkeydown="if(event.key === 'Enter') { event.preventDefault(); this.blur(); }" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Selector de Estado de la Cita -->
                        <div class="mt-2">
                            <label for="det_estado" class="form-label mb-1 text-secondary fw-semibold small" style="font-size: 0.76rem;">
                                <i class="bi bi-bookmark-check text-primary me-1"></i> Estado de la Cita:
                            </label>
                            <select class="form-select form-select-sm fw-bold border-secondary-subtle" id="det_estado" onchange="manejarCambioEstadoCita()">
                                <option value="Agendada">📌 Agendada</option>
                                <option value="Sin confirmar">📞 Sin confirmar</option>
                                <option value="Confirmado">✔️ Confirmado</option>
                                <option value="Atendido">✅ Atendido</option>
                                <option value="No asistio">🚫 No asistió</option>
                                <option value="Pagado">💲 Pagado</option>
                                <option value="Facturado">📦 Facturado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="det_representante_box" class="pt-1 mt-1 border-top small text-truncate d-none" style="font-size: 0.78rem;">
                        <i class="bi bi-people me-1 text-muted"></i><strong>Representante:</strong> <span id="det_representante" class="text-dark"></span>
                    </div>

                    <div id="det_pagos_box" class="pt-1 mt-1 border-top small d-none" style="font-size: 0.78rem;">
                        <div class="text-truncate">
                            <i class="bi bi-receipt me-1 text-success"></i><strong>Factura:</strong> <span id="det_factura" class="fw-semibold text-dark"></span>
                        </div>
                        <div id="det_pagos_lista" class="mt-0.5 text-muted small"></div>
                    </div>
                </div>

                <!-- Acciones Principales con Botón 'Registrar Pago' -->
                <div class="d-flex gap-2 pt-2 border-top">
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 text-nowrap" onclick="eliminarCitaActual()" title="Cancelar y eliminar esta cita">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                    <button type="button" class="btn btn-success fw-bold btn-sm flex-grow-1 py-1.5 text-truncate" onclick="abrirCobroDesdeDetalle()">
                        <i class="bi bi-cash-stack me-1"></i> Registrar Pago
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
