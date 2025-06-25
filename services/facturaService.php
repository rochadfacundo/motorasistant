<?php
require_once __DIR__ . '/../utils/afipUtils.php';
require_once __DIR__ . '/../factura/generadorPDF.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/db.php';

class FacturaService {
    public static function yaFueFacturado($paymentId): bool {
        $logPath = __DIR__ . '/../logs/pagos.log';
        if (!file_exists($logPath)) return false;
        return str_contains(file_get_contents($logPath), (string)$paymentId);
    }

    public static function generarYGuardarFactura($pago): void {
       // prepararAutenticacionAfip();
        
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

        // Datos de la respuesta AFIP
        $afipResponse = obtenerDatosFactura($pago->transaction_amount);

        if ($afipResponse['cae'] === 'ERROR') {
            Logger::logWebhook("❌ No se insertó en DB porque la factura no se generó correctamente.");
            return;
        }

        $numeroFactura = $afipResponse['numero'];
        $cae = $afipResponse['cae'];
        $nroFormateado = $afipResponse['nroFormateado'];
        $tipoComprobante = $afipResponse['tipo'];
        $puntoVenta = $afipResponse['ptoVta'];
        $importe = $pago->transaction_amount;

        // Insertar factura a la base de datos Store Procedure
        try {
            $pdo = DB::getConnection();
            $stmt = $pdo->prepare("CALL insertarFactura(
                :pPaymentId, :pNumeroFactura, :pNroFormateado, :pCAE, :pPdfPath, :pTipoComprobante, :pPuntoVenta, :pImporte
            )");

            $stmt->bindParam(':pPaymentId', $pago->id);
            $stmt->bindParam(':pNumeroFactura', $numeroFactura, PDO::PARAM_INT);
            $stmt->bindParam(':pCAE', $cae);
            $stmt->bindParam(':pNroFormateado', $nroFormateado);
            $stmt->bindParam(':pPdfPath', $pdfPath);
            $stmt->bindParam(':pTipoComprobante', $tipoComprobante);
            $stmt->bindParam(':pPuntoVenta', $puntoVenta);
            $stmt->bindParam(':pImporte', $importe);

            $stmt->execute();
            Logger::logWebhook("✅ Factura guardada en base de datos.");
        } catch (PDOException $e) {
            Logger::logWebhook("❌ Error al guardar la factura: " . $e->getMessage());
        }
            
    }
}
