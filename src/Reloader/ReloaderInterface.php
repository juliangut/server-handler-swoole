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

use Psr\Log\LoggerAwareInterface;
use Swoole\Server as SwooleServer;

interface ReloaderInterface extends LoggerAwareInterface
{
    /**
     * Register server reloader.
     *
     * @param SwooleServer $server
     */
    public function register(SwooleServer $server): void;
}
