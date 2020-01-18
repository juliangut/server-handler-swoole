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

namespace Jgut\ServerHandler\Swoole\Http;

use Psr\Http\Message\ServerRequestInterface;
use Swoole\Http\Request as SwooleRequest;

interface PsrRequestFactoryInterface
{
    /**
     * Get PSR7 request from Swoole request.
     *
     * @param SwooleRequest $swooleRequest
     *
     * @return ServerRequestInterface
     */
    public function fromSwooleRequest(SwooleRequest $swooleRequest): ServerRequestInterface;
}
