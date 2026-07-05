<?php

require __DIR__ . '/../../vendor/autoload.php';

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LokiHandler extends AbstractProcessingHandler
{
    private string $endpoint;
    private array $labels;

    public function __construct(string $endpoint, array $labels = [], $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->endpoint = $endpoint;
        $this->labels = $labels;
    }

    protected function write(array $record): void
    {
        $streamPayload = [
            'streams' => [[
                'stream' => $this->labels,
                'values' => [[
                    (string) (int) (microtime(true) * 1000000000),
                    $record['formatted'],
                ]],
            ]],
        ];

        $payload = json_encode($streamPayload);
        if ($payload === false) {
            return;
        }

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ];

        $context = stream_context_create($options);
        @file_get_contents($this->endpoint, false, $context);
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
    }
}

$logEndpoint = $_ENV['LOKI_ENDPOINT'] ?? 'http://localhost:3100/loki/api/v1/push';
$appName = $_ENV['APP_NAME'] ?? 'uw-cybersec-asset-manager';
$environment = $_ENV['APP_ENV'] ?? 'development';

$logger = new Logger($appName);
$logger->pushHandler(new LokiHandler($logEndpoint, ['app' => $appName, 'env' => $environment], Logger::DEBUG));
$logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

if ($environment === 'development') {
    $logger->info('Logger initialized', ['endpoint' => $logEndpoint, 'app' => $appName, 'env' => $environment]);
}

function log_php_error(int $errno, string $errstr, string $errfile, int $errline): bool
{
    global $logger;
    if ($logger) {
        $logger->error($errstr, [
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline,
        ]);
    }

    return false;
}

function log_php_exception(Throwable $exception): void
{
    global $logger;
    if ($logger) {
        $logger->critical($exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

function logPasswordReveal(string $entryId): void
{
    global $logger;
    if ($logger) {
        $logger->warning('Password revealed to user', [
            'entry_id' => $entryId,
            'time' => date('c'),
        ]);
    }
}

set_error_handler('log_php_error');
set_exception_handler(function (Throwable $exception) {
    log_php_exception($exception);
    http_response_code(500);
    exit('A server error occurred and has been logged.');
});

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $entryId = $data['entry_id'] ?? 'unknown';
        logPasswordReveal((string) $entryId);
        http_response_code(204); // No content
        exit;
    }
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        global $logger;
        if ($logger) {
            $logger->critical($error['message'], [
                'type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
        }
    }
});
