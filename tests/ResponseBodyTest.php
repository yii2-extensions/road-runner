<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\tests;

use HttpSoft\Message\Stream;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use yii2\extensions\roadrunner\ResponseBody;

use function is_resource;

/**
 * Tests response-body transparency and retained streaming failures.
 */
final class ResponseBodyTest extends TestCase
{
    public function testDelegatesStreamOperations(): void
    {
        $stream = new Stream();
        $body = new ResponseBody($stream);

        self::assertNull(
            $body->getFailure(),
            'A new body must have no recorded failure.',
        );
        self::assertTrue(
            $body->isReadable(),
            'The wrapped stream must remain readable.',
        );
        self::assertTrue(
            $body->isWritable(),
            'The wrapped stream must remain writable.',
        );
        self::assertTrue(
            $body->isSeekable(),
            'The wrapped stream must remain seekable.',
        );
        self::assertSame(
            6,
            $body->write('abcdef'),
            'Writes must reach the wrapped stream.',
        );
        self::assertSame(
            6,
            $body->tell(),
            'The wrapped position must be exposed.',
        );
        self::assertSame(
            6,
            $body->getSize(),
            'The wrapped size must be exposed.',
        );

        $body->seek(-3, SEEK_END);

        self::assertSame(
            'd',
            $body->read(1),
            'Seek offset, mode and read length must be preserved.',
        );
        self::assertSame(
            'ef',
            $body->getContents(),
            'Remaining contents must be preserved.',
        );
        self::assertTrue(
            $body->eof(),
            'The wrapped end-of-stream state must be exposed.',
        );

        $body->rewind();

        self::assertFalse(
            $body->eof(),
            'Rewind must reset the read position.',
        );
        self::assertSame(
            'abcdef',
            (string) $body,
            'String conversion must preserve the body.',
        );
        self::assertSame(
            $stream->getMetadata(),
            $body->getMetadata(),
            'All stream metadata must be preserved.',
        );
        self::assertSame(
            $stream->getMetadata('uri'),
            $body->getMetadata('uri'),
            'Metadata keys must be forwarded.',
        );
        self::assertNull(
            $body->getFailure(),
            'Successful reads must not record a failure.',
        );

        $body->close();

        self::assertFalse(
            $stream->isReadable(),
            'Closing the wrapper must close the underlying stream.',
        );
    }

    public function testDetachPreservesResourceOwnership(): void
    {
        $resource = fopen('php://temp', 'w+b');

        self::assertIsResource(
            $resource,
            'The test stream must open successfully.',
        );

        $body = new ResponseBody(new Stream($resource));

        self::assertSame(
            $resource,
            $body->detach(),
            'Detach must return the underlying resource.',
        );
        self::assertNull(
            $body->getSize(),
            'The wrapper must expose the detached stream state.',
        );
        self::assertTrue(
            is_resource($resource),
            'Detach must leave ownership with the caller.',
        );

        fclose($resource);
    }

    /**
     * @param 'eof'|'read' $method
     */
    #[TestWith(['read'])]
    #[TestWith(['eof'])]
    public function testRecordsAndRethrowsReadFailures(string $method): void
    {
        $failure = new RuntimeException('Read failure.');

        $stream = $this->createMock(StreamInterface::class);

        $stream
            ->expects(self::once())
            ->method($method)
            ->willThrowException($failure);

        $body = new ResponseBody($stream);

        try {
            $method === 'read' ? $body->read(3) : $body->eof();

            self::fail(
                'The original read failure must propagate.',
            );
        } catch (RuntimeException $e) {
            self::assertSame(
                $failure,
                $e,
                'The original exception must be rethrown.',
            );
            self::assertSame(
                $failure,
                $body->getFailure(),
                'The failure must remain observable after a catch.',
            );
        }
    }
}
