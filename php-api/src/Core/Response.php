<?php
declare(strict_types=1);

namespace CouncilLibrary\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    private int $statusCode;
    private array $headers = [];
    private string $body = '';

    public function __construct(int $statusCode = 200, array $headers = [], string $body = '')
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): self
    {
        $clone = clone $this;
        $clone->statusCode = (int)$code;
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return '';
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion($version): self
    {
        return $this;
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
        if (!isset($this->headers[$key])) {
            return '';
        }
        return is_array($this->headers[$key]) ? implode(', ', $this->headers[$key]) : (string)$this->headers[$key];
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
        $key = strtolower($name);
        if (!isset($clone->headers[$key])) {
            $clone->headers[$key] = [];
        }
        $existing = (array)$clone->headers[$key];
        $existing[] = $value;
        $clone->headers[$key] = $existing;
        return $clone;
    }

    public function withoutHeader($name): self
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return new class($this) implements StreamInterface {
            public function __construct(private Response $response) {}
            public function __toString(): string { return $this->response->getBodyString(); }
            public function close(): void {}
            public function detach() { return null; }
            public function getSize(): ?int { return strlen($this->response->getBodyString()); }
            public function tell(): int { return 0; }
            public function eof(): bool { return true; }
            public function isSeekable(): bool { return false; }
            public function seek($offset, $whence = SEEK_SET): void {}
            public function rewind(): void {}
            public function isWritable(): bool { return true; }
            public function write($string): int {
                $this->response->appendBody((string)$string);
                return strlen((string)$string);
            }
            public function isReadable(): bool { return true; }
            public function read($length): string { return $this->response->getBodyString(); }
            public function getContents(): string { return $this->response->getBodyString(); }
            public function getMetadata($key = null) { return null; }
        };
    }

    public function withBody(StreamInterface $body): self
    {
        $clone = clone $this;
        $clone->body = (string)$body;
        return $clone;
    }

    public function getBodyString(): string
    {
        return $this->body;
    }

    public function appendBody(string $text): void
    {
        $this->body .= $text;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                $val = is_array($value) ? implode(', ', $value) : $value;
                header("{$name}: {$val}");
            }
        }
        echo $this->body;
    }
}

