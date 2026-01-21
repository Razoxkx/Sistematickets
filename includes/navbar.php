<?php
// Navbar reutilizable
if (!isset($_SESSION)) {
    session_start();
}
require_once 'config.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark" id="navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Mi App</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Home</a>
                </li>
                <?php 
                $permisos = ['tisupport', 'admin'];
                if (in_array($_SESSION["role"] ?? "viewer", $permisos)): 
                ?>
                <li class="nav-item">
                    <a class="nav-link" href="tickets.php">Tickets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="register.php">Agregar Usuario</a>
                </li>
                <?php endif; ?>
                
                <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
                <li class="nav-item">
                    <a class="nav-link" href="usuarios.php">Gestionar Usuarios</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <button class="btn btn-outline-light btn-sm me-2" id="darkModeToggle" onclick="toggleDarkMode()">
                        🌙 Oscuro
                    </button>
                </li>
                <li class="nav-item">
                    <span class="navbar-text me-3">
                        <strong><?php echo htmlspecialchars($_SESSION["username"] ?? ""); ?></strong>
                        <span class="badge bg-info"><?php echo traducirRol($_SESSION["role"] ?? "viewer"); ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-danger btn-sm text-white" href="index.php?logout=1">Cerrar Sesión</a>
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
        document.getElementById('darkModeToggle').textContent = '☀️ Claro';
    }

    function disableDarkMode() {
        document.documentElement.removeAttribute('data-bs-theme');
        localStorage.setItem('darkMode', 'disabled');
        document.getElementById('darkModeToggle').textContent = '🌙 Oscuro';
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
