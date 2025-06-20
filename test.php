<?php
require_once __DIR__ . '/utils/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = DB::getConnection();
    $stmt = $pdo->query("SELECT * FROM cliente");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$clientes) {
        echo "⚠️ No hay clientes registrados.";
    } else {
        echo "<h2>📋 Lista de clientes:</h2><table border='1' cellpadding='8'>";
        echo "<tr>";
        foreach (array_keys($clientes[0]) as $col) {
            echo "<th>$col</th>";
        }
        echo "</tr>";

        foreach ($clientes as $cliente) {
            echo "<tr>";
            foreach ($cliente as $valor) {
                echo "<td>" . htmlspecialchars($valor) . "</td>";
            }
            echo "</tr>";
        }

        echo "</table>";
    }
} catch (PDOException $e) {
    echo "❌ Error al obtener los clientes: " . $e->getMessage();
}
