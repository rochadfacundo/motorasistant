<?php
function generarFactura(array $datos): array {
    //Simula la emisión del CAE.
    
    // Guardamos un log para saber que fue llamada
    file_put_contents(
        'webhook.log', 
        "generarFactura() llamada con: " . json_encode($datos) . "\n",
         FILE_APPEND
        );

    return [
        'cae' => 'CAE-SIMULADO-123456',
        'numero_factura' => '0001-00000001',
        'pdf_path' => 'facturas/factura-simulada.pdf'
    ];
}