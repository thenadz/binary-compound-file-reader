<?php

/**
 * Example: Using the stream wrapper for seamless integration
 * 
 * This example demonstrates how to use the cfbf:// stream wrapper
 * to access compound file streams as PHP resources.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DanRossiter\BinaryCompoundFile\CompoundFileStream;

// Register the stream wrapper
if (!in_array('cfbf', stream_get_wrappers())) {
    stream_wrapper_register('cfbf', CompoundFileStream::class);
    echo "Registered cfbf:// stream wrapper\n";
}

// Configuration
$filePath = __DIR__ . '/../tests/fixtures/Dan Rossiter Resume.doc';

// Open a stream using the wrapper
// Format: cfbf://path/to/file.doc#StreamName
$streamUrl = 'cfbf://' . $filePath . '#WordDocument';

echo "Opening stream: $streamUrl\n";

$handle = fopen($streamUrl, 'rb');
if (!$handle) {
    die("Failed to open stream\n");
}

// Read content
$content = stream_get_contents($handle);
$size = strlen($content);

printf("Read %d bytes from WordDocument stream\n", $size);

// Show first 100 bytes as hex
echo "First 100 bytes (hex):\n";
echo substr(bin2hex($content), 0, 200) . "\n";

fclose($handle);

// Unregister when done
stream_wrapper_unregister('cfbf');
echo "\nUnregistered cfbf:// stream wrapper\n";
