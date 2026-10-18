<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner;

use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Retains failures from streaming `read()` and `eof()` calls that an emitter may consume.
 */
final class ResponseBody implements StreamInterface
{
    private Throwable|null $failure = null;

    public function __construct(private readonly StreamInterface $stream) {}

    public function __toString(): string
    {
        return (string) $this->stream;
    }

    public function close(): void
    {
        $this->stream->close();
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        return $this->stream->detach();
    }

    public function eof(): bool
    {
        try {
            return $this->stream->eof();
        } catch (Throwable $e) {
            $this->failure = $e;

            throw $e;
        }
    }

    public function getContents(): string
    {
        return $this->stream->getContents();
    }

    /**
     * Returns the failure even when the emitter returned normally after consuming it.
     */
    public function getFailure(): Throwable|null
    {
        return $this->failure;
    }

    public function getMetadata(string|null $key = null): mixed
    {
        return $this->stream->getMetadata($key);
    }

    public function getSize(): int|null
    {
        return $this->stream->getSize();
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    public function isWritable(): bool
    {
        return $this->stream->isWritable();
    }

    public function read(int $length): string
    {
        try {
            return $this->stream->read($length);
        } catch (Throwable $e) {
            $this->failure = $e;

            throw $e;
        }
    }

    public function rewind(): void
    {
        $this->stream->rewind();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->stream->seek($offset, $whence);
    }

    public function tell(): int
    {
        return $this->stream->tell();
    }

    public function write(string $string): int
    {
        return $this->stream->write($string);
    }
}
