<?php

/**
 * Example: Parse compound file structure
 * 
 * This example demonstrates how to inspect the internal structure
 * of a compound binary file including sectors, FAT chains, and directory tree.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DanRossiter\BinaryCompoundFile\CompoundFile;
use DanRossiter\BinaryCompoundFile\DirectoryEntry;
use DanRossiter\BinaryCompoundFile\StorageType;

// Configuration
$filePath = __DIR__ . '/../tests/fixtures/Dan Rossiter Resume.doc';

// Open file
$fp = fopen($filePath, 'rb');
if (!$fp) {
    die("Failed to open file: $filePath\n");
}

echo "=== Compound File Structure Analysis ===\n\n";

// Parse the file
$cfb = new CompoundFile($fp);
$header = $cfb->getHeader();

// Display header information
echo "File Header:\n";
echo "  Signature: " . bin2hex($header->getAbSig()) . "\n";
echo "  Byte Order: 0x" . dechex($header->getByteOrder()) . 
     ($header->isBigEndian() ? " (Big Endian)" : " (Little Endian)") . "\n";
echo "  Version: " . $header->getRevision() . "\n";
echo "  Sector Size: " . $header->getSectSize() . " bytes\n";
echo "  Mini Sector Size: " . $header->getMiniSectSize() . " bytes\n";
echo "  Total Sectors in DIFAT: " . $header->getCsectDif() . "\n";
echo "  First Directory Sector: " . $header->getSectDirStart() . "\n";
echo "  First FAT Sector: " . $header->getSectFatStart() . "\n";
echo "  First Mini FAT Sector: " . $header->getSectMiniFatStart() . "\n";
echo "  Has DIFAT: " . ($header->hasDifat() ? "Yes" : "No") . "\n";
echo "  Has Mini FAT: " . ($header->hasMiniFat() ? "Yes" : "No") . "\n";

echo "\nDirectory Structure:\n";

// Display directory tree
$directories = $cfb->getDirectories();
foreach ($directories as $name => $dir) {
    $type = match($dir->getMse()) {
        StorageType::INVALID => 'Invalid',
        StorageType::STORAGE => 'Storage',
        StorageType::STREAM => 'Stream',
        StorageType::LOCKBYTES => 'LockBytes',
        StorageType::PROPERTY => 'Property',
        StorageType::ROOT => 'Root',
        default => 'Unknown'
    };
    
    $size = $dir->getUlSize();
    $minor = $dir->isMinor() ? ' (mini)' : '';
    
    printf("  %-30s [%s]%s %d bytes\n", $name, $type, $minor, $size);
}

// Statistics
$streamCount = 0;
$storageCount = 0;
$totalStreamSize = 0;

foreach ($directories as $dir) {
    if ($dir->getMse() === StorageType::STREAM) {
        $streamCount++;
        $totalStreamSize += $dir->getUlSize();
    } elseif ($dir->getMse() === StorageType::STORAGE) {
        $storageCount++;
    }
}

echo "\nStatistics:\n";
echo "  Total Directories: " . count($directories) . "\n";
echo "  Streams: $streamCount\n";
echo "  Storages: $storageCount\n";
echo "  Total Stream Data: " . number_format($totalStreamSize) . " bytes\n";

fclose($fp);
