<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\tests\support\stub;

use HttpSoft\Message\StreamTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Creates a read exception inside the streaming call stack after one successful chunk.
 */
final class FailingReadStream implements StreamInterface
{
    use StreamTrait {
        read as private readContent;
    }

    private int $reads = 0;

    public function __construct()
    {
        $this->init('php://temp', 'w+b');
        $this->write('abcdef');
        $this->rewind();
    }

    public function read(int $length): string
    {
        if (++$this->reads === 2) {
            throw new RuntimeException('Stream read failed.');
        }

        return $this->readContent($length);
    }
}
