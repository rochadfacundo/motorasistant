<?php
/**
 * ================================================================
 * MotorAssistant - Clase de conexión a base de datos
 * ---------------------------------------------------------------
 * Centraliza la conexión a la base de datos MySQL mediante PDO.
 * Se utiliza en todos los servicios del sistema:
 *  - PagoService
 *  - FacturaService
 *  - PreferenciaService
 * 
 * Las credenciales se cargan dinámicamente desde `.env.db`.
 * ================================================================
 */
require_once __DIR__ . '/loadEnv.php';

class DB {

    /**
     * ================================================================
     * 🔌 getConnection()
     * ---------------------------------------------------------------
     * Crea y devuelve una instancia PDO configurada para conexión MySQL.
     *
     * @return PDO
     * ================================================================
     */
    public static function getConnection(): PDO {
        // Cargar variables de entorno
        loadEnv(__DIR__ . '/../.env.db');

        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');

        // Crear conexión PDO con opciones de seguridad
        return new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
}
