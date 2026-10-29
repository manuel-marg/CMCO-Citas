<!-- Modal Agendar Nueva Cita -->
<div class="modal fade" id="modalAgendar" tabindex="-1" aria-labelledby="modalAgendarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formAgendar" onsubmit="guardarCita(event)">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" id="agendar_paciente_id" name="paciente_id" value="">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalAgendarLabel">
                        <i class="bi bi-calendar-plus me-1"></i> Agendar Cita Médica
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <!-- Fila Turno: Fecha, Hora y Consultorio -->
                    <div class="row g-3 mb-3 p-3 bg-light rounded-3 border">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Fecha:</label>
                            <input type="date" class="form-control fw-bold" id="agendar_fecha" name="fecha" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Hora de Cita:</label>
                            <select class="form-select fw-bold" id="agendar_hora" name="hora" required>
                                <!-- Se llena dinámicamente -->
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Consultorio:</label>
                            <select class="form-select fw-bold" id="agendar_consultorio_id" name="consultorio_id" required>
                                <!-- Se llena dinámicamente -->
                            </select>
                        </div>
                    </div>

                    <!-- Búsqueda y Datos del Paciente -->
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-bold">
                            <i class="bi bi-person-search text-primary me-1"></i> Paciente:
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="agendar_buscar_paciente" 
                                   placeholder="Escriba nombre o cédula para buscar..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" onclick="limpiarSeleccionPaciente()" title="Limpiar selección">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div id="autocomplete_pacientes" class="autocomplete-items d-none"></div>
                        <small class="text-muted">Si el paciente ya existe en el sistema, selecciónelo de la lista para auto-completar sus datos.</small>
                    </div>

                    <!-- Datos rápidos del paciente (editables/nuevos) -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nombre Completo:</label>
                            <input type="text" class="form-control" id="agendar_paciente_nombre" name="paciente_nombre" placeholder="Nombre y Apellido" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Cédula:</label>
                            <input type="text" class="form-control" id="agendar_paciente_cedula" name="paciente_cedula" placeholder="Ej: V12345678">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Teléfono:</label>
                            <input type="text" class="form-control" id="agendar_paciente_telefono" name="paciente_telefono" placeholder="Ej: 04141234567">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Representante (Si aplica):</label>
                            <input type="text" class="form-control" id="agendar_paciente_representante" name="paciente_representante" placeholder="Nombre del padre, madre o tutor">
                        </div>
                    </div>

                    <hr>

                    <!-- Médico, Tipo y Precio -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-person-badge text-primary me-1"></i> Médico / Especialista:
                            </label>
                            <select class="form-select" id="agendar_medico_id" name="medico_id" required>
                                <!-- Se llena dinámicamente -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-tags text-primary me-1"></i> Tipo de Cita / Beneficio:
                            </label>
                            <select class="form-select" id="agendar_tipo" name="tipo" onchange="actualizarPrecioSegunTipo()" required>
                                <option value="Particular">Particular (Estándar 100%)</option>
                                <option value="Empleado">Empleado (Beneficio 100% CMCO)</option>
                                <option value="Familiar">Familiar Directo (50% Descuento)</option>
                                <option value="Participante">Participante ADH (Beneficio)</option>
                                <option value="Exonerado ADH">Exonerado ADH (50% Descuento)</option>
                                <option value="Exonerado Doctor">Exonerado Doctor (50% Descuento)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Precio en $ USD:</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control fw-bold" id="agendar_precio_usd" name="precio_usd" value="50.00" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Motivo de Consulta / Especialidad:</label>
                            <input type="text" class="form-control" id="agendar_motivo" name="motivo_consulta" placeholder="Ej: Consulta general, control, dolor...">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Observaciones Adicionales:</label>
                            <textarea class="form-control" id="agendar_observaciones" name="observaciones" rows="2" placeholder="Notas sobre el paciente o condiciones especiales..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Confirmar y Agendar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
