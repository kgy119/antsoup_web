<?php
// env.php

class Env {
    private static $loaded = false;

    public static function load($filePath = __DIR__ . '/../.env') {
        if (self::$loaded || !file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, "\"' ");

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        self::load();  // ensure env is loaded once
        return $_ENV[$key] ?? $default;
    }
}
?>