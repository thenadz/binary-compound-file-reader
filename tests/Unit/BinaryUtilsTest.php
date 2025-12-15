<?php

namespace DanRossiter\BinaryCompoundFile\Tests\Unit;

use DanRossiter\BinaryCompoundFile\BinaryUtils;
use PHPUnit\Framework\TestCase;

class BinaryUtilsTest extends TestCase
{
    public function testInt32ConvertsUnsignedToSigned(): void
    {
        // Test positive value remains positive
        $this->assertSame(100, BinaryUtils::int32(100));

        // Test maximum positive 32-bit value
        $this->assertSame(2147483647, BinaryUtils::int32(2147483647));

        // Test value that should be negative
        $this->assertSame(-1, BinaryUtils::int32(0xFFFFFFFF));

        // Test -2 (ENDOFCHAIN)
        $this->assertSame(-2, BinaryUtils::int32(0xFFFFFFFE));

        // Test minimum negative value
        $this->assertSame(-2147483648, BinaryUtils::int32(0x80000000));
    }
}
