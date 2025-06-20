<?php
require_once __DIR__ . '/../utils/afipUtils.php';
require_once __DIR__ . '/../factura/GeneradorPDF.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/db.php';

class FacturaService {
    public static function yaFueFacturado($paymentId): bool {
        $logPath = __DIR__ . '/../logs/pagos.log';
        if (!file_exists($logPath)) return false;
        return str_contains(file_get_contents($logPath), (string)$paymentId);
    }

    public static function generarYGuardarFactura($pago): void {
        prepararAutenticacionAfip();
        /*
        $linea = date('c') . " - ✅ {$pago->id} - {$pago->status} - {$pago->transaction_amount} - {$pago->payer->email}\n";
        file_put_contents(__DIR__ . '/../logs/pagos.log', $linea, FILE_APPEND);

        $datos = [
            'nombre' => $pago->payer->name ?? '',
            'apellido' => $pago->payer->surname ?? '',
            'email' => $pago->payer->email ?? '',
            'monto' => $pago->transaction_amount
        ];

        // Genera el PDF
        $pdfPath = GeneradorPDF::crearFacturaPDF($datos);
        Logger::logWebhook("✅ Factura generada correctamente en: $pdfPath");

        // Datos de la respuesta AFIP (ejemplo: desde afipUtils.php o como sea que devuelvas la autorización)
        $afipResponse = obtenerDatosFactura(); // Ajustalo si ya lo tenés como retorno

        $numeroFactura = $afipResponse['numero'];
        $cae = $afipResponse['cae'];
        $tipoComprobante = $afipResponse['tipo'];
        $puntoVenta = $afipResponse['ptoVta'];
        $importe = $pago->transaction_amount;

        // Insertar factura a la base de datos usando Store Procedure
        try {
            $pdo = DB::getConnection();
            $stmt = $pdo->prepare("CALL insertarFactura(
                :pPaymentId, :pNumeroFactura, :pCAE, :pPdfPath, :pTipoComprobante, :pPuntoVenta, :pImporte
            )");

            $stmt->bindParam(':pPaymentId', $pago->id);
            $stmt->bindParam(':pNumeroFactura', $numeroFactura);
            $stmt->bindParam(':pCAE', $cae);
            $stmt->bindParam(':pPdfPath', $pdfPath);
            $stmt->bindParam(':pTipoComprobante', $tipoComprobante);
            $stmt->bindParam(':pPuntoVenta', $puntoVenta);
            $stmt->bindParam(':pImporte', $importe);

            $stmt->execute();
            Logger::logWebhook("✅ Factura guardada en base de datos.");
        } catch (PDOException $e) {
            Logger::logWebhook("❌ Error al guardar la factura: " . $e->getMessage());
        }
            */
    }
}
