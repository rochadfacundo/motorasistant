<?php

/**
 * ================================================================
 * MotorAssistant - Servicio de Facturación AFIP + PDF
 * ---------------------------------------------------------------
 * Este servicio gestiona todo el ciclo de facturación despues de un pago:
 * 1️ Verifica si el pago ya fue facturado.
 * 2️ Obtiene los datos del pago y la preferencia asociada.
 * 3️ Calcula neto e IVA según el tipo de factura.
 * 4️ Llama al WSFE de AFIP para emitir el comprobante (CAE).
 * 5️ Genera el PDF de factura con código QR.
 * 6️ Guarda los datos en la base y copia el PDF al directorio público.
 * ================================================================
 */
require_once __DIR__ . '/../utils/afipUtils.php';
require_once __DIR__ . '/../factura/generadorPDF.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../services/qrService.php';
require_once __DIR__ . '/../services/preferenciaService.php';
require_once __DIR__ . '/../utils/fileUtils.php';


class FacturaService {


    /**
     * ================================================================
     * yaFueFacturado($paymentId)
     * ---------------------------------------------------------------
     * Verifica si un pago ya fue procesado previamente para evitar
     * generar facturas duplicadas.  
     * Usa el archivo de log `pagos.log` como registro histórico.
     *
     * @param string|int $paymentId ID del pago (MercadoPago)
     * @return bool Verdadero si ya fue registrado
     * ================================================================
     */
    public static function yaFueFacturado($paymentId): bool {

        $logPath = __DIR__ . '/../logs/pagos.log';
        if (!file_exists($logPath)) return false;

         // Busca el ID dentro del archivo de log (solo simple detección por texto por ahora - CAMBIAR)
        return str_contains(file_get_contents($logPath), (string)$paymentId);
    }

     /**
     * ================================================================
     *  generarYGuardarFactura($pago, $tipoFactura)
     * ---------------------------------------------------------------
     * Crea y guarda una factura electrónica asociada a un pago
     * de MercadoPago. Incluye generación de CAE, PDF y registro
     * en base de datos.
     *
     * @param object $pago Objeto de pago devuelto por la API de MP
     * @param string $tipoFactura Tipo de comprobante: A | B | C
     * ================================================================
     */
    public static function generarYGuardarFactura($pago, string $tipoFactura = 'B'): void {
        $linea = date('c') . " - ✅ {$pago->id} - {$pago->status} - {$pago->transaction_amount} - {$pago->payer->email}\n";
        
        //GUARDA LUEGO EN BD PAGOS
        file_put_contents(__DIR__ . '/../logs/pagos.log', $linea, FILE_APPEND);
    
        Logger::logWebhook("🔍 Buscando preferencia con ID: " . $pago->external_reference);
        $datosPreferencia = PreferenciaService::obtenerPorPreferenceId($pago->external_reference);
    
         /**
         * ================================================================
         * Determinar tipo y número de documento
         * ---------------------------------------------------------------
         * AFIP requiere DocTipo y DocNro:
         *  - Factura A → CUIT válido (DocTipo 80)
         *  - Factura B/C → Consumidor final (DocTipo 99)
         * ================================================================
         */
        if ($tipoFactura === 'A' && isset($datosPreferencia['cuit']) && preg_match('/^\d{11}$/', $datosPreferencia['cuit'])) {
            $docTipo = 80; // CUIT
            $docNro = $datosPreferencia['cuit'];
        } else 
        {
            $docTipo = 99; //Consumidor Final
            $docNro = 0; //DNI o acepta 0 porque no necesita crédito fiscal factura B o C
        }
    
        Logger::logWebhook("📌 DocTipo: $docTipo - DocNro: $docNro");
    

        /**
         * ================================================================
         * Cálculo de importes
         * ---------------------------------------------------------------
         * Si la factura es tipo A:
         *   Neto = Total / 1.21
         *   IVA  = Total - Neto
         * Si es B o C:
         *   Total incluye IVA, se muestra directo.
         * ================================================================
         * 
         * Ejemplo Total = 3.00:
         * - Neto  = 3.00 / 1.21 = 2.48
         * - IVA   = 3.00 - 2.48 = 0.52
         */  
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
    

         /**
         * ================================================================
         *  Llamada a AFIP (WSFEv1)
         * ---------------------------------------------------------------
         * Se usa la función obtenerDatosFactura() de afipUtils.php
         * para enviar los datos al WebService de AFIP y obtener
         * el CAE, número de comprobante, tipo, etc.
         * ================================================================
         */
        $afipResponse = obtenerDatosFactura($tipoFactura === 'A' ? $neto : $importeBruto, $tipoFactura, $docTipo, $docNro);
    
        // Validar respuesta de AFIP
        if (!isset($afipResponse['cae']) || $afipResponse['cae'] === null || $afipResponse['cae'] === 'ERROR') {
            Logger::logWebhook("❌ No se insertó en DB porque la factura no se generó correctamente. CAE: " . var_export($afipResponse['cae'], true));
            return;
        }
    
        Logger::logWebhook("🧪 Respuesta AFIP: " . json_encode($afipResponse));
    
        // Extraer datos relevantes
        $numeroFactura = $afipResponse['numero'];
        $cae = $afipResponse['cae'];
        $nroFormateado = $afipResponse['nroFormateado'];
        $tipoComprobante = $afipResponse['codigoTipo'];
        $puntoVenta = $afipResponse['ptoVta'];
    
        Logger::logWebhook("📤 Email obtenido de preferencia: " . ($datosPreferencia['email'] ?? 'NO DISPONIBLE'));
        Logger::logWebhook("🎯 CAE enviado al QR: " . $cae);
    
         /**
         * ================================================================
         *  Genera URL del QR (validación AFIP)
         * ---------------------------------------------------------------
         * Se genera la URL que AFIP exige en el QR del comprobante,
         * según RG 4892/2020. Luego será insertada en el PDF.
         * ================================================================
         */
        $qrUrl = QrService::generarUrlQrAfip([
            'cuit' => 30718607961,
            'ptoVta' => $puntoVenta,
            'tipoCmp' => $tipoComprobante,
            'nroCmp' => $numeroFactura,
            'importe' => $importeBruto,
            'cae' => $cae
        ]);
    
         /**
         * ================================================================
         * Prepara datos para PDF
         * ---------------------------------------------------------------
         * Arma los datos finales que serán pasados al generador
         * de PDF (plantilla, QR, CAE, importes, cliente, etc.)
         * ================================================================
         */
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
    
        // Genera el PDF a partir de la plantilla
        $pdfPath = GeneradorPDF::crearFacturaPDF($datos);
        Logger::logWebhook("✅ Factura generada correctamente en: $pdfPath");
    
        /**
         * ================================================================
         *  Guardar factura en la base de datos
         * ---------------------------------------------------------------
         * Usa un Stored Procedure insertarFactura para registrar:
         *  - ID de pago
         *  - Número de factura
         *  - CAE
         *  - Ruta del PDF
         *  - Tipo de comprobante, punto de venta e importe
         * ================================================================
         */
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
    
            // Copia factura a directorio público para poder descargarlo
            FileUtils::copiarFacturaAPublico($pdfPath);
    
        } catch (PDOException $e) {
            Logger::logWebhook("❌ Error al guardar la factura: " . $e->getMessage());
        }
    }
}
