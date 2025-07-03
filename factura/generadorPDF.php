<?php
use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class GeneradorPDF {
    public static function crearFacturaPDF(array $datos): string {
        $plantilla = __DIR__ . '/plantilla.html';
        if (!file_exists($plantilla)) {
            Logger::logWebhook("❌ No se encontró plantilla HTML para generar factura.");
            throw new RuntimeException("No se encontró la plantilla HTML.");
        }

        $html = file_get_contents($plantilla);

        // Generar detalle dinámico según tipo de factura
        $detalleFactura = '';

        if (strtoupper($datos['tipo_factura']) === 'A' && isset($datos['neto'], $datos['iva'])) {
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

        // Reemplazos
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

        // QR
        if (!empty($datos['qrUrl'])) {
            $qrCode = new QrCode($datos['qrUrl']);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrBase64 = base64_encode($result->getString());

            $qrImgTag = "
                <div style='text-align: center;'>
                    <img src='data:image/png;base64,{$qrBase64}' alt='QR AFIP' width='200'><br>
                    <div style='font-size: 10px; margin-top: 5px;'>
                        Escaneá este código para verificar la factura en el sitio de AFIP.
                    </div>
                </div>";
            $html = str_replace('<!--QR_CODE-->', $qrImgTag, $html);
        }

        // Render PDF
        $dompdf = new Dompdf();
        $dompdf->getOptions()->set('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $tipo = strtoupper($datos['tipo_factura'] ?? 'X');
        $nro = preg_replace('/[^0-9]/', '', $datos['nro_factura'] ?? '000000000000');

        $nombreArchivo = "factura-{$tipo}-{$nro}.pdf";
        $pdfPath = __DIR__ . "/../facturas/{$nombreArchivo}";

        if (!file_put_contents($pdfPath, $dompdf->output())) {
            Logger::logWebhook("❌ No se pudo guardar el PDF en $pdfPath");
            throw new RuntimeException("No se pudo guardar el PDF.");
        }

        chmod($pdfPath, 0664);
        Logger::logWebhook("✅ Factura generada correctamente en: $pdfPath");

        return $pdfPath;
    }

    public static function descargarFactura(string $nombreArchivo): void {
        $ruta = __DIR__ . '/../facturas/' . basename($nombreArchivo);

        if (!file_exists($ruta)) {
            http_response_code(404);
            echo "Archivo no encontrado.";
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}
