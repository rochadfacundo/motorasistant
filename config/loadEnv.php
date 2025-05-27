<?php
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("El archivo .env no existe en: $filePath");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar líneas vacías y comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Asegurar que la línea tiene un "="
        if (!str_contains($line, '=')) {
            continue;
        }

        // Dividir clave y valor (solo la primera aparición del "=")
        list($key, $value) = explode('=', $line, 2);

        // Limpiar espacios y comillas
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"");

        // Establecer en variables de entorno
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
?>
