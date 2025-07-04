<?php
$pageTitle = "Pago rechazado";
require '../head.php';
?>

<body class="d-flex flex-column min-vh-100">
<?php require '../header.php'; ?>

<main class="container my-5 flex-grow-1">
<?php
$data = $_GET;
?>

<div class="card shadow mx-auto text-center" style="max-width: 600px;">
    <div class="card-body p-5">
        <div class="mb-4 text-danger">
            <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
        </div>
        <h3 class="card-title text-danger">Pago rechazado</h3>
        <p class="card-text mt-3">
            Lamentablemente el pago no pudo procesarse.<br>
            Te sugerimos verificar los datos e intentar nuevamente.
        </p>
        <p class="text-muted small">
            Nº de intento: <strong><?= htmlspecialchars($data['payment_id'] ?? '—') ?></strong><br>
            Referencia: <strong><?= htmlspecialchars($data['external_reference'] ?? '—') ?></strong>
        </p>
        <a href="#" class="btn btn-outline-danger mt-4">← Volver</a>
    </div>
</div>
</main>

<?php require '../footer.php'; ?>
</body>
</html>
