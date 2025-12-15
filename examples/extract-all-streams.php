<?php

/**
 * Example: Extract all streams from a compound file to individual files
 * 
 * This example demonstrates how to:
 * - Open a compound binary file
 * - Iterate through all directory entries
 * - Extract stream contents to separate files
 * - Handle both little-endian and big-endian files
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DanRossiter\BinaryCompoundFile\CompoundFile;
use DanRossiter\BinaryCompoundFile\DirectoryEntry;
use DanRossiter\BinaryCompoundFile\StorageType;

// Configuration
$inputFile = __DIR__ . '/../tests/fixtures/Dan Rossiter Resume-BE.doc';
$outputDir = __DIR__ . '/../extracted_streams';

// Open the compound binary file
$fp = fopen($inputFile, 'rb');
if (!$fp) {
    die("Failed to open file: $inputFile\n");
}

printf("Opened file: %s\n", $inputFile);

// Parse the compound file
$cfb = new CompoundFile($fp);

// Validate the file
if (!$cfb->isValid()) {
    die("Invalid compound file format\n");
}

printf("Valid compound file. Version: %d, Sector size: %d bytes\n",
    $cfb->getHeader()->getRevision(),
    $cfb->getHeader()->getSectSize()
);

// Create output directory
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
    printf("Created output directory: %s\n", $outputDir);
}

// Get all directories
$directories = $cfb->getDirectories();
printf("Found %d directory entries\n\n", count($directories));

// Extract all streams
$streamCount = 0;
$totalBytes = 0;

foreach ($directories as $name => $dir) {
    // Only process stream entries (not storage or root)
    if ($dir->getMse() !== StorageType::STREAM) {
        continue;
    }
    
    // Get stream content
    $content = $cfb->getStream($dir);
    $size = strlen($content);
    
    // Create safe filename
    $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
    $outputPath = $outputDir . '/' . $filename . '.bin';
    
    // Write to file
    file_put_contents($outputPath, $content);
    
    printf("Extracted: %-30s %8d bytes -> %s\n", $name, $size, $filename . '.bin');
    
    $streamCount++;
    $totalBytes += $size;
}

fclose($fp);

printf("\nExtracted %d streams, %d total bytes\n", $streamCount, $totalBytes);
printf("Output directory: %s\n", $outputDir);
