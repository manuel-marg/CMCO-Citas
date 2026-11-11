<!-- SECCIÓN: GESTIÓN Y CATÁLOGO DE MÉDICOS Y ESPECIALISTAS -->
<section id="section-medicos" class="py-3 d-none">
    <div class="container-fluid px-4">
        
        <!-- Barra Superior de Control -->
        <div class="agenda-bar mb-3 no-print">
            <div class="row g-3 align-items-center">
                <div class="col-lg-5 col-md-12">
                    <h4 class="fw-bold mb-1 text-primary d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge"></i> Catálogo de Médicos y Especialistas
                    </h4>
                    <p class="small text-muted mb-0">
                        Administración de profesionales de la salud, especialidades y porcentaje de honorarios.
                    </p>
                </div>
                
                <div class="col-lg-4 col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control" id="medicos_search_input" 
                               placeholder="Buscar médico o especialidad..." oninput="filtrarMedicosCatalogo(this.value)">
                        <select class="form-select form-select-sm w-auto flex-grow-0" id="medicos_filtro_estado" onchange="cargarCatalogoMedicos()">
                            <option value="all">Todos</option>
                            <option value="1" selected>Solo Activos</option>
                            <option value="0">Solo Inactivos</option>
                        </select>
                    </div>
                </div>

                <div class="col-lg-3 col-md-5 text-md-end d-flex align-items-center justify-content-md-end gap-2">
                    <button class="btn btn-primary fw-bold shadow-sm" onclick="abrirModalNuevoMedico()">
                        <i class="bi bi-person-plus-fill me-1"></i> + Registrar Doctor
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla del Catálogo de Médicos -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0 text-center" id="tabla_medicos_catalogo">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-start ps-4">Médico / Especialista</th>
                                <th>Especialidad</th>
                                <th>Color Agenda</th>
                                <th>Teléfono</th>
                                <th>% Honorarios</th>
                                <th>Citas en Historial</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_medicos_catalogo">
                            <tr>
                                <td colspan="8" class="py-4 text-muted">Cargando catálogo de médicos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Modal para Registrar / Editar Médico -->
<div class="modal fade" id="modalGuardarMedico" tabindex="-1" aria-labelledby="modalGuardarMedicoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="modalGuardarMedicoLabel">
                    <i class="bi bi-person-plus-fill me-1"></i> Registrar Doctor / Especialista
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form_guardar_medico" onsubmit="guardarMedico(event)">
                <div class="modal-body p-3">
                    <input type="hidden" id="medico_form_id" value="0">
                    
                    <div class="mb-3">
                        <label for="medico_form_nombre" class="form-label small fw-bold text-dark mb-1">
                            Nombre y Título <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="medico_form_nombre" 
                                   placeholder="Ej: Dra. Valentina Rodríguez o Dr. Roberto Silva" required>
                        </div>
                        <div class="form-text small text-muted" style="font-size: 0.75rem;">
                            Incluye el prefijo (Dr., Dra., Lcda., Odo.) para conservarlo en la agenda e informes.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="medico_form_especialidad" class="form-label small fw-bold text-dark mb-1">
                            Especialidad Médica <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="bi bi-award"></i></span>
                            <input type="text" class="form-control" id="medico_form_especialidad" 
                                   list="lista_especialidades_sugeridas" placeholder="Ej: Medicina General, Pediatría..." required>
                            <datalist id="lista_especialidades_sugeridas">
                                <option value="Medicina General">
                                <option value="Laboratorio Clínico">
                                <option value="Exámenes de Laboratorio">
                                <option value="Pediatría">
                                <option value="Cardiología">
                                <option value="Ginecología y Obstetricia">
                                <option value="Traumatología">
                                <option value="Odontología">
                                <option value="Dermatología">
                                <option value="Medicina Interna">
                                <option value="Terapia / Especialista">
                                <option value="Nutrición">
                                <option value="Psicología">
                            </datalist>
                        </div>
                    </div>

                    <!-- Selector de Color para la Agenda -->
                    <div class="mb-3">
                        <label for="medico_form_color" class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-palette text-primary me-1"></i> Color en la Agenda</span>
                            <span class="badge rounded-pill border" id="preview_color_badge" style="background-color: #4f90a5; color: #fff; font-size: 0.72rem;">#4F90A5</span>
                        </label>
                        
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="color" class="form-control form-control-color border-secondary-subtle" id="medico_form_color" value="#4f90a5" title="Elegir color personalizado" oninput="actualizarPreviewColorMedico(this.value)">
                            <input type="text" class="form-control form-control-sm font-monospace text-uppercase" id="medico_form_color_hex" value="#4F90A5" style="max-width: 100px;" oninput="actualizarColorDesdeInputHex(this.value)">
                            <small class="text-muted" style="font-size: 0.75rem;">Color del borde izquierdo en las tarjetas de citas.</small>
                        </div>

                        <!-- Paletas de Colores Rápidos -->
                        <div class="p-2 bg-light rounded-2 border">
                            <div class="small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">
                                Colores del Sistema Anterior (Google Sheets):
                            </div>
                            <div class="d-flex flex-wrap gap-1 mb-2" id="paleta_colores_anteriores">
                                <!-- Inyectado dinámicamente por JS -->
                            </div>

                            <div class="small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">
                                Colores Adicionales Disponibles:
                            </div>
                            <div class="d-flex flex-wrap gap-1" id="paleta_colores_adicionales">
                                <!-- Inyectado dinámicamente por JS -->
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="medico_form_porcentaje" class="form-label small fw-bold text-dark mb-1">
                                % Honorario (Médico / Lab.)
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light">%</span>
                                <input type="number" step="0.01" min="0" max="100" class="form-control" 
                                       id="medico_form_porcentaje" value="50.00" required>
                            </div>
                            <div class="form-text small text-muted" style="font-size: 0.72rem;">
                                Ej: 50% Médicos (50% ADH) o 80% Laboratorio (20% ADH).
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="medico_form_telefono" class="form-label small fw-bold text-dark mb-1">
                                Teléfono de Contacto
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                <input type="text" class="form-control" id="medico_form_telefono" placeholder="Ej: 04141234567">
                            </div>
                            <div class="form-text small text-muted" style="font-size: 0.72rem;">
                                Opcional.
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold" id="btn_guardar_medico">
                        <i class="bi bi-check-lg me-1"></i> Guardar Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
