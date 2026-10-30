<nav class="navbar navbar-expand-lg navbar-dark navbar-cmco py-2 sticky-top no-print">
    <div class="container-fluid px-4">
        <!-- Logo / Marca -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="#agenda" onclick="showSection('agenda')">
            <div class="navbar-logo-box">
                <img src="assets/img/logo.png" alt="CMCO Logo" class="navbar-logo-img">
            </div>
            <div class="d-flex flex-column justify-content-center">
                <span class="brand-text"><?= APP_NAME ?></span>
                <small class="brand-subtitle text-white-50"><?= APP_ORG ?></small>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Pestañas de Navegación -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
                <li class="nav-item">
                    <a class="nav-link active px-3" id="tab-agenda" href="javascript:void(0)" onclick="showSection('agenda')">
                        <i class="bi bi-calendar3 me-1"></i> Agenda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" id="tab-liquidacion" href="javascript:void(0)" onclick="showSection('liquidacion')">
                        <i class="bi bi-calculator me-1"></i> Honorarios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" id="tab-beneficios" href="javascript:void(0)" onclick="showSection('beneficios')">
                        <i class="bi bi-gift me-1"></i> Beneficios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" id="tab-pacientes" href="javascript:void(0)" onclick="showSection('pacientes')">
                        <i class="bi bi-people me-1"></i> Pacientes
                    </a>
                </li>
            </ul>

            <!-- Pastilla Tasa BCV Oficial (Solo lectura directa de bcv.org.ve) -->
            <div class="d-flex align-items-center gap-2">
                <div class="bcv-pill d-flex align-items-center gap-2" title="Tasa Oficial del Banco Central de Venezuela (bcv.org.ve)">
                    <i class="bi bi-currency-exchange text-warning"></i>
                    <span>$ USD: <strong id="nav-bcv-rate">Cargando...</strong></span>
                </div>
            </div>
        </div>
    </div>
</nav>
