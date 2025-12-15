<?php

namespace DanRossiter\BinaryCompoundFile\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DirectoryEntryTest extends TestCase
{
    public function testGetLongLongCombinesBigEndianValues(): void
    {
        // Use reflection to access private static method
        $reflection = new ReflectionClass('DanRossiter\BinaryCompoundFile\DirectoryEntry');
        $method = $reflection->getMethod('getLongLong');
        $method->setAccessible(true);

        // Test combining two 32-bit values in big-endian order
        // For a 64-bit value 0x0000000100000002:
        // - value1 (high): 0x00000001
        // - value2 (low):  0x00000002
        $result = $method->invoke(null, 0x00000001, 0x00000002, true);

        if (PHP_INT_SIZE >= 8) {
            // On 64-bit PHP, should return integer
            $this->assertIsInt($result);
            $this->assertEquals(0x0000000100000002, $result);
        } else {
            // On 32-bit PHP, may return float
            $this->assertTrue(is_int($result) || is_float($result));
            $this->assertEquals(4294967298.0, $result); // 2^32 + 2
        }
    }

    public function testGetLongLongCombinesLittleEndianValues(): void
    {
        $reflection = new ReflectionClass('DanRossiter\BinaryCompoundFile\DirectoryEntry');
        $method = $reflection->getMethod('getLongLong');
        $method->setAccessible(true);

        // Test combining two 32-bit values in little-endian order
        // For a 64-bit value 0x0000000100000002:
        // - value1 (low):  0x00000002
        // - value2 (high): 0x00000001
        $result = $method->invoke(null, 0x00000002, 0x00000001, false);

        if (PHP_INT_SIZE >= 8) {
            $this->assertIsInt($result);
            $this->assertEquals(0x0000000100000002, $result);
        } else {
            $this->assertTrue(is_int($result) || is_float($result));
            $this->assertEquals(4294967298.0, $result);
        }
    }

    public function testGetLongLongHandlesZero(): void
    {
        $reflection = new ReflectionClass('DanRossiter\BinaryCompoundFile\DirectoryEntry');
        $method = $reflection->getMethod('getLongLong');
        $method->setAccessible(true);

        $resultBE = $method->invoke(null, 0, 0, true);
        $resultLE = $method->invoke(null, 0, 0, false);

        $this->assertEquals(0, $resultBE);
        $this->assertEquals(0, $resultLE);
    }

    public function testGetLongLongHandlesMaxValues(): void
    {
        $reflection = new ReflectionClass('DanRossiter\BinaryCompoundFile\DirectoryEntry');
        $method = $reflection->getMethod('getLongLong');
        $method->setAccessible(true);

        // Test with max 32-bit unsigned values
        // 0xFFFFFFFFFFFFFFFF (all bits set)
        $result = $method->invoke(null, 0xFFFFFFFF, 0xFFFFFFFF, true);

        if (PHP_INT_SIZE >= 8) {
            // On 64-bit systems
            $this->assertEquals(-1, $result); // Two's complement representation
        } else {
            // On 32-bit systems, should be a large float
            $this->assertTrue(is_float($result));
        }
    }

    /**
     * Tests that byte order matters when calling getLongLong.
     *
     * Verifies that the same unpacked values produce different results
     * depending on the byte order flag, demonstrating why getLongLong
     * needs to handle endianness internally.
     */
    public function testGetLongLongByteOrderMatters(): void
    {
        $reflection = new ReflectionClass('DanRossiter\BinaryCompoundFile\DirectoryEntry');
        $method = $reflection->getMethod('getLongLong');
        $method->setAccessible(true);

        // Same input values, different byte order interpretations
        $value1 = 0x00000001;
        $value2 = 0x00000002;

        $bigEndianResult = $method->invoke(null, $value1, $value2, true);
        $littleEndianResult = $method->invoke(null, $value1, $value2, false);

        // These should be different - demonstrating order matters
        $this->assertNotEquals($bigEndianResult, $littleEndianResult);

        if (PHP_INT_SIZE >= 8) {
            // Big-endian: value1 is high, value2 is low = 0x0000000100000002
            $this->assertEquals(4294967298, $bigEndianResult);
            // Little-endian: value2 is high, value1 is low = 0x0000000200000001
            $this->assertEquals(8589934593, $littleEndianResult);
        }
    }
}
