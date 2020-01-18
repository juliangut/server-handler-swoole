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

namespace Jgut\ServerHandler\Swoole\Tests\Http;

use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Jgut\ServerHandler\Swoole\Http\SwooleResponseFactory;
use Laminas\Diactoros\ResponseFactory;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Response as SwooleResponse;

class SwooleResponseFactoryTest extends TestCase
{
    /**
     * @var SwooleResponseFactory
     */
    private $responseFactory;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->responseFactory = new SwooleResponseFactory();
    }

    public function testDefault(): void
    {
        $psrResponse = (new ResponseFactory())->createResponse();
        $psrResponse->getBody()->write('Body content');

        $swooleResponse = $this->getMockBuilder(SwooleResponse::class)->disableOriginalConstructor()->getMock();
        $swooleResponse->expects(self::once())
            ->method('setStatusCode')
            ->with(200, 'OK');
        $swooleResponse->expects(self::once())
            ->method('write')
            ->with('Body content');

        $this->responseFactory->fromPsrResponse($psrResponse, $swooleResponse);
    }

    public function testHeaders(): void
    {
        $psrResponse = (new ResponseFactory())->createResponse();
        $psrResponse = $psrResponse->withHeader('Content-Type', 'application/json');

        $swooleResponse = $this->getMockBuilder(SwooleResponse::class)->disableOriginalConstructor()->getMock();
        $swooleResponse->expects(self::once())
            ->method('header')
            ->with('Content-Type', 'application/json');

        $this->responseFactory->fromPsrResponse($psrResponse, $swooleResponse);
    }

    public function testCookies(): void
    {
        $psrResponse = (new ResponseFactory())->createResponse();
        $psrResponse = FigResponseCookies::set(
            $psrResponse,
            SetCookie::create('sessionId')
                ->withValue('123456')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
        );

        $swooleResponse = $this->getMockBuilder(SwooleResponse::class)->disableOriginalConstructor()->getMock();
        $swooleResponse->expects(self::once())
            ->method('cookie')
            ->with('sessionId', '123456', 0, '/', '', true, true, 'Lax');

        $this->responseFactory->fromPsrResponse($psrResponse, $swooleResponse);
    }

    public function testBody(): void
    {
        $psrResponse = (new ResponseFactory())->createResponse();
        $psrResponse->getBody()->write('Body content');

        $swooleResponse = $this->getMockBuilder(SwooleResponse::class)->disableOriginalConstructor()->getMock();
        $swooleResponse->expects(self::once())
            ->method('write')
            ->with('Body content');

        $this->responseFactory->fromPsrResponse($psrResponse, $swooleResponse);
    }
}
