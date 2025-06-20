<?php
use Dompdf\Dompdf;

class GeneradorPDF {
    public static function crearFacturaPDF(array $datos): string {
        $html = file_get_contents(__DIR__ . '/plantilla.html');
        $html = str_replace(
            ['Juan Pérez', '10.000,00'],
            [$datos['nombre'] . ' ' . $datos['apellido'], number_format($datos['monto'], 2, ',', '')],
            $html
        );

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfPath = __DIR__ . '/../facturas/factura-' . time() . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        return $pdfPath;
    }
}
