<?php
require_once __DIR__ . '/../../factura/generadorPDF.php';


$archivo = $_GET['archivo'] ?? null;

if (!$archivo) {
    http_response_code(400);
    echo "Parámetro faltante.";
    exit;
}

GeneradorPDF::descargarFactura($archivo);
