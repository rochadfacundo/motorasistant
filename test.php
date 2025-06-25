<?php
require_once __DIR__ . '/utils/db.php';

try {
    $pdo = DB::getConnection();

    $stmt = $pdo->prepare("CALL insertarFactura(
        :pPaymentId,
        :pNumeroFactura,
        :pCAE,
        :pPdfPath,
        :pTipoComprobante,
        :pPuntoVenta,
        :pImporte
    )");

    // Datos ficticios
    $paymentId = 'FAKE123456';
    $numeroFactura = 1001;
    $cae = '12345678901234';
    $pdfPath = 'facturas/factura_fake.pdf';
    $tipoComprobante = 'FA';
    $puntoVenta = 1;
    $importe = 12345.67;

    $stmt->bindParam(':pPaymentId', $paymentId);
    $stmt->bindParam(':pNumeroFactura', $numeroFactura);
    $stmt->bindParam(':pCAE', $cae);
    $stmt->bindParam(':pPdfPath', $pdfPath);
    $stmt->bindParam(':pTipoComprobante', $tipoComprobante);
    $stmt->bindParam(':pPuntoVenta', $puntoVenta);
    $stmt->bindParam(':pImporte', $importe);

    $stmt->execute();

    echo "✅ Factura ficticia insertada correctamente.";
} catch (PDOException $e) {
    echo "❌ Error al insertar factura: " . $e->getMessage();
}
