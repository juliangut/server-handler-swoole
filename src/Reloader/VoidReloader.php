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

use Psr\Log\LoggerInterface;
use Swoole\Server as SwooleServer;

class VoidReloader implements ReloaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(SwooleServer $server): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger): void
    {
    }
}
