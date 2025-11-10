<?php

/**
 * ================================================================
 * MotorAssistant - Descarga de Facturas PDF
 * ---------------------------------------------------------------
 * Este script actúa como endpoint público/privado para descargar
 * una factura previamente generada por el sistema.
 *
 * Flujo:
 * 1️ Recibe por GET el parámetro `archivo` (nombre del PDF)
 * 2️ Valida su existencia
 * 3️ Llama a GeneradorPDF::descargarFactura() para enviar headers
 * 4️ Transmite el archivo al navegador
 * ================================================================
 */

// Cargar clase de generación/descarga de PDF
require_once __DIR__ . '/../../factura/generadorPDF.php';

// Obtener nombre del archivo desde la query string
$archivo = $_GET['archivo'] ?? null;

// Validación parámetro obligatorio
if (!$archivo) {
    http_response_code(400);
    echo "Parámetro faltante.";
    exit;
}

/**
 * ================================================================
 * Descarga de la factura
 * ---------------------------------------------------------------
 * Llama al método estático que:
 * - Verifica existencia del archivo en /facturas/
 * - Envía los headers HTTP correctos
 * - Lee el archivo y finaliza el script con `readfile()`
 * ================================================================
 */
GeneradorPDF::descargarFactura($archivo);
