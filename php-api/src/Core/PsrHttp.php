<?php
declare(strict_types=1);

namespace Psr\Http\Message {
    if (!interface_exists(MessageInterface::class)) {
        interface MessageInterface {
            public function getProtocolVersion();
            public function withProtocolVersion($version);
            public function getHeaders();
            public function hasHeader($name);
            public function getHeader($name);
            public function getHeaderLine($name);
            public function withHeader($name, $value);
            public function withAddedHeader($name, $value);
            public function withoutHeader($name);
            public function getBody();
            public function withBody(StreamInterface $body);
        }
    }

    if (!interface_exists(RequestInterface::class)) {
        interface RequestInterface extends MessageInterface {
            public function getRequestTarget();
            public function withRequestTarget($requestTarget);
            public function getMethod();
            public function withMethod($method);
            public function getUri();
            public function withUri(UriInterface $uri, $preserveHost = false);
        }
    }

    if (!interface_exists(ServerRequestInterface::class)) {
        interface ServerRequestInterface extends RequestInterface {
            public function getServerParams();
            public function getCookieParams();
            public function withCookieParams(array $cookies);
            public function getQueryParams();
            public function withQueryParams(array $query);
            public function getUploadedFiles();
            public function withUploadedFiles(array $uploadedFiles);
            public function getParsedBody();
            public function withParsedBody($data);
            public function getAttributes();
            public function getAttribute($name, $default = null);
            public function withAttribute($name, $value);
            public function withoutAttribute($name);
        }
    }

    if (!interface_exists(ResponseInterface::class)) {
        interface ResponseInterface extends MessageInterface {
            public function getStatusCode();
            public function withStatus($code, $reasonPhrase = '');
            public function getReasonPhrase();
        }
    }

    if (!interface_exists(StreamInterface::class)) {
        interface StreamInterface {
            public function __toString();
            public function close();
            public function detach();
            public function getSize();
            public function tell();
            public function eof();
            public function isSeekable();
            public function seek($offset, $whence = SEEK_SET);
            public function rewind();
            public function isWritable();
            public function write($string);
            public function isReadable();
            public function read($length);
            public function getContents();
            public function getMetadata($key = null);
        }
    }

    if (!interface_exists(UriInterface::class)) {
        interface UriInterface {
            public function getScheme();
            public function getAuthority();
            public function getUserInfo();
            public function getHost();
            public function getPort();
            public function getPath();
            public function getQuery();
            public function getFragment();
            public function withScheme($scheme);
            public function withUserInfo($user, $password = null);
            public function withHost($host);
            public function withPort($port);
            public function withPath($path);
            public function withQuery($query);
            public function withFragment($fragment);
            public function __toString();
        }
    }
}

namespace Psr\Http\Server {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    if (!interface_exists(MiddlewareInterface::class)) {
        interface MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
        }
    }

    if (!interface_exists(RequestHandlerInterface::class)) {
        interface RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface;
        }
    }
}

