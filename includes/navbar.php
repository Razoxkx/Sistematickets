<?php
// Navbar reutilizable
if (!isset($_SESSION)) {
    session_start();
}
require_once 'config.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark" id="navbar">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-ticket-detailed me-2" style="font-size: 1.5rem;"></i>
            <strong>Ticket Manager</strong>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house"></i> Home</a>
                </li>
                <?php 
                $permisos = ['tisupport', 'admin'];
                if (in_array($_SESSION["role"] ?? "viewer", $permisos)): 
                ?>
                <li class="nav-item">
                    <a class="nav-link" href="tickets.php"><i class="bi bi-ticket-detailed"></i> Tickets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="activos.php"><i class="bi bi-box"></i> Activos</a>
                </li>
                <?php endif; ?>
                
                <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
                <li class="nav-item">
                    <a class="nav-link" href="usuarios.php"><i class="bi bi-people"></i> Usuarios</a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($_SESSION["role"] ?? "viewer", $permisos)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="reportes.php"><i class="bi bi-bar-chart"></i> Reportes</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php 
                $permisos = ['tisupport', 'admin'];
                if (in_array($_SESSION["role"] ?? "viewer", $permisos)): 
                ?>
                <li class="nav-item">
                    <a class="nav-link" href="mi_contrasena.php"><i class="bi bi-key"></i> Mi Contraseña</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <button class="btn btn-outline-light btn-sm me-2" id="darkModeToggle" onclick="toggleDarkMode()">
                        <i class="bi bi-moon"></i> Oscuro
                    </button>
                </li>
                <li class="nav-item">
                    <span class="navbar-text me-3">
                        <strong><?php echo htmlspecialchars($_SESSION["username"] ?? ""); ?></strong>
                        <span class="badge bg-info"><?php echo traducirRol($_SESSION["role"] ?? "viewer"); ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-danger btn-sm text-white" href="index.php?logout=1"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
    // Inicializar modo oscuro
    function initDarkMode() {
        const darkMode = localStorage.getItem('darkMode');
        if (darkMode === 'enabled') {
            enableDarkMode();
        }
    }

    function toggleDarkMode() {
        if (localStorage.getItem('darkMode') === 'enabled') {
            disableDarkMode();
        } else {
            enableDarkMode();
        }
    }

    function enableDarkMode() {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        localStorage.setItem('darkMode', 'enabled');
        document.getElementById('darkModeToggle').innerHTML = '<i class="bi bi-sun"></i> Claro';
    }

    function disableDarkMode() {
        document.documentElement.removeAttribute('data-bs-theme');
        localStorage.setItem('darkMode', 'disabled');
        document.getElementById('darkModeToggle').innerHTML = '<i class="bi bi-moon"></i> Oscuro';
    }

    // Inicializar al cargar
    initDarkMode();

    // Recargar página al presionar atrás en el navegador
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            location.reload();
        }
    });
</script>
