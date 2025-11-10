<?php

 /**
 * ================================================================
 * MotorAssistant - Generador de Facturas PDF (AFIP + QR)
 * ---------------------------------------------------------------
 * Este módulo toma los datos de la factura electrónica generada 
 * (CAE, monto, cliente, tipo, QR, etc.) y produce un archivo PDF 
 * legible con formato profesional, listo para descarga o envío.
 * 
 * Usa:
 *  - Dompdf → para renderizar HTML en PDF
 *  - endroid/qr-code → para generar código QR AFIP
 * ================================================================
 */


use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class GeneradorPDF {

     /**
     * ================================================================
     * Genera un archivo PDF de factura a partir de los datos recibidos.
     * ---------------------------------------------------------------
     * @param array $datos  Datos necesarios para la factura:
     *  - nombre, apellido, email
     *  - tipo_factura (A, B, C)
     *  - monto, neto, iva
     *  - cae, fecha_vencimiento_cae, nro_factura
     *  - qrUrl (opcional)
     * 
     * @return string Ruta absoluta al PDF generado
     * @throws RuntimeException Si no puede generar o guardar el PDF
     * ================================================================
     */
    public static function crearFacturaPDF(array $datos): string {
        $plantilla = __DIR__ . '/plantilla.html';
        if (!file_exists($plantilla)) {
            Logger::logWebhook("❌ No se encontró plantilla HTML para generar factura.");
            throw new RuntimeException("No se encontró la plantilla HTML.");
        }

        // Cargar contenido HTML base
        $html = file_get_contents($plantilla);

        /**
         * ================================================================
         *  Generar tabla de detalle dinámico según tipo de factura
         * ---------------------------------------------------------------
         * En factura tipo A → se muestra desglose de neto + IVA + total
         * En factura tipo B o C → solo total del servicio
         * ================================================================
         */
        $detalleFactura = '';

        if (strtoupper($datos['tipo_factura']) === 'A' && isset($datos['neto'], $datos['iva'])) {
            
            // Factura A: con IVA discriminado
            $detalleFactura = "
                <table style='width: 100%; margin-top: 20px; font-size: 14px; border-collapse: collapse;'>
                    <thead>
                        <tr>
                            <th style='text-align: left; border: 1px solid #000;'>Descripción</th>
                            <th style='text-align: right; border: 1px solid #000;'>Cantidad</th>
                            <th style='text-align: right; border: 1px solid #000;'>Precio Unitario</th>
                            <th style='text-align: right; border: 1px solid #000;'>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style='border: 1px solid #000;'>Servicio de asistencia mecánica</td>
                            <td style='text-align: right; border: 1px solid #000;'>1</td>
                            <td style='text-align: right; border: 1px solid #000;'>$ " . number_format($datos['neto'], 2, ',', '') . "</td>
                            <td style='text-align: right; border: 1px solid #000;'>$ " . number_format($datos['neto'], 2, ',', '') . "</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='text-align: right; font-weight: bold; border: 1px solid #000;'>IVA (21%)</td>
                            <td style='text-align: right; border: 1px solid #000;'>$ " . number_format($datos['iva'], 2, ',', '') . "</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='text-align: right; font-weight: bold; border: 1px solid #000;'>Total</td>
                            <td style='text-align: right; border: 1px solid #000;'>$ " . number_format($datos['monto'], 2, ',', '') . "</td>
                        </tr>
                    </tbody>
                </table>
            ";
        } else {
            // Factura B o C: monto total sin desglose
            $detalleFactura = "
            <table style='width: 100%; margin-top: 20px; font-size: 14px; border-collapse: collapse;'>
                <thead>
                    <tr>
                        <th style='text-align: left; border: 1px solid #000;'>Descripción</th>
                        <th style='text-align: right; border: 1px solid #000;'>Cantidad</th>
                        <th style='text-align: right; border: 1px solid #000;'>Precio Unitario</th>
                        <th style='text-align: right; border: 1px solid #000;'>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style='border: 1px solid #000;'>Servicio de asistencia mecánica</td>
                        <td style='text-align: right; border: 1px solid #000;'>1</td>
                        <td style='text-align: right; border: 1px solid #000;'>$ " . number_format($datos['monto'], 2, ',', '') . "</td>
                        <td style='text-align: right; border: 1px solid #000;'>$ " . number_format($datos['monto'], 2, ',', '') . "</td>
                    </tr>
                </tbody>
            </table>
        ";
        }

        /**
         * ================================================================
         * Reemplaza placeholders del HTML con datos reales
         * ---------------------------------------------------------------
         * La plantilla HTML contiene tags comentados que 
         * se sustituyen con la información correspondiente.
         * ================================================================
         */
        $html = str_replace(
            ['<!--NOMBRE_CLIENTE-->', '<!--EMAIL-->', '<!--MONTO-->', '<!--CAE-->', '<!--FECHA_CAE-->', '<!--TIPO_FACTURA-->', '<!--NRO_FACTURA-->', '<!--DETALLE_FACTURA-->'],
            [
                $datos['nombre'] . ' ' . $datos['apellido'],
                $datos['email'],
                number_format($datos['monto'], 2, ',', ''),
                $datos['cae'] ?? 'N/D',
                $datos['fecha_vencimiento_cae'] ?? 'N/D',
                $datos['tipo_factura'] ?? 'Desconocido',
                $datos['nro_factura'] ?? 'N/D',
                $detalleFactura
            ],
            $html
        );

        /**
         * ================================================================
         * Generar código QR de AFIP (si viene qrUrl)
         * ---------------------------------------------------------------
         * AFIP provee una URL para validación pública del comprobante.
         * Se genera un QR embebido en base64 dentro del HTML.
         * ================================================================
         */
        if (!empty($datos['qrUrl'])) {
            $qrCode = new QrCode($datos['qrUrl']);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrBase64 = base64_encode($result->getString());

            // Construir bloque HTML con imagen del QR
            $qrImgTag = "
                <div style='text-align: center;'>
                    <img src='data:image/png;base64,{$qrBase64}' alt='QR AFIP' width='200'><br>
                    <div style='font-size: 10px; margin-top: 5px;'>
                        Escaneá este código para verificar la factura en el sitio de AFIP.
                    </div>
                </div>";
            $html = str_replace('<!--QR_CODE-->', $qrImgTag, $html);
        }

        /**
         * ================================================================
         * Renderización del PDF con Dompdf
         * ---------------------------------------------------------------
         * Convierte el HTML final en un PDF y lo guarda en /facturas/.
         * ================================================================
         */
        $dompdf = new Dompdf();
        $dompdf->getOptions()->set('isRemoteEnabled', true); // habilita imágenes externas
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nombre y ruta del archivo final
        $tipo = strtoupper($datos['tipo_factura'] ?? 'X');
        $nro = preg_replace('/[^0-9]/', '', $datos['nro_factura'] ?? '000000000000');

        $nombreArchivo = "factura-{$tipo}-{$nro}.pdf";
        $pdfPath = __DIR__ . "/../facturas/{$nombreArchivo}";

        // Guardar PDF generado
        if (!file_put_contents($pdfPath, $dompdf->output())) {
            Logger::logWebhook("❌ No se pudo guardar el PDF en $pdfPath");
            throw new RuntimeException("No se pudo guardar el PDF.");
        }

        // Ajustar permisos (lectura para servidor web)
        chmod($pdfPath, 0664);
        Logger::logWebhook("✅ Factura generada correctamente en: $pdfPath");

        return $pdfPath;
    }

    /**
     * ================================================================
     * Permite descargar una factura PDF existente desde el navegador.
     * ---------------------------------------------------------------
     * @param string $nombreArchivo Nombre del archivo PDF en /facturas/
     * ================================================================
     */
    public static function descargarFactura(string $nombreArchivo): void {
        $ruta = __DIR__ . '/../facturas/' . basename($nombreArchivo);

        // Validar existencia
        if (!file_exists($ruta)) {
            http_response_code(404);
            echo "Archivo no encontrado.";
            exit;
        }

        // Enviar headers para descarga
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}
