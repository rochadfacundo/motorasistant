<?php
if (!isset($pageTitle)) {
    $pageTitle = 'MotorAssistant';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Estilos generales (si querés incluirlos) -->
    <style>
        #wallet_wrapper {
            max-width: 400px;
            margin: 0 auto;
        }

        #wallet_container {
            width: 100%;
        }
    </style>

    <!-- SDK MercadoPago -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>

