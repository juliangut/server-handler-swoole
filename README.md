[![PHP version](https://img.shields.io/badge/PHP-%3E%3D7.1-8892BF.svg?style=flat-square)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/vpre/juliangut/server-handler-swoole.svg?style=flat-square)](https://packagist.org/packages/juliangut/server-handler-swoole)
[![License](https://img.shields.io/github/license/juliangut/server-handler-swoole.svg?style=flat-square)](https://github.com/juliangut/server-handler-swoole/blob/master/LICENSE)

[![Build Status](https://img.shields.io/travis/juliangut/server-handler-swoole.svg?style=flat-square)](https://travis-ci.org/juliangut/server-handler-swoole)
[![Style Check](https://styleci.io/repos/234732458/shield)](https://styleci.io/repos/234732458)
[![Code Quality](https://img.shields.io/scrutinizer/g/juliangut/server-handler-swoole.svg?style=flat-square)](https://scrutinizer-ci.com/g/juliangut/server-handler-swoole)
[![Code Coverage](https://img.shields.io/coveralls/juliangut/server-handler-swoole.svg?style=flat-square)](https://coveralls.io/github/juliangut/server-handler-swoole)

[![Total Downloads](https://img.shields.io/packagist/dt/juliangut/server-handler-swoole.svg?style=flat-square)](https://packagist.org/packages/juliangut/server-handler-swoole/stats)
[![Monthly Downloads](https://img.shields.io/packagist/dm/juliangut/server-handler-swoole.svg?style=flat-square)](https://packagist.org/packages/juliangut/server-handler-swoole/stats)

# juliangut/server-handler-swoole

Easily run Swoole server with any implementation of PSR-15 RequestHandlerInterface

## Installation

### Composer

```
composer require juliangut/server-handler-swoole
```

## Usage

Require composer autoload file

```php
require './vendor/autoload.php';

use Jgut\ServerHandler\Swoole\Http\PsrRequestFactory;
use Jgut\ServerHandler\Swoole\Http\SwooleResponseFactory;
use Swoole\Http\Server as SwooleServer;

$swooleServer = new SwooleServer('127.0.0.1', 8080, \SWOOLE_BASE, \SWOOLE_SOCK_TCP);
$swooleServer->set([
    'max_conn' => 1024,
    'enable_coroutine' => false,
]);

$requestFactory = new PsrRequestFactory(
    /* \Psr\Http\Message\ServerRequestFactoryInterface */,
    /* \Psr\Http\Message\UriFactoryInterface */,
    /* \Psr\Http\Message\StreamFactoryInterface */,
    /* \Psr\Http\Message\UploadedFileFactoryInterface */
);
$responseFactory = new SwooleResponseFactory();

$server = new Server(
    $swooleServer,
    /* Psr\Http\Server\RequestHandlerInterface */,
    $requestFactory,
    $responseFactory,
    true
);

$server->run();
```

## Contributing

Found a bug or have a feature request? [Please open a new issue](https://github.com/juliangut/server-handler-swoole/issues). Have a look at existing issues before.

See file [CONTRIBUTING.md](https://github.com/juliangut/server-handler-swoole/blob/master/CONTRIBUTING.md)

## License

See file [LICENSE](https://github.com/juliangut/server-handler-swoole/blob/master/LICENSE) included with the source code for a copy of the license terms.
