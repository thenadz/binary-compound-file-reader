<?php

namespace DanRossiter\BinaryCompoundFile\Tests\Integration;

use DanRossiter\BinaryCompoundFile\CompoundFile;
use DanRossiter\BinaryCompoundFile\CompoundFileStream;
use DanRossiter\BinaryCompoundFile\StorageType;
use PHPUnit\Framework\TestCase;

class StreamWrapperTest extends TestCase
{
    private string $leFile;

    protected function setUp(): void
    {
        $this->leFile = __DIR__ . '/../fixtures/Dan Rossiter Resume.doc';

        if (!file_exists($this->leFile)) {
            $this->markTestSkipped('Test file not found');
        }

        // Register stream wrapper
        if (!in_array('cfbf', stream_get_wrappers())) {
            stream_wrapper_register('cfbf', CompoundFileStream::class);
        }
    }

    protected function tearDown(): void
    {
        if (in_array('cfbf', stream_get_wrappers())) {
            stream_wrapper_unregister('cfbf');
        }
    }

    public function testStreamWrapperRegistration(): void
    {
        $this->assertContains('cfbf', stream_get_wrappers());
    }

    public function testReadStreamThroughWrapper(): void
    {
        $handle = fopen($this->leFile, 'rb');
        $cf = new CompoundFile($handle);
        $dirs = $cf->getDirectories();

        // Find WordDocument stream
        $wordDocStream = null;
        foreach ($dirs as $name => $dir) {
            if ($name === 'WordDocument' && $dir->getMse() === StorageType::STREAM) {
                $wordDocStream = $dir;

                break;
            }
        }

        $this->assertNotNull($wordDocStream, 'WordDocument stream should exist');

        // Read via stream wrapper
        $handle = fopen('cfbf://' . $this->leFile . '#WordDocument', 'rb');
        $this->assertIsResource($handle);

        $content = stream_get_contents($handle);
        $this->assertIsString($content);
        $this->assertGreaterThan(0, strlen($content));

        // Compare with direct access
        $directContent = $cf->getStream($wordDocStream);

        // Stream wrapper reads sector-aligned, so it may be slightly longer
        $this->assertGreaterThanOrEqual(strlen($directContent), strlen($content));
        $this->assertLessThan(strlen($directContent) + 1000, strlen($content));

        fclose($handle);
    }

    public function testStreamWrapperWithInvalidFile(): void
    {
        $handle = @fopen('cfbf://nonexistent.doc#Stream', 'rb');
        $this->assertFalse($handle);
    }

    public function testStreamWrapperWithInvalidStream(): void
    {
        $handle = @fopen('cfbf://' . $this->leFile . '#NonExistentStream', 'rb');
        $this->assertFalse($handle);
    }
}
