<?php
require_once __DIR__ . '/../utils/afipUtils.php';
require_once __DIR__ . '/../factura/generadorPDF.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../services/qrService.php';
require_once __DIR__ . '/../services/preferenciaService.php';

class FacturaService {
    public static function yaFueFacturado($paymentId): bool {
        $logPath = __DIR__ . '/../logs/pagos.log';
        if (!file_exists($logPath)) return false;
        return str_contains(file_get_contents($logPath), (string)$paymentId);
    }

    public static function generarYGuardarFactura($pago, string $tipoFactura = 'B'): void {
        $linea = date('c') . " - ✅ {$pago->id} - {$pago->status} - {$pago->transaction_amount} - {$pago->payer->email}\n";
        file_put_contents(__DIR__ . '/../logs/pagos.log', $linea, FILE_APPEND);
    
        // Datos de la respuesta AFIP
        $afipResponse = obtenerDatosFactura($pago->transaction_amount, $tipoFactura);
    
        if (!isset($afipResponse['cae']) || $afipResponse['cae'] === null || $afipResponse['cae'] === 'ERROR') {
            Logger::logWebhook("❌ No se insertó en DB porque la factura no se generó correctamente. CAE: " . var_export($afipResponse['cae'], true));
            return;
        }
    
        Logger::logWebhook("🧪 Respuesta AFIP: " . json_encode($afipResponse));
    
        $numeroFactura = $afipResponse['numero'];
        $cae = $afipResponse['cae'];
        $nroFormateado = $afipResponse['nroFormateado'];
        $tipoComprobante = $afipResponse['codigoTipo'];
        $puntoVenta = $afipResponse['ptoVta'];
        $importe = $pago->transaction_amount;
    
        Logger::logWebhook("🎯 CAE enviado al QR: " . $cae);
    
        // Buscar los datos reales desde la base de datos
        Logger::logWebhook("🔍 Buscando preferencia con ID: " . $pago->external_reference);
        $datosPreferencia = PreferenciaService::obtenerPorPreferenceId($pago->external_reference);
        Logger::logWebhook("📤 Email obtenido de preferencia: " . ($datosPreferencia['email'] ?? 'NO DISPONIBLE'));

        $qrUrl = QrService::generarUrlQrAfip([
            'cuit' => 30718607961,
            'ptoVta' => $puntoVenta,
            'tipoCmp' => $tipoComprobante,
            'nroCmp' => $numeroFactura,
            'importe' => $importe,
            'cae' => $cae
        ]);
    
        $datos = [
            'nombre' => $datosPreferencia['nombre'] ?? '',
            'apellido' => $datosPreferencia['apellido'] ?? '',
            'email' => $datosPreferencia['email'] ?? '',
            'monto' => $importe,
            'qrUrl' => $qrUrl,
            'cae' => $cae,
            'fecha_vencimiento_cae' => $afipResponse['fechaVencimientoCae'] ?? 'N/D',
            'tipo_factura' => $afipResponse['tipo'] ?? 'Desconocido',
            'nro_factura' => $nroFormateado
        ];
    
        // Genera el PDF
        $pdfPath = GeneradorPDF::crearFacturaPDF($datos);
        Logger::logWebhook("✅ Factura generada correctamente en: $pdfPath");
    
        // Insertar en la base de datos
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
