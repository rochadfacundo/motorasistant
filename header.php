<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Llega un admin
if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = [
        'id' => 1,
        'nombre' => 'Admin Test',
        'email' => 'admin@motorasistant.com',
        'rol' => 'administrador'
    ];
}

// URL base absoluta desde raíz del dominio
$baseUrl = '/motorasistant/';

?>

<nav class="navbar navbar-expand-lg" style="background-color: #f8d7da; color: #dc3545;">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= $baseUrl ?>public/index.php" style="color: #dc3545;">
        <img src="/assets/logo.png" alt="MotorAssistant Logo" style="height: 50px; margin-right: 10px;">
            <strong>MotorAssistant</strong>
        </a>

        <button class="navbar-toggler text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['usuario'])): ?>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="<?= $baseUrl ?>views/dashboard/dashboard-<?= $_SESSION['usuario']['rol']; ?>.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <?php if ($_SESSION['usuario']['rol'] === 'administrador'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="<?= $baseUrl ?>views/dashboard/usuarios.php">
                                <i class="bi bi-people"></i> Gestionar Usuarios
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="<?= $baseUrl ?>views/auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="<?= $baseUrl ?>views/auth/login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
