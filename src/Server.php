<?php

/*
 * server-handler-swoole (https://github.com/juliangut/server-handler-swoole).
 * Swoole with PSR-15.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/server-handler-swoole
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\ServerHandler\Swoole;

use Jgut\ServerHandler\Swoole\Http\PsrRequestFactoryInterface;
use Jgut\ServerHandler\Swoole\Http\SwooleResponseFactoryInterface;
use Jgut\ServerHandler\Swoole\Reloader\ReloaderInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareTrait;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Server as SwooleServer;

class Server
{
    use LoggerAwareTrait;

    private const DEFAULT_PROCESS_NAME = 'swoole-server';

    /**
     * @var SwooleServer
     */
    private $server;

    /**
     * @var RequestHandlerInterface
     */
    private $requestHandler;

    /**
     * @var PsrRequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var SwooleResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var ReloaderInterface|null
     */
    private $reloader;

    /**
     * @var string
     */
    private $processName;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var string
     */
    private $cwd;

    /**
     * List of server events.
     *
     * @var string[]
     */
    protected $serverEvents = [
        'start',
        'shutDown',
        'managerStart',
        'managerStop',
        'workerStart',
        'workerStop',
        'workerError',
        'request',
        'packet',
        'bufferFull',
        'bufferEmpty',
        'task',
        'finish',
        'pipeMessage',
    ];

    /**
     * Server constructor.
     *
     * @param SwooleServer                   $server
     * @param RequestHandlerInterface        $requestHandler
     * @param PsrRequestFactoryInterface     $requestFactory
     * @param SwooleResponseFactoryInterface $responseFactory
     * @param ReloaderInterface|null         $reloader
     * @param string|null                    $processName
     * @param bool                           $debug
     */
    public function __construct(
        SwooleServer $server,
        RequestHandlerInterface $requestHandler,
        PsrRequestFactoryInterface $requestFactory,
        SwooleResponseFactoryInterface $responseFactory,
        ?ReloaderInterface $reloader = null,
        ?string $processName = null,
        bool $debug = false
    ) {
        $this->server = $server;
        $this->requestHandler = $requestHandler;
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->reloader = $reloader;
        $this->processName = $processName ?? self::DEFAULT_PROCESS_NAME;
        $this->debug = $debug;
    }

    /**
     * Run server.
     */
    public function run(): void
    {
        $cwd = \getcwd();
        $this->cwd = $cwd !== false ? $cwd : '-';

        foreach ($this->serverEvents as $event) {
            $method = 'on' . \ucfirst($event);

            if (\method_exists($this, $method)) {
                /** @var callable $callable */
                $callable = [$this, $method];

                $this->server->on($event, $callable);
            }
        }

        \set_error_handler([$this, 'handleError']);

        $this->server->start();
    }

    /**
     * On server start callback.
     *
     * @param SwooleServer $server
     */
    public function onStart(SwooleServer $server): void
    {
        $mode = $server->manager_pid !== 0 ? 'process' : 'base';
        $this->setProcessName($this->processName . '-master-' . $mode);

        $this->log(\sprintf('Swoole server is running in %s at %s:%d', $this->cwd, $server->host, $server->port));
    }

    /**
     * On manager start callback.
     *
     * @param SwooleServer $server
     */
    public function onManagerStart(SwooleServer $server): void
    {
        $mode = $server->manager_pid !== 0 ? 'process' : 'base';
        $this->setProcessName($this->processName . '-manager-' . $mode);

        $this->log(\sprintf('Swoole manager in %s at %s:%d', $this->cwd, $server->host, $server->port));
    }

    /**
     * On worker start callback.
     *
     * @param SwooleServer $server
     * @param int          $workerId
     */
    public function onWorkerStart(SwooleServer $server, int $workerId): void
    {
        $this->setProcessName(
            \sprintf('%s-%s-%s', $this->processName, $server->taskworker ? 'task' : 'worker', $workerId)
        );

        if ($this->reloader !== null && $workerId === 0) {
            $this->reloader->register($server);
        }

        $this->log(\sprintf(
            'Swoole %s with ID "%s" started in %s, for server running at %s:%d',
            $server->taskworker ? 'task' : 'worker',
            $workerId,
            $this->cwd,
            $server->host,
            $server->port
        ));
    }

    /**
     * On server request callback.
     *
     * @param SwooleRequest  $swooleRequest
     * @param SwooleResponse $swooleResponse
     */
    public function onRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        try {
            $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

            $psrResponse = $this->requestHandler->handle($psrRequest);

            $swooleResponse = $this->responseFactory->fromPsrResponse($psrResponse, $swooleResponse);
        } catch (\Throwable $exception) {
            // @ignoreException
            $swooleResponse = $this->getExceptionResponse($exception, $swooleResponse);
        } finally {
            $swooleResponse->end();
        }
    }

    /**
     * On server shutdown callback.
     *
     * @param SwooleServer $server
     */
    public function onShutdown(SwooleServer $server): void
    {
        if ($this->cwd !== null) {
            \chdir($this->cwd);
        }

        $this->log(\sprintf('Swoole server running at %s:%d has shut down', $server->host, $server->port));
    }

    /**
     * Custom errors handler.
     * Transforms unhandled errors into exceptions.
     *
     * @param int         $severity
     * @param string      $message
     * @param string|null $file
     * @param int|null    $line
     *
     * @throws \ErrorException
     *
     * @return bool
     */
    public function handleError(int $severity, string $message, string $file = null, int $line = null): bool
    {
        if ((\error_reporting() & $severity) !== 0) {
            throw new \ErrorException($message, $severity, $severity, $file, $line);
        }

        return false;
    }

    /**
     * @param \Throwable     $exception
     * @param SwooleResponse $swooleResponse
     *
     * @return SwooleResponse
     */
    private function getExceptionResponse(\Throwable $exception, SwooleResponse $swooleResponse): SwooleResponse
    {
        $swooleResponse->setStatusCode(500);

        $message = 'An unexpected error occurred';

        if ($this->debug) {
            $message .= "; stack trace:\n\n" . $this->getStackTrace($exception);
        }

        $swooleResponse->write($message);

        return $swooleResponse;
    }

    /**
     * Get execution stack trace.
     *
     * @param \Throwable $exception
     *
     * @return string
     */
    private function getStackTrace(\Throwable $exception): string
    {
        $exceptionTemplate = <<< 'TRACE'
%s raised in file %s line %d:
Message: %s
Stack Trace:
%s


TRACE;
        $message = '';

        do {
            $message .= \sprintf(
                $exceptionTemplate,
                \get_class($exception),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage(),
                $exception->getTraceAsString()
            );
        } while ($exception = $exception->getPrevious());

        return $message;
    }

    /**
     * Set running process name.
     *
     * @param string $name
     */
    private function setProcessName(string $name): void
    {
        if (\PHP_OS !== 'Darwin') {
            \swoole_set_process_name($name);
        }
    }

    /**
     * Log message.
     *
     * @param string $message
     */
    private function log(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->debug($message);
        }
    }
}
