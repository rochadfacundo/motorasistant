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

        $html = str_replace(
            ['<!--NOMBRE_CLIENTE-->', '<!--EMAIL-->', '<!--MONTO-->', '<!--CAE-->', '<!--FECHA_CAE-->', '<!--TIPO_FACTURA-->', '<!--NRO_FACTURA-->'],
            [
                $datos['nombre'] . ' ' . $datos['apellido'],
                $datos['email'],
                number_format($datos['monto'], 2, ',', ''),
                $datos['cae'] ?? 'N/D',
                $datos['fecha_vencimiento_cae'] ?? 'N/D',
                $datos['tipo_factura'] ?? 'Desconocido',
                $datos['nro_factura'] ?? 'N/D'
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

        return $pdfPath;
    }
}
