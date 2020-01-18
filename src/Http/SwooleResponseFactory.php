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

use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwooleResponse;

final class SwooleResponseFactory implements SwooleResponseFactoryInterface
{
    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-http-server/methods-properties#swoole-http-response-write
     */
    private const CHUNK_SIZE = 2097152;

    /**
     * {@inheritdoc}
     */
    public function fromPsrResponse(ResponseInterface $psrResponse, SwooleResponse $swooleResponse): SwooleResponse
    {
        $swooleResponse->setStatusCode($psrResponse->getStatusCode(), $psrResponse->getReasonPhrase());

        foreach ($psrResponse->withoutHeader(SetCookies::SET_COOKIE_HEADER)->getHeaders() as $header => $values) {
            $swooleResponse->header(\ucwords($header, '-'), \implode(', ', $values));
        }

        foreach (SetCookies::fromResponse($psrResponse)->getAll() as $cookie) {
            $swooleResponse->cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpires(),
                $cookie->getPath() ?? '/',
                $cookie->getDomain() ?? '',
                $cookie->getSecure(),
                $cookie->getHttpOnly(),
                $this->getSameSitePolicy($cookie)
            );
        }

        $body = $psrResponse->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $swooleResponse->write($body->read(self::CHUNK_SIZE));
        }

        return $swooleResponse;
    }

    /**
     * Get same-site cookie policy.
     *
     * @param SetCookie $cookie
     *
     * @return string|null
     */
    private function getSameSitePolicy(SetCookie $cookie): ?string
    {
        $sameSite = $cookie->getSameSite();

        return $sameSite !== null ? \str_replace('SameSite=', '', $sameSite->asString()) : null;
    }
}
