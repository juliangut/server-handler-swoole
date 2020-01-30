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

namespace Jgut\ServerHandler\Swoole\Reloader;

use Psr\Log\LoggerAwareTrait;
use Swoole\Server as SwooleServer;

class InotifyFileReloader implements ReloaderInterface
{
    use LoggerAwareTrait;

    /**
     * inotify stream.
     *
     * @var resource
     */
    private $inotify;

    /**
     * Review interval in milliseconds.
     *
     * @var int
     */
    private $interval;

    /**
     * List of watched files.
     *
     * @var array<string, string>
     */
    private $watchedFiles = [];

    /**
     * InotifyFileReloader constructor.
     *
     * @param int $interval
     */
    public function __construct(int $interval)
    {
        if (!\extension_loaded('inotify')) {
            throw new \RuntimeException('"inotify" PHP extension not found');
        }

        $this->inotify = \inotify_init();
        if ($this->inotify === false) {
            throw new \RuntimeException('Unable to initialize an inotify instance');
        }

        if (!\stream_set_blocking($this->inotify, false)) {
            throw new \RuntimeException('Unable to set non-blocking mode on inotify stream');
        }

        $this->interval = \max(1, $interval);
    }

    /**
     * {@inheritdoc}
     */
    public function register(SwooleServer $server): void
    {
        $tickHandler = \Closure::fromCallable(function () use ($server): void {
            $this->onTick($server);
        })->bindTo($this);

        $server->tick($this->interval, $tickHandler, $server);
    }

    /**
     * On tick event handler.
     *
     * @param SwooleServer $server
     */
    private function onTick(SwooleServer $server): void
    {
        if ($this->filesHaveChange()) {
            $this->log('Reloading due to file changes');

            $server->reload();
        }
    }

    /**
     * Whether files have been changed or not.
     *
     * @return bool
     */
    private function filesHaveChange(): bool
    {
        foreach (\array_diff(\get_included_files(), $this->watchedFiles) as $filePath) {
            $descriptor = \inotify_add_watch($this->inotify, $filePath, \IN_MODIFY);
            $this->watchedFiles[$descriptor] = $filePath;
        }

        $events = \inotify_read($this->inotify);
        if (\is_array($events)) {
            $reducer = \Closure::fromCallable(function (bool $modified, array $event): bool {
                $descriptor = $event['wd'] ?? null;

                return $modified || ($descriptor !== null && isset($this->watchedFiles[$descriptor]));
            })->bindTo($this);

            return \array_reduce($events, $reducer, false);
        }

        return false;
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
