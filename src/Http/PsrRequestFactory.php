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

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request as SwooleRequest;

final class PsrRequestFactory implements PsrRequestFactoryInterface
{
    /**
     * @var ServerRequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var UriFactoryInterface
     */
    private $uriFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var UploadedFileFactoryInterface
     */
    private $uploadedFileFactory;

    /**
     * PsrRequestFactory constructor.
     *
     * @param ServerRequestFactoryInterface $requestFactory
     * @param UriFactoryInterface           $uriFactory
     * @param StreamFactoryInterface        $streamFactory
     * @param UploadedFileFactoryInterface  $uploadedFileFactory
     */
    public function __construct(
        ServerRequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $uploadedFileFactory
    ) {
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->streamFactory = $streamFactory;
        $this->uploadedFileFactory = $uploadedFileFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function fromSwooleRequest(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        $headers = $swooleRequest->header ?? [];
        $server = $swooleRequest->server ?? [];
        $server = \array_change_key_case($server, \CASE_UPPER);

        $psrRequest = $this->requestFactory->createServerRequest(
            $this->getMethod($server),
            $this->getUri($server, \array_change_key_case($headers, \CASE_UPPER)),
            $server
        );

        foreach ($headers as $header => $value) {
            $psrRequest = $psrRequest->withHeader($header, $value);
        }

        $psrRequest = $psrRequest->withProtocolVersion($this->getProtocolVersion($server));
        $psrRequest = $psrRequest->withCookieParams($swooleRequest->cookie ?? []);
        $psrRequest = $psrRequest->withQueryParams($swooleRequest->get ?? []);
        $psrRequest = $psrRequest->withParsedBody($swooleRequest->post ?? []);
        $psrRequest = $psrRequest->withUploadedFiles($this->getUploadedFiles($swooleRequest->files ?? []));
        $psrRequest = $psrRequest->withBody($this->getBody($swooleRequest));

        return $psrRequest;
    }

    /**
     * Get HTTP method.
     *
     * @param array<string, mixed> $server
     *
     * @return string
     */
    private function getMethod(array $server): string
    {
        return $server['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Get URI.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $headers
     *
     * @return UriInterface
     */
    private function getUri(array $server, array $headers): UriInterface
    {
        $uri = $this->uriFactory->createUri()
            ->withScheme($this->getSchema($server));

        [$host, $port] = $this->getHostAndPort($server, $headers);
        $uri = $uri->withHost($host);
        if (!\in_array($port, ['', '80'], true)) {
            $uri = $uri->withPort($port);
        }

        [$path, $fragment] = $this->getPathAndFragment($server);

        return $uri->withPath($path)
            ->withFragment($fragment)
            ->withQuery($this->getQuery($server));
    }

    /**
     * Get URI schema.
     *
     * @param array<string, mixed> $server
     *
     * @return string
     */
    private function getSchema(array $server): string
    {
        if (isset($server['HTTPS'])
            && (
                (\is_bool($server['HTTPS']) && $server['HTTPS'] === true)
                || (\is_string($server['HTTPS']) && \strtolower($server['HTTPS']) === 'on')
            )
        ) {
            return 'https';
        }

        return 'http';
    }

    /**
     * Get URI host and port.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $headers
     *
     * @return mixed[]
     */
    private function getHostAndPort(array $server, array $headers): array
    {
        $parseHostString = function (string $hostString): array {
            $host = $hostString;
            $port = '';

            if (\strpos($host, ':') !== false) {
                [$host, $port] = \explode(':', $host, 2);
            }

            return [$host, $port];
        };

        $parseIpv6String = function (string $hostString, string $port): array {
            $host = '[' . $hostString . ']';
            $port = $port !== '' ? $port : '80';

            if ($port . ']' === \substr($host, ((int) \strrpos($host, ':')) + 1)) {
                $port = '';
            }

            return [$host, $port];
        };

        if (isset($headers['HOST'])) {
            return $parseHostString($headers['HOST']);
        }

        if (isset($server['HTTP_HOST'])) {
            return $parseHostString($server['HTTP_HOST']);
        }

        if (!isset($server['SERVER_NAME'])) {
            return ['', ''];
        }

        $host = $server['SERVER_NAME'];
        $port = $server['SERVER_PORT'] ?? '';

        if (!isset($server['SERVER_ADDR']) || \preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host) === false
        ) {
            return [$host, (string) $port];
        }

        return $parseIpv6String($server['SERVER_ADDR'], (string) $port);
    }

    /**
     * Get URI path and fragment.
     *
     * @param array<string, mixed> $server
     *
     * @return string[]
     */
    private function getPathAndFragment(array $server): array
    {
        $requestUri = $server['REQUEST_URI'] ?? null;
        $path = $server['ORIG_PATH_INFO'] ?? null;
        $fragment = '';

        if ($requestUri !== null) {
            $path = \preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
        } elseif ($path === null) {
            $path = '/';
        }

        $path = \explode('?', $path, 2)[0];

        if (\strpos($path, '#') !== false) {
            [$path, $fragment] = \explode('#', $path, 2);
        }

        return [$path, $fragment];
    }

    /**
     * Get URI query string.
     *
     * @param array<string, mixed> $server
     *
     * @return string
     */
    private function getQuery(array $server): string
    {
        if (isset($server['QUERY_STRING'])) {
            return \ltrim($server['QUERY_STRING'], '?');
        }

        return '';
    }

    /**
     * Get HTTP protocol version.
     *
     * @param array<string, mixed> $server
     *
     * @return string
     */
    private function getProtocolVersion(array $server): string
    {
        if (isset($server['SERVER_PROTOCOL'])
            && \preg_match('!^(HTTP/)?(?P<version>[1-9]\d*(?:\.\d)?)$!', $server['SERVER_PROTOCOL'], $matches) !== false
        ) {
            return $matches['version'];
        }

        return '1.1';
    }

    /**
     * Get uploaded files.
     *
     * @param array<string, string|array<string, mixed>> $files
     *
     * @return array<string, UploadedFileInterface|array<UploadedFileInterface>>
     */
    private function getUploadedFiles(array $files): array
    {
        $uploadedFiles = [];
        foreach ($files as $key => $value) {
            if (\is_array($value) && isset($value['tmp_name']) && \is_array($value['tmp_name'])) {
                $uploadedFiles[$key] = $this->getRecursiveUploadedFiles(
                    $value['tmp_name'],
                    $value['size'],
                    $value['error'],
                    $value['name'] ?? null,
                    $value['type'] ?? null
                );

                continue;
            }

            if (\is_array($value) && isset($value['tmp_name'])) {
                $uploadedFiles[$key] = $this->uploadedFileFactory->createUploadedFile(
                    $this->streamFactory->createStream($value['tmp_name']),
                    $value['size'],
                    $value['error'],
                    $value['name'] ?? null,
                    $value['type'] ?? null
                );

                continue;
            }

            if (\is_array($value)) {
                $uploadedFiles[$key] = $this->getUploadedFiles($value);

                continue;
            }
        }

        return $uploadedFiles;
    }

    /**
     * @param array<string, mixed>      $tmpNameTree
     * @param array<string, mixed>      $sizeTree
     * @param array<string, mixed>      $errorTree
     * @param array<string, mixed>|null $nameTree
     * @param array<string, mixed>|null $typeTree
     *
     * @return array<string, mixed>
     */
    private function getRecursiveUploadedFiles(
        array $tmpNameTree,
        array $sizeTree,
        array $errorTree,
        array $nameTree = null,
        array $typeTree = null
    ): array {
        $uploadedFiles = [];
        foreach ($tmpNameTree as $key => $value) {
            if (\is_array($value)) {
                $uploadedFiles[$key] = $this->getRecursiveUploadedFiles(
                    $tmpNameTree[$key],
                    $sizeTree[$key],
                    $errorTree[$key],
                    $nameTree[$key] ?? null,
                    $typeTree[$key] ?? null
                );

                continue;
            }

            $uploadedFiles[$key] = $this->uploadedFileFactory->createUploadedFile(
                $this->streamFactory->createStream($tmpNameTree[$key]),
                $sizeTree[$key],
                $errorTree[$key],
                $nameTree[$key] ?? null,
                $typeTree[$key] ?? null
            );
        }

        return $uploadedFiles;
    }

    /**
     * Get body stream from Swoole request.
     *
     * @param SwooleRequest $swooleRequest
     *
     * @return StreamInterface
     */
    private function getBody(SwooleRequest $swooleRequest): StreamInterface
    {
        return $this->streamFactory->createStream($swooleRequest->rawContent() ?? '');
    }
}
