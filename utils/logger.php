<?php
 /**
 * ================================================================
 * MotorAssistant - Logger de eventos del sistema
 * ---------------------------------------------------------------
 * Centraliza el registro de logs en el archivo:
 *   /logs/webhook.log
 * ================================================================
 */

class Logger {

     /**
     * Registra un mensaje en el archivo de log principal.
     *
     * @param string $mensaje Mensaje a registrar (ya formateado)
     * @return void
     */
    public static function logWebhook(string $mensaje): void {
        $logPath = __DIR__ . '/../logs/webhook.log';
        $timestamp = date('c'); // Formato ISO 8601 (ej: 2025-11-10T18:33:12-03:00)
        $linea = "{$timestamp} {$mensaje}\n";

        // Intenta crear el directorio de logs si no existe
        $dirLogs = dirname($logPath);
        if (!is_dir($dirLogs)) {
            mkdir($dirLogs, 0775, true);
        }

        // Escribe línea en el archivo de log
        file_put_contents($logPath, $linea, FILE_APPEND);
    }

}
