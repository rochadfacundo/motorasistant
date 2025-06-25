<?php
 use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class GeneradorPDF {
    public static function crearFacturaPDF(array $datos): string {
        $html = file_get_contents(__DIR__ . '/plantilla.html');

        $html = str_replace(
            ['Juan Pérez', '10.000,00', '<!--CAE-->', '<!--FECHA_CAE-->'],
            [
                $datos['nombre'] . ' ' . $datos['apellido'],
                number_format($datos['monto'], 2, ',', ''),
                $datos['cae'] ?? 'N/D',
                $datos['fecha_vencimiento_cae'] ?? 'N/D'
            ],
            $html
        );

        if (isset($datos['qrUrl']) && !empty($datos['qrUrl'])) {
            $qrCode = new QrCode($datos['qrUrl']);

            $writer = new PngWriter();
            $result = $writer->write(
                $qrCode,
                null,
                null,
                [
                    'size' => 500,
                    'margin' => 10,
                    'round_block_size' => true,
                    'error_correction_level' => 'low' // Opciones: low, medium, quartile, high
                ]
            );

            $qrBase64 = base64_encode($result->getString());

            $qrImgTag = "
                <div style='text-align: center;'>
                    <img src='data:image/png;base64,{$qrBase64}' alt='QR AFIP' width='120'><br>
                    <div style='font-size: 10px; margin-top: 5px;'>
                        Escaneá este código para verificar esta factura en el sitio de AFIP.
                    </div>
                </div>";

            $html = str_replace('<!--QR_CODE-->', $qrImgTag, $html);
        }

        $dompdf = new Dompdf();
        $dompdf->getOptions()->set('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfPath = __DIR__ . '/../facturas/factura-' . time() . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        return $pdfPath;
    }
}
