<?php
declare(strict_types=1);

namespace CouncilLibrary\Core;

class Logger
{
    private string $logPath;

    public function __construct(string $channel = 'council-library', ?string $logPath = null)
    {
        $this->logPath = $logPath ?? dirname(__DIR__, 2) . '/logs/api.log';
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context
        ];
        @file_put_contents($this->logPath, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }
}

