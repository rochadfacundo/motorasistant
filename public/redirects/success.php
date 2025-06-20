<?php
require_once __DIR__ . '/../../utils/db.php';
require_once __DIR__ . '/../../head.php';
require_once __DIR__ . '/../../header.php';

$pageTitle = "Pago aprobado";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$data = $_GET;
$pagoExitoso = false;

try {
    $pdo = DB::getConnection();

    $stmt = $pdo->prepare("CALL insertarPago(
        :collection_id,
        :collection_status,
        :payment_id,
        :status,
        :external_reference,
        :payment_type,
        :merchant_order_id,
        :preference_id,
        :site_id,
        :processing_mode,
        :merchant_account_id
    )");

    $stmt->bindParam(':collection_id', $data['collection_id']);
    $stmt->bindParam(':collection_status', $data['collection_status']);
    $stmt->bindParam(':payment_id', $data['payment_id']);
    $stmt->bindParam(':status', $data['status']);
    $stmt->bindParam(':external_reference', $data['external_reference']);
    $stmt->bindParam(':payment_type', $data['payment_type']);
    $stmt->bindParam(':merchant_order_id', $data['merchant_order_id']);
    $stmt->bindParam(':preference_id', $data['preference_id']);
    $stmt->bindParam(':site_id', $data['site_id']);
    $stmt->bindParam(':processing_mode', $data['processing_mode']);
    $stmt->bindParam(':merchant_account_id', $data['merchant_account_id']);

    $stmt->execute();
    $pagoExitoso = true;
} catch (PDOException $e) {
    $errorMensaje = $e->getMessage();
}
?>

<body class="d-flex flex-column min-vh-100">
<main class="container my-5 flex-grow-1">
    <div class="card shadow mx-auto text-center" style="max-width: 600px;">
        <div class="card-body p-5">
            <?php if ($pagoExitoso): ?>
                <div class="mb-4 text-success">
                    <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                </div>
                <h3 class="card-title text-success">¡Pago aprobado!</h3>
                <p class="card-text mt-3">
                    El pago se ha registrado correctamente.<br>
                    Nº de pago: <strong><?= htmlspecialchars($data['payment_id']) ?></strong><br>
                    Referencia: <strong><?= htmlspecialchars($data['external_reference']) ?></strong>
                </p>
                <a href="/" class="btn btn-outline-success mt-4">← Volver</a>
            <?php else: ?>
                <div class="mb-4 text-danger">
                    <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                </div>
                <h3 class="card-title text-danger">Error al registrar el pago</h3>
                <p class="card-text"><?= htmlspecialchars($errorMensaje) ?></p>
                <a href="/" class="btn btn-outline-danger mt-4">← Volver</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../footer.php'; ?>
</body>
</html>
