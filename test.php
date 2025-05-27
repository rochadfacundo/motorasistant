<?php
require 'config/conexion.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datos simulados (deberían existir en la tabla `clientes`)
$id = 1;
$nombre = "Juan Pérez";
$email = "juanperez@example.com";

try {
    $stmt = $pdo->prepare("CALL actualizarCliente(:id, :nombre, :email)");
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    echo "Cliente actualizado correctamente.";
} catch (PDOException $e) {
    echo "Error al ejecutar el procedimiento: " . $e->getMessage();
}
