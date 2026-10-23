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
    /**
     * Last failure raised by the wrapped stream's `read()` or `eof()` call, or `null` when none occurred.
     */
    private Throwable|null $failure = null;

    /**
     * Creates a new instance of the {@see ResponseBody} class.
     *
     * @param StreamInterface $stream Response body stream to delegate to.
     */
    public function __construct(private readonly StreamInterface $stream) {}

    /**
     * Returns the entire contents of the wrapped stream.
     */
    public function __toString(): string
    {
        return (string) $this->stream;
    }

    /**
     * Closes the wrapped stream and its underlying resources.
     */
    public function close(): void
    {
        $this->stream->close();
    }

    /**
     * Detaches the underlying resource from the wrapped stream.
     *
     * @return resource|null
     */
    public function detach()
    {
        return $this->stream->detach();
    }

    /**
     * Returns whether the wrapped stream is at its end and retains any failure for {@see getFailure()}.
     *
     * @throws Throwable if the wrapped stream fails; the same instance is retained before it is rethrown.
     */
    public function eof(): bool
    {
        try {
            return $this->stream->eof();
        } catch (Throwable $e) {
            $this->failure = $e;

            throw $e;
        }
    }

    /**
     * Returns the remaining contents of the wrapped stream.
     */
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

    /**
     * Returns the wrapped stream metadata, or the value for `$key`.
     *
     * @param string|null $key Metadata key to retrieve, or `null` for all metadata.
     */
    public function getMetadata(string|null $key = null): mixed
    {
        return $this->stream->getMetadata($key);
    }

    /**
     * Returns the size of the wrapped stream in bytes, or `null` when it is unknown.
     */
    public function getSize(): int|null
    {
        return $this->stream->getSize();
    }

    /**
     * Returns whether the wrapped stream is readable.
     */
    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    /**
     * Returns whether the wrapped stream is seekable.
     */
    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    /**
     * Returns whether the wrapped stream is writable.
     */
    public function isWritable(): bool
    {
        return $this->stream->isWritable();
    }

    /**
     * Reads up to `$length` bytes from the wrapped stream and retains any failure for {@see getFailure()}.
     *
     * @param int $length Maximum number of bytes to read.
     *
     * @throws Throwable if the wrapped stream fails; the same instance is retained before it is rethrown.
     */
    public function read(int $length): string
    {
        try {
            return $this->stream->read($length);
        } catch (Throwable $e) {
            $this->failure = $e;

            throw $e;
        }
    }

    /**
     * Seeks the wrapped stream to its beginning.
     */
    public function rewind(): void
    {
        $this->stream->rewind();
    }

    /**
     * Seeks the wrapped stream to a position.
     *
     * @param int $offset Stream offset.
     * @param int $whence Offset calculation mode, as accepted by `fseek()`.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->stream->seek($offset, $whence);
    }

    /**
     * Returns the current position of the wrapped stream pointer.
     */
    public function tell(): int
    {
        return $this->stream->tell();
    }

    /**
     * Writes data to the wrapped stream.
     *
     * @param string $string Data to write.
     *
     * @return int Number of bytes written.
     */
    public function write(string $string): int
    {
        return $this->stream->write($string);
    }
}
