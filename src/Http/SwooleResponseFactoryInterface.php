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

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwooleResponse;

interface SwooleResponseFactoryInterface
{
    /**
     * Get Swoole response from PSR7 response.
     *
     * @param ResponseInterface $psrResponse
     * @param SwooleResponse    $swooleResponse
     *
     * @return SwooleResponse
     */
    public function fromPsrResponse(ResponseInterface $psrResponse, SwooleResponse $swooleResponse): SwooleResponse;
}
