<?php

namespace DanRossiter\BinaryCompoundFile\Tests\Unit;

use DanRossiter\BinaryCompoundFile\ByteOrder;
use PHPUnit\Framework\TestCase;

class ByteOrderTest extends TestCase
{
    public function testLittleEndianValue(): void
    {
        $this->assertSame(0xFFFE, ByteOrder::LITTLE_ENDIAN);
    }

    public function testBigEndian(): void
    {
        $this->assertSame(0xFEFF, ByteOrder::BIG_ENDIAN);
    }
}
