<?php
// Sidebar reutilizable
if (!isset($_SESSION)) {
    session_start();
}
require_once 'config.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
        --sidebar-bg-dark: #0f1419;
        --sidebar-bg-light: #f8f9fa;
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: #1a1a1a;
        color: #fff;
        overflow-y: auto;
        transition: width 0.3s ease;
        z-index: 1000;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    }

    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    [data-bs-theme="dark"] .sidebar {
        background: #0d0d0d;
    }

    [data-bs-theme="light"] .sidebar {
        background: #f8f9fa;
        color: #333;
        border-right: 1px solid #dee2e6;
    }

    /* Logo */
    .sidebar-header {
        padding: 20px 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 70px;
    }

    [data-bs-theme="light"] .sidebar-header {
        border-bottom-color: #dee2e6;
    }

    .sidebar-logo {
        font-size: 20px;
        font-weight: bold;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar.collapsed .sidebar-logo {
        display: none;
    }

    .sidebar-toggle {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        font-size: 18px;
        padding: 0;
        display: flex;
        align-items: center;
    }

    /* Menu items */
    .sidebar-nav {
        list-style: none;
        padding: 15px 0;
        margin: 0;
    }

    .sidebar-nav-item {
        margin: 5px 0;
    }

    .sidebar-nav-link {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 15px;
        color: inherit;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
    }

    [data-bs-theme="dark"] .sidebar-nav-link {
        color: #ddd;
    }

    [data-bs-theme="dark"] .sidebar-nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        border-left-color: #0d6efd;
    }

    [data-bs-theme="light"] .sidebar-nav-link {
        color: #666;
    }

    [data-bs-theme="light"] .sidebar-nav-link:hover {
        background-color: #e9ecef;
        border-left-color: #0d6efd;
    }

    .sidebar-nav-link.active {
        background-color: rgba(13, 110, 253, 0.2);
        color: #0d6efd;
        border-left-color: #0d6efd;
        font-weight: 600;
    }

    .sidebar-nav-icon {
        min-width: 30px;
        text-align: center;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-nav-icon .bi {
        font-size: 1.2rem;
        color: inherit;
    }

    [data-bs-theme="dark"] .sidebar-nav-link .bi {
        color: #b0b8c0;
    }

    [data-bs-theme="dark"] .sidebar-nav-link:hover .bi {
        color: #e9ecef;
    }

    [data-bs-theme="dark"] .sidebar-nav-link.active .bi {
        color: #0d6efd;
    }

    [data-bs-theme="light"] .sidebar-nav-link .bi {
        color: #495057;
    }

    [data-bs-theme="light"] .sidebar-nav-link:hover .bi {
        color: #212529;
    }

    [data-bs-theme="light"] .sidebar-nav-link.active .bi {
        color: #0d6efd;
    }

    .sidebar-nav-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar.collapsed .sidebar-nav-text {
        display: none;
    }

    /* Separador */
    .sidebar-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 10px 0;
    }

    [data-bs-theme="light"] .sidebar-divider {
        background: #dee2e6;
    }

    /* Sección de usuario */
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        width: 100%;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 15px;
    }

    [data-bs-theme="light"] .sidebar-footer {
        border-top-color: #dee2e6;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 10px;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar.collapsed .user-name {
        display: none;
    }

    .user-role {
        font-size: 11px;
        opacity: 0.8;
    }

    /* Main content */
    body {
        margin-left: var(--sidebar-width);
        transition: margin-left 0.3s ease;
    }

    body.sidebar-collapsed {
        margin-left: var(--sidebar-collapsed-width);
    }

    /* Responsive */
    @media (max-width: 768px) {
        :root {
            --sidebar-width: 70px;
            --sidebar-collapsed-width: 0px;
        }

        .sidebar {
            width: 70px;
        }

        .sidebar-header {
            justify-content: center;
        }

        .sidebar-toggle {
            display: none;
        }

        body {
            margin-left: 70px;
        }
    }
</style>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">Mi App</div>
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    </div>

    <ul class="sidebar-nav">
        <li class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php" title="Dashboard">
                <span class="sidebar-nav-icon"><i class="bi bi-house"></i></span>
                <span class="sidebar-nav-text">Dashboard</span>
            </a>
        </li>

        <?php 
        $permisos = ['tisupport', 'admin'];
        if (in_array($_SESSION["role"] ?? "viewer", $permisos)): 
        ?>
        <li class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo (basename($_SERVER['PHP_SELF']) === 'tickets.php') ? 'active' : ''; ?>" href="tickets.php" title="Tickets">
                <span class="sidebar-nav-icon"><i class="bi bi-ticket-detailed"></i></span>
                <span class="sidebar-nav-text">Tickets</span>
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo (basename($_SERVER['PHP_SELF']) === 'activos.php') ? 'active' : ''; ?>" href="activos.php" title="Activos">
                <span class="sidebar-nav-icon"><i class="bi bi-box"></i></span>
                <span class="sidebar-nav-text">Activos</span>
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo (basename($_SERVER['PHP_SELF']) === 'reportes.php') ? 'active' : ''; ?>" href="reportes.php" title="Reportes">
                <span class="sidebar-nav-icon"><i class="bi bi-bar-chart"></i></span>
                <span class="sidebar-nav-text">Reportes</span>
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo (basename($_SERVER['PHP_SELF']) === 'mi_contrasena.php') ? 'active' : ''; ?>" href="mi_contrasena.php" title="Mi Contraseña">
                <span class="sidebar-nav-icon"><i class="bi bi-key"></i></span>
                <span class="sidebar-nav-text">Mi Contraseña</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
        <li class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo (basename($_SERVER['PHP_SELF']) === 'usuarios.php') ? 'active' : ''; ?>" href="usuarios.php" title="Usuarios">
                <span class="sidebar-nav-icon"><i class="bi bi-people"></i></span>
                <span class="sidebar-nav-text">Usuarios</span>
            </a>
        </li>
        <?php endif; ?>

        <div class="sidebar-divider"></div>

        <li class="sidebar-nav-item">
            <button class="sidebar-nav-link" onclick="toggleDarkMode()" title="Cambiar tema" style="border: none; cursor: pointer; width: 100%; text-align: left; background: none;">
                <span class="sidebar-nav-icon" id="themeIcon"><i class="bi bi-moon"></i></span>
                <span class="sidebar-nav-text" id="themeText">Oscuro</span>
            </button>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-name" title="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
                <?php echo htmlspecialchars($_SESSION["username"] ?? ""); ?>
            </div>
            <div class="user-role">
                <?php echo traducirRol($_SESSION["role"] ?? "viewer"); ?>
            </div>
        </div>
        <a href="index.php?logout=1" class="sidebar-nav-link" style="border: none;" title="Cerrar sesión">
            <span class="sidebar-nav-icon"><i class="bi bi-box-arrow-right"></i></span>
            <span class="sidebar-nav-text">Salir</span>
        </a>
    </div>
</aside>

<script>
    // Toggle sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }

    // Cargar estado del sidebar al cargar la página
    window.addEventListener('DOMContentLoaded', function() {
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        const sidebar = document.getElementById('sidebar');
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }

        // Actualizar tema al cargar
        updateThemeIcon();
    });

    // Dark mode functions
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
        updateThemeIcon();
    }

    function disableDarkMode() {
        document.documentElement.removeAttribute('data-bs-theme');
        localStorage.setItem('darkMode', 'disabled');
        updateThemeIcon();
    }

    function updateThemeIcon() {
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');
        if (localStorage.getItem('darkMode') === 'enabled') {
            themeIcon.innerHTML = '<i class="bi bi-sun"></i>';
            themeText.textContent = 'Claro';
        } else {
            themeIcon.innerHTML = '<i class="bi bi-moon"></i>';
            themeText.textContent = 'Oscuro';
        }
    }

    // Inicializar modo oscuro
    initDarkMode();

    // Recargar página al presionar atrás
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            location.reload();
        }
    });
</script>
