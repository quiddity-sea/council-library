<?php
declare(strict_types=1);

namespace CouncilLibrary\Core;

class Autoloader
{
    public static function register(): void
    {
        require_once __DIR__ . '/PsrHttp.php';

        spl_autoload_register(function (string $class): void {
            $prefix = 'CouncilLibrary\\';
            $baseDir = dirname(__DIR__) . '/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}

