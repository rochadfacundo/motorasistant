<?php
// utils/db.php
require_once __DIR__ . '/loadEnv.php';

class DB {
    public static function getConnection(): PDO {
        loadEnv(__DIR__ . '/../.env.db');

        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');

        return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
}
