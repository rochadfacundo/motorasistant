<?php
 /**
 * ================================================================
 * MotorAssistant - Servicio de Generación de QR AFIP
 * ---------------------------------------------------------------
 * Este servicio genera la URL oficial de AFIP para incluir el 
 * código QR dentro de las facturas electrónicas. 
 *
 * Formato AFIP:
 *  https://www.afip.gob.ar/fe/qr/?p=<base64_json>
 *
 * El JSON codificado contiene los datos mínimos requeridos 
 * para verificar la validez del comprobante en línea.
 * ================================================================
 */

class QrService {


    /**
     * ================================================================
     * generarUrlQrAfip($params)
     * ---------------------------------------------------------------
     * Genera una URL con el formato oficial de AFIP para el QR que
     * se incluye en las facturas electrónicas.
     *
     * Estructura esperada de $params:
     *  - cuit: CUIT del emisor (ej. 30718607961)
     *  - ptoVta: Punto de venta (ej. 3)
     *  - tipoCmp: Código de tipo de comprobante (ej. 1 = Factura A)
     *  - nroCmp: Número del comprobante
     *  - importe: Monto total de la factura
     *  - cae: Código de Autorización Electrónico (CAE)
     *
     * Retorna:
     *  - string URL completa lista para insertar en el HTML o PDF
     * ================================================================
     */
    public static function generarUrlQrAfip(array $params): string {

         /**
         * ================================================================
         *  estructura del JSON oficial exigido por AFIP
         * ---------------------------------------------------------------
         * Ejemplo (según RG 4892/2020):
         * {
         *   "ver":1,
         *   "fecha":"2025-11-10",
         *   "cuit":30718607961,
         *   "ptoVta":3,
         *   "tipoCmp":1,
         *   "nroCmp":123,
         *   "importe":1000.00,
         *   "moneda":"PES",
         *   "ctz":1,
         *   "tipoDocRec":99,
         *   "nroDocRec":0,
         *   "tipoCodAut":"E",
         *   "codAut":"71234567890123"
         * }
         * ================================================================
         */
        $data = [
            "ver" => 1, // Versión del formato QR AFIP
            "fecha" => date('Y-m-d'), // Fecha de emisión (AAAA-MM-DD)
            "cuit" => (int)$params['cuit'], // CUIT emisor
            "ptoVta" => (int)$params['ptoVta'], // Punto de venta
            "tipoCmp" => (int)$params['tipoCmp'], // Tipo comprobante (Factura A=1, B=6, etc.)
            "nroCmp" => (int)$params['nroCmp'], // Número del comprobante
            "importe" => round($params['importe'], 2), // Total factura
            "moneda" => "PES", // Moneda = Pesos Argentinos
            "ctz" => 1, // Cotización (1 = moneda nacional)
            "tipoDocRec" => 99, // Tipo documento receptor (99 = consumidor final)
            "nroDocRec" => 0, // N° doc. receptor (0 si es CF)
            "tipoCodAut" => "E", // "E" = CAE, "A" = CAEA
            "codAut" => (string)$params['cae'] // CAE emitido por AFIP
        ];

        // depuro
        Logger::logWebhook("📤 JSON para QR:\n" . json_encode($data, JSON_PRETTY_PRINT));

         /**
         * ================================================================
         * Codificación Base64
         * ---------------------------------------------------------------
         * AFIP requiere que el JSON se codifique en Base64 y se pase
         * como query param "p" en la URL oficial.
         * ================================================================
         */
        $base64 = base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES));

        // URL final para insertar en el PDF
        return "https://www.afip.gob.ar/fe/qr/?p=" . $base64;
    }
}
