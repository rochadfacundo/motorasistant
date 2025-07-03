<?php
require_once __DIR__ . '/../utils/afipUtils.php';
require_once __DIR__ . '/../factura/generadorPDF.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../services/qrService.php';
require_once __DIR__ . '/../services/preferenciaService.php';
require_once __DIR__ . '/../utils/fileUtils.php';

class FacturaService {
    public static function yaFueFacturado($paymentId): bool {

    // Cuando el total ya incluye el IVA (como en un pago de MercadoPago), usamos esta lógica:
    //Fórmula:
    // - Neto  = Total / (1 + IVA)
    // - IVA   = Total - Neto
    // Eje Total = 3.00:
    // - Neto  = 3.00 / 1.21 = 2.48
    // - IVA   = 3.00 - 2.48 = 0.52
        $logPath = __DIR__ . '/../logs/pagos.log';
        if (!file_exists($logPath)) return false;
        return str_contains(file_get_contents($logPath), (string)$paymentId);
    }

    public static function generarYGuardarFactura($pago, string $tipoFactura = 'B'): void {

        $linea = date('c') . " - ✅ {$pago->id} - {$pago->status} - {$pago->transaction_amount} - {$pago->payer->email}\n";
        file_put_contents(__DIR__ . '/../logs/pagos.log', $linea, FILE_APPEND);
    
        Logger::logWebhook("🔍 Buscando preferencia con ID: " . $pago->external_reference);
        $datosPreferencia = PreferenciaService::obtenerPorPreferenceId($pago->external_reference);
    
        // Determinar DocTipo y DocNro
        if ($tipoFactura === 'A' && isset($datosPreferencia['cuit']) && preg_match('/^\d{11}$/', $datosPreferencia['cuit'])) {
            $docTipo = 80;
            $docNro = $datosPreferencia['cuit'];
        } else {
            $docTipo = 99;
            $docNro = 0;
        }
    
        Logger::logWebhook("📌 DocTipo: $docTipo - DocNro: $docNro");
    
        $importeBruto = $pago->transaction_amount;
        $neto = $importeBruto;
        $iva = 0;
    
        if ($tipoFactura === 'A') {
            $ivaTasa = 0.21;
            $neto = round($importeBruto / (1 + $ivaTasa), 2); 
            $iva = round($importeBruto - $neto, 2);            
            Logger::logWebhook("🧾 Factura A → Neto: $neto | IVA: $iva | Total: $importeBruto");
        } else {
            Logger::logWebhook("🧾 Factura B → Total final (IVA incluido): $importeBruto");
        }
    
        // Llamada a AFIP con el monto correcto (neto si es A)
        $afipResponse = obtenerDatosFactura($tipoFactura === 'A' ? $neto : $importeBruto, $tipoFactura, $docTipo, $docNro);
    
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
    
        Logger::logWebhook("📤 Email obtenido de preferencia: " . ($datosPreferencia['email'] ?? 'NO DISPONIBLE'));
        Logger::logWebhook("🎯 CAE enviado al QR: " . $cae);
    
        $qrUrl = QrService::generarUrlQrAfip([
            'cuit' => 30718607961,
            'ptoVta' => $puntoVenta,
            'tipoCmp' => $tipoComprobante,
            'nroCmp' => $numeroFactura,
            'importe' => $importeBruto,
            'cae' => $cae
        ]);
    
        $datos = [
            'nombre' => $datosPreferencia['nombre'] ?? '',
            'apellido' => $datosPreferencia['apellido'] ?? '',
            'email' => $datosPreferencia['email'] ?? '',
            'monto' => $importeBruto,
            'neto' => $neto,
            'iva' => $iva,
            'qrUrl' => $qrUrl,
            'cae' => $cae,
            'fecha_vencimiento_cae' => $afipResponse['fechaVencimientoCae'] ?? 'N/D',
            'tipo_factura' => $afipResponse['tipo'] ?? 'Desconocido',
            'nro_factura' => $nroFormateado
        ];
    
        $pdfPath = GeneradorPDF::crearFacturaPDF($datos);
        Logger::logWebhook("✅ Factura generada correctamente en: $pdfPath");
    
        // Guardar en base de datos
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
            $stmt->bindParam(':pImporte', $importeBruto);
    
            $stmt->execute();
            Logger::logWebhook("✅ Factura guardada en base de datos.");
    
            FileUtils::copiarFacturaAPublico($pdfPath);
    
        } catch (PDOException $e) {
            Logger::logWebhook("❌ Error al guardar la factura: " . $e->getMessage());
        }
    }
    
}
