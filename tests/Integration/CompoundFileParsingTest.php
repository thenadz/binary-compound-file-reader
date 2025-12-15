<?php

namespace DanRossiter\BinaryCompoundFile\Tests\Integration;

use DanRossiter\BinaryCompoundFile\CompoundFile;
use DanRossiter\BinaryCompoundFile\StorageType;
use PHPUnit\Framework\TestCase;

class CompoundFileParsingTest extends TestCase
{
    private string $leFile;
    private string $beFile;

    protected function setUp(): void
    {
        $this->leFile = __DIR__ . '/../fixtures/Dan Rossiter Resume.doc';
        $this->beFile = __DIR__ . '/../fixtures/Dan Rossiter Resume-BE.doc';

        if (!file_exists($this->leFile)) {
            $this->markTestSkipped('Little-endian test file not found');
        }

        if (!file_exists($this->beFile)) {
            $this->markTestSkipped('Big-endian test file not found');
        }
    }

    public function testParseLittleEndianFile(): void
    {
        $handle = fopen($this->leFile, 'rb');
        $this->assertIsResource($handle);

        $cf = new CompoundFile($handle);
        $dirs = $cf->getDirectories();

        $this->assertIsArray($dirs);
        $this->assertNotEmpty($dirs);

        // Should have root directory
        $this->assertArrayHasKey('Root Entry', $dirs);

        fclose($handle);
    }

    public function testParseBigEndianFile(): void
    {
        $handle = fopen($this->beFile, 'rb');
        $this->assertIsResource($handle);

        $cf = new CompoundFile($handle);
        $dirs = $cf->getDirectories();

        $this->assertIsArray($dirs);
        $this->assertNotEmpty($dirs);

        // Should have root directory
        $this->assertArrayHasKey('Root Entry', $dirs);

        fclose($handle);
    }

    public function testLittleAndBigEndianProduceSameOutput(): void
    {
        $handleLE = fopen($this->leFile, 'rb');
        $handleBE = fopen($this->beFile, 'rb');

        $cfLE = new CompoundFile($handleLE);
        $cfBE = new CompoundFile($handleBE);

        $dirsLE = $cfLE->getDirectories();
        $dirsBE = $cfBE->getDirectories();

        // Same number of directories
        $this->assertCount(count($dirsLE), $dirsBE);

        // Same directory names
        $this->assertEquals(array_keys($dirsLE), array_keys($dirsBE));

        // Stream contents should match
        $totalBytesLE = 0;
        $totalBytesBE = 0;

        foreach ($dirsLE as $name => $dir) {
            if ($dir->getMse() === StorageType::STREAM) {
                $streamLE = $cfLE->getStream($dir);
                $streamBE = $cfBE->getStream($dirsBE[$name]);

                $this->assertEquals(
                    strlen($streamLE),
                    strlen($streamBE),
                    "Stream '$name' size mismatch"
                );

                $this->assertEquals(
                    $streamLE,
                    $streamBE,
                    "Stream '$name' content mismatch"
                );

                $totalBytesLE += strlen($streamLE);
                $totalBytesBE += strlen($streamBE);
            }
        }

        $this->assertEquals($totalBytesLE, $totalBytesBE);
        $this->assertGreaterThan(0, $totalBytesLE, 'Should have extracted some data');

        fclose($handleLE);
        fclose($handleBE);
    }

    public function testGetStreamForNonExistentDirectory(): void
    {
        $handle = fopen($this->leFile, 'rb');
        $cf = new CompoundFile($handle);
        $dirs = $cf->getDirectories();

        // Get a storage directory (not a stream)
        $storageDir = null;
        foreach ($dirs as $dir) {
            if ($dir->getMse() === StorageType::STORAGE) {
                $storageDir = $dir;

                break;
            }
        }

        if ($storageDir) {
            $stream = $cf->getStream($storageDir);
            $this->assertEquals('', $stream, 'Storage directories should return empty string');
        } else {
            $this->assertTrue(true, 'No storage directory found to test, test passes');
        }

        fclose($handle);
    }

    public function testStreamCounts(): void
    {
        $handle = fopen($this->leFile, 'rb');
        $cf = new CompoundFile($handle);
        $dirs = $cf->getDirectories();

        $streamCount = 0;
        foreach ($dirs as $dir) {
            if ($dir->getMse() === StorageType::STREAM) {
                $streamCount++;
            }
        }

        // Based on test-all.php, we expect 7 streams
        $this->assertEquals(7, $streamCount, 'Expected 7 streams in test file');

        fclose($handle);
    }
}
