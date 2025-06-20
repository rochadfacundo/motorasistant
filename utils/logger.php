<?php

class Logger {
    public static function logWebhook(string $mensaje): void {
        file_put_contents(__DIR__ . '/../logs/webhook.log', date('c') . " $mensaje\n", FILE_APPEND);
    }
}
