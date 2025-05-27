<?php
require 'loadEnv.php';

try {
    // Cargar las variables de entorno desde .env
   loadEnv(__DIR__ . '/../.env');

    // Leer las variables de entorno
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
     
    // Crear una conexión PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Configurar PDO para lanzar excepciones en caso de errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Configurar PDO para no emular declaraciones preparadas
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Manejar errores de conexión
    die("Error de conexión: " . $e->getMessage());
}
?>
