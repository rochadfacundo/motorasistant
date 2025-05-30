<?php
require '../config/conexion.php';
require '../header.php';
?>

  <main class="flex-fill container my-5">

    <h2>Resultado del pago</h2>

    <?php
    // Mostrar los datos recibidos por GET (solo para debug, podés comentar en producción)
    echo "<pre>";
    print_r($_GET);
    echo "</pre>";

    $data = $_GET;

    try {
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

        echo "<h4 class='text-success'>✅ Pago registrado correctamente.</h4>";
    } catch (PDOException $e) {
        echo "<h4 class='text-danger'>❌ Error al registrar el pago:</h4>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
    ?>

  </main>

  <?php require '../footer.php'; ?>

