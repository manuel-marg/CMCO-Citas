<!-- SECCIÓN: CONTROL DE BENEFICIOS Y EXONERACIONES -->
<section id="section-beneficios" class="py-3 d-none">
    <div class="container-fluid px-4">
        
        <!-- Barra de Control -->
        <div class="agenda-bar mb-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <h4 class="fw-bold mb-1 text-primary">
                        <i class="bi bi-gift me-1"></i> Control de Beneficios Especiales
                    </h4>
                    <p class="small text-muted mb-0">
                        Auditoría mensual de consultas de Empleados, Familiares directos y Participantes ADH.
                    </p>
                </div>
                <div class="col-md-3 d-flex align-items-center gap-2">
                    <label class="form-label mb-0 fw-bold small text-muted">Mes:</label>
                    <input type="month" class="form-control fw-bold border-primary" id="ben_mes_selector" onchange="cargarBeneficios()">
                </div>
                <div class="col-md-3 text-md-end no-print">
                    <button class="btn btn-outline-dark fw-bold" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Imprimir Reporte
                    </button>
                </div>
            </div>
        </div>

        <!-- Pestañas de Beneficios -->
        <ul class="nav nav-tabs nav-tabs-cmco mb-3" id="beneficiosTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tab-emp" data-bs-toggle="tab" data-bs-target="#content-emp" type="button">
                    <i class="bi bi-person-badge me-1"></i> Empleados del Mes (<span id="count_emp">0</span>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-fam" data-bs-toggle="tab" data-bs-target="#content-fam" type="button">
                    <i class="bi bi-people me-1"></i> Familiar Directo 50% (<span id="count_fam">0</span>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-part" data-bs-toggle="tab" data-bs-target="#content-part" type="button">
                    <i class="bi bi-heart me-1"></i> Participantes ADH (<span id="count_part">0</span>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-exon" data-bs-toggle="tab" data-bs-target="#content-exon" type="button">
                    <i class="bi bi-shield-check me-1"></i> Exonerados (<span id="count_exon">0</span>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="beneficiosTabContent">
            <!-- 1. Empleados -->
            <div class="tab-pane fade show active" id="content-emp">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 text-center align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th class="text-start ps-3">Nombre del Empleado (Paciente)</th>
                                        <th>Cédula</th>
                                        <th>Teléfono</th>
                                        <th>Médico Tratante</th>
                                        <th>Monto ($)</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody_beneficio_empleados">
                                    <tr><td colspan="7" class="py-4 text-muted">Cargando registros...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Familiares -->
            <div class="tab-pane fade" id="content-fam">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 text-center align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th class="text-start ps-3">Paciente Familiar</th>
                                        <th>Personal ADH / Representante</th>
                                        <th>Cédula</th>
                                        <th>Teléfono</th>
                                        <th>Médico Tratante</th>
                                        <th>Monto ($)</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody_beneficio_familiares">
                                    <tr><td colspan="8" class="py-4 text-muted">Cargando registros...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Participantes -->
            <div class="tab-pane fade" id="content-part">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 text-center align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th class="text-start ps-3">Participante ADH</th>
                                        <th>Cédula</th>
                                        <th>Teléfono</th>
                                        <th>Médico Tratante</th>
                                        <th>Monto ($)</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody_beneficio_participantes">
                                    <tr><td colspan="7" class="py-4 text-muted">Cargando registros...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Exonerados -->
            <div class="tab-pane fade" id="content-exon">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 text-center align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th class="text-start ps-3">Paciente</th>
                                        <th>Tipo Exoneración</th>
                                        <th>Cédula</th>
                                        <th>Médico Tratante</th>
                                        <th>Monto ($)</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody_beneficio_exonerados">
                                    <tr><td colspan="7" class="py-4 text-muted">Cargando registros...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
