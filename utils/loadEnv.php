<?php
 /**
 * ================================================================
 * MotorAssistant - Cargador de variables de entorno (.env)
 * ---------------------------------------------------------------
 * Lee un archivo .env plano con formato clave=valor y las carga 
 * como variables de entorno disponibles vía getenv(), $_ENV y $_SERVER.
 *
 * Permite separar credenciales (DB, APIs, AFIP, MP, etc.) del código.
 * ================================================================
 */

function loadEnv(string $filePath): void {
    if (!file_exists($filePath)) {
        throw new Exception("❌ Archivo .env no encontrado en: $filePath");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios y líneas vacías
        if (str_starts_with(trim($line), '#') || trim($line) === '') {
            continue;
        }

        // Saltar líneas sin '='
        if (!str_contains($line, '=')) continue;

        // Separar clave y valor (solo primera aparición de '=')
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"");

        // Asignar en entorno
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
