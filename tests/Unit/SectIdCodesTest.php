<?php

namespace DanRossiter\BinaryCompoundFile\Tests\Unit;

use DanRossiter\BinaryCompoundFile\SectIdCodes;
use PHPUnit\Framework\TestCase;

class SectIdCodesTest extends TestCase
{
    public function testFreeSectValue(): void
    {
        $this->assertSame(-1, SectIdCodes::FREESECT);
    }

    public function testEndOfChain(): void
    {
        $this->assertSame(-2, SectIdCodes::ENDOFCHAIN);
    }

    public function testFatSect(): void
    {
        $this->assertSame(-3, SectIdCodes::FATSECT);
    }

    public function testDifSect(): void
    {
        $this->assertSame(-4, SectIdCodes::DIFSECT);
    }
}
