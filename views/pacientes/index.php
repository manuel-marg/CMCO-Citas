<!-- SECCIÓN: DIRECTORIO DE PACIENTES -->
<section id="section-pacientes" class="py-3 d-none">
    <div class="container-fluid px-4">
        
        <!-- Barra de Búsqueda y Botón Nuevo -->
        <div class="agenda-bar mb-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <h4 class="fw-bold mb-1 text-primary d-flex align-items-center gap-2">
                        <i class="bi bi-people"></i> Directorio de Pacientes
                        <span class="badge bg-primary fs-6 fw-normal" id="badge_total_pacientes">Cargando...</span>
                    </h4>
                    <p class="small text-muted mb-0">
                        Base de datos central con historial de citas y tipos de beneficio.
                    </p>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control" id="directorio_search_input" 
                               placeholder="Buscar por nombre, cédula o representante..." oninput="buscarPacientesDirectorio(this.value)">
                    </div>
                </div>
                <div class="col-md-3 text-md-end">
                    <button class="btn btn-primary fw-bold" onclick="abrirModalNuevoPaciente()">
                        <i class="bi bi-person-plus me-1"></i> + Registrar Paciente
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla de Pacientes -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-3">Nombre y Apellido</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Representante</th>
                                <th>Tipo / Rol</th>
                                <th class="text-center">Historial</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_pacientes_directorio">
                            <tr>
                                <td colspan="6" class="py-4 text-center text-muted">Cargando pacientes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Controles de Paginación y Selector de Límite -->
            <div class="card-footer bg-white py-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="small text-muted" id="pacientes_info_registros">
                        Mostrando 0 a 0 de 0 pacientes
                    </span>
                    <div class="d-flex align-items-center gap-2 small">
                        <span class="text-muted fw-semibold">Mostrar:</span>
                        <select class="form-select form-select-sm w-auto shadow-none" id="pacientes_select_limit" onchange="cambiarLimitePacientes(this.value)">
                            <option value="25">25 por pág.</option>
                            <option value="50" selected>50 por pág.</option>
                            <option value="100">100 por pág.</option>
                            <option value="all">Ver Todos</option>
                        </select>
                    </div>
                </div>
                <nav aria-label="Navegación de páginas de pacientes">
                    <ul class="pagination pagination-sm mb-0 justify-content-center" id="pacientes_pagination_ul">
                        <!-- Botones de página inyectados por JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</section>

<!-- Modal Ficha / Historial del Paciente -->
<div class="modal fade" id="modalHistorialPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-lines-fill me-1"></i> Ficha e Historial de Citas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <h5 class="fw-bold mb-1 text-primary" id="hist_paciente_nombre">Nombre</h5>
                    <div class="row g-2 small text-muted">
                        <div class="col-md-4"><strong>Cédula:</strong> <span id="hist_paciente_cedula">-</span></div>
                        <div class="col-md-4"><strong>Teléfono:</strong> <span id="hist_paciente_telefono">-</span></div>
                        <div class="col-md-4"><strong>Beneficio:</strong> <span id="hist_paciente_tipo" class="badge bg-secondary">-</span></div>
                        <div class="col-md-12"><strong>Representante:</strong> <span id="hist_paciente_repres">-</span></div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2">Historial de Consultas:</h6>
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-sm table-bordered align-middle text-center mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Consultorio</th>
                                <th>Médico</th>
                                <th>Estado</th>
                                <th>Precio ($)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_historial_citas">
                            <tr><td colspan="6" class="py-3 text-muted">Sin historial</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear / Editar Paciente -->
<div class="modal fade" id="modalPacienteForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formPacienteDirectorio" onsubmit="guardarPacienteDirectorio(event)">
                <input type="hidden" id="pac_form_id" name="id" value="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pac_form_title">
                        <i class="bi bi-person-plus me-1"></i> Registrar Paciente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nombre y Apellido:</label>
                        <input type="text" class="form-control" id="pac_form_nombre" name="nombre" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">Cédula:</label>
                            <input type="text" class="form-control" id="pac_form_cedula" name="cedula" placeholder="Ej: V12345678">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">Teléfono:</label>
                            <input type="text" class="form-control" id="pac_form_telefono" name="telefono" placeholder="Ej: 04141234567">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Representante (Si aplica):</label>
                        <input type="text" class="form-control" id="pac_form_representante" name="representante">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Clasificación / Beneficio:</label>
                        <select class="form-select" id="pac_form_tipo_beneficio" name="tipo_beneficio">
                            <option value="Particular">Particular</option>
                            <option value="Empleado">Empleado</option>
                            <option value="Familiar">Familiar Directo (50%)</option>
                            <option value="Participante">Participante ADH</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted">Observaciones / Rol:</label>
                        <textarea class="form-control" id="pac_form_observaciones" name="observaciones" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
