<?php
/**
 * ================================================================
 * MotorAssistant - Pago Pendiente
 * ---------------------------------------------------------------
 * Esta vista se muestra cuando Mercado Pago aún no ha confirmado 
 * el pago (por ejemplo, transferencias o medios offline).
 *
 * Flujo:
 * 1️ Llega con parámetros GET (payment_id, external_reference)
 * 2️ Muestra mensaje informativo de estado pendiente
 * 3️ Permite al usuario volver al sitio o consultar el estado luego
 * ================================================================
 */

$pageTitle = "Pago pendiente";


// Carga de layout principal
require '../head.php';
?>


<body class="d-flex flex-column min-vh-100">
<?php require '../header.php'; ?>

<main class="container my-5 flex-grow-1">
<?php
$data = $_GET;
?>

<!-- ================================================================
     MENSAJE DE ESTADO PENDIENTE
     --------------------------------------------------------------- -->
<div class="card shadow mx-auto text-center" style="max-width: 600px;">
    <div class="card-body p-5">
        <div class="mb-4 text-warning">
            <i class="bi bi-hourglass-split" style="font-size: 3rem;"></i>
        </div>
        <h3 class="card-title text-warning">Pago pendiente</h3>
        <p class="card-text mt-3">
            Tu pago está en proceso y aún no fue confirmado.<br>
            Nº de operación: <strong><?= htmlspecialchars($data['payment_id'] ?? '—') ?></strong><br>
            Referencia: <strong><?= htmlspecialchars($data['external_reference'] ?? '—') ?></strong>
        </p>
        <a href="#" class="btn btn-outline-warning mt-4">← Volver</a>
    </div>
</div>
</main>

<?php require '../footer.php'; ?>
</body>
</html>