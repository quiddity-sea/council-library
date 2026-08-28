<?php
declare(strict_types=1);

namespace CouncilLibrary\Core;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class Request implements ServerRequestInterface
{
    private string $method;
    private string $path;
    private array $headers = [];
    private array $queryParams = [];
    private ?array $parsedBody = null;
    private string $rawBody = '';
    private array $attributes = [];

    public function __construct(
        string $method = 'GET',
        string $path = '/',
        array $headers = [],
        array $queryParams = [],
        ?array $parsedBody = null,
        string $rawBody = ''
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->headers = $headers;
        $this->queryParams = $queryParams;
        $this->parsedBody = $parsedBody;
        $this->rawBody = $rawBody;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = $value;
            }
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $parsedBody = null;

        if (!empty($rawBody)) {
            $contentType = $headers['content-type'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $parsedBody = json_decode($rawBody, true) ?? [];
            }
        }

        if ($parsedBody === null && !empty($_POST)) {
            $parsedBody = $_POST;
        }

        return new self($method, $path, $headers, $_GET, $parsedBody, $rawBody);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return new class($this->path) implements UriInterface {
            public function __construct(private string $path) {}
            public function getScheme(): string { return 'http'; }
            public function getAuthority(): string { return ''; }
            public function getUserInfo(): string { return ''; }
            public function getHost(): string { return 'localhost'; }
            public function getPort(): ?int { return 8080; }
            public function getPath(): string { return $this->path; }
            public function getQuery(): string { return ''; }
            public function getFragment(): string { return ''; }
            public function withScheme($scheme): self { return $this; }
            public function withUserInfo($user, $password = null): self { return $this; }
            public function withHost($host): self { return $this; }
            public function withPort($port): self { return $this; }
            public function withPath($path): self { return new self((string)$path); }
            public function withQuery($query): self { return $this; }
            public function withFragment($fragment): self { return $this; }
            public function __toString(): string { return $this->path; }
        };
    }

    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $clone = clone $this;
        $clone->path = $uri->getPath();
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader($name): array
    {
        $key = strtolower($name);
        return isset($this->headers[$key]) ? (array)$this->headers[$key] : [];
    }

    public function getHeaderLine($name): string
    {
        $key = strtolower($name);
        return isset($this->headers[$key]) ? (string)$this->headers[$key] : '';
    }

    public function withHeader($name, $value): self
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = $value;
        return $clone;
    }

    public function withAddedHeader($name, $value): self
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = $value;
        return $clone;
    }

    public function withoutHeader($name): self
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);
        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): self
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): self
    {
        $clone = clone $this;
        $clone->parsedBody = is_array($data) ? $data : null;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute($name): self
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        $str = $this->rawBody;
        return new class($str) implements StreamInterface {
            public function __construct(private string $content) {}
            public function __toString(): string { return $this->content; }
            public function close(): void {}
            public function detach() { return null; }
            public function getSize(): ?int { return strlen($this->content); }
            public function tell(): int { return 0; }
            public function eof(): bool { return true; }
            public function isSeekable(): bool { return false; }
            public function seek($offset, $whence = SEEK_SET): void {}
            public function rewind(): void {}
            public function isWritable(): bool { return false; }
            public function write($string): int { return 0; }
            public function isReadable(): bool { return true; }
            public function read($length): string { return $this->content; }
            public function getContents(): string { return $this->content; }
            public function getMetadata($key = null) { return null; }
        };
    }

    public function withBody(StreamInterface $body): self
    {
        $clone = clone $this;
        $clone->rawBody = (string)$body;
        return $clone;
    }

    // Stubbed ServerRequestInterface methods
    public function getServerParams(): array { return $_SERVER; }
    public function getCookieParams(): array { return $_COOKIE; }
    public function withCookieParams(array $cookies): self { return $this; }
    public function getUploadedFiles(): array { return $_FILES; }
    public function withUploadedFiles(array $uploadedFiles): self { return $this; }
    public function getRequestTarget(): string { return $this->path; }
    public function withRequestTarget($requestTarget): self { return $this; }
    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion($version): self { return $this; }
}

