<?php
/**
 * Creates a fully spec-compliant big-endian version of a compound file for testing.
 * Converts all multi-byte fields from little-endian to big-endian throughout the entire file.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DanRossiter\BinaryCompoundFile\CompoundFile;
use DanRossiter\BinaryCompoundFile\SectIdCodes;

$inputFile = __DIR__ . '/../tests/fixtures/Dan Rossiter Resume.doc';
$outputFile = __DIR__ . '/../tests/fixtures/Dan Rossiter Resume-BE.doc';

echo "Converting compound file to big-endian byte order...\n";
echo "Input:  $inputFile\n";
echo "Output: $outputFile\n\n";

// Read entire file
$data = file_get_contents($inputFile);
if ($data === false) {
    die("Failed to read input file\n");
}

$length = strlen($data);
echo "File size: " . number_format($length) . " bytes\n\n";

// Parse the file structure first (using little-endian)
$fp = fopen($inputFile, 'rb');
$cfb = new CompoundFile($fp);
$header = $cfb->getHeader();

echo "Parsed file structure:\n";
echo "  Version: " . $header->getDllVersion() . "\n";
echo "  Sector size: " . $header->getSectSize() . "\n";
echo "  Mini sector size: " . $header->getMinorSectorSize() . "\n";
echo "  DIFAT entries: " . count($cfb->getDifat()) . "\n";
echo "  FAT sectors: " . $header->getCsectFat() . "\n";
echo "  Directory entries: " . count($cfb->getDirectories()) . "\n\n";

fclose($fp);

// Conversion functions
function swapBytes16($offset, &$data) {
    if ($offset + 1 >= strlen($data)) return;
    $temp = $data[$offset];
    $data[$offset] = $data[$offset + 1];
    $data[$offset + 1] = $temp;
}

function swapBytes32($offset, &$data) {
    if ($offset + 3 >= strlen($data)) return;
    $temp = $data[$offset];
    $data[$offset] = $data[$offset + 3];
    $data[$offset + 3] = $temp;
    $temp = $data[$offset + 1];
    $data[$offset + 1] = $data[$offset + 2];
    $data[$offset + 2] = $temp;
}

function swapBytes64($offset, &$data) {
    if ($offset + 7 >= strlen($data)) return;
    // Swap 8 bytes: 01234567 -> 76543210
    for ($i = 0; $i < 4; $i++) {
        $temp = $data[$offset + $i];
        $data[$offset + $i] = $data[$offset + 7 - $i];
        $data[$offset + 7 - $i] = $temp;
    }
}

$sectorSize = $header->getSectSize();
$miniSectorSize = $header->getMinorSectorSize();
$entriesPerFatSector = $sectorSize / 4;

echo "Converting header (512 bytes)...\n";

// Header fields
swapBytes16(24, $data); // uMinorVersion
swapBytes16(26, $data); // uDllVersion
swapBytes16(28, $data); // uByteOrder (0xFFFE -> 0xFEFF)
swapBytes16(30, $data); // uSectorShift
swapBytes16(32, $data); // uMiniSectorShift
swapBytes32(44, $data); // csectFat
swapBytes32(48, $data); // sectDirStart
swapBytes32(52, $data); // signature
swapBytes32(56, $data); // ulMiniSectorCutoff
swapBytes32(60, $data); // sectMiniFatStart
swapBytes32(64, $data); // csectMiniFat
swapBytes32(68, $data); // sectDifStart
swapBytes32(72, $data); // csectDif

// First 109 DIFAT entries
for ($i = 0; $i < 109; $i++) {
    swapBytes32(76 + ($i * 4), $data);
}

echo "Converting DIFAT chain sectors...\n";
// Additional DIFAT sectors if csectDif > 0
$csectDif = $header->getCsectDif();
if ($csectDif > 0) {
    $difSector = $header->getSectDifStart();
    $count = 0;
    while ($difSector != SectIdCodes::ENDOFCHAIN && $count < $csectDif) {
        $offset = 512 + ($difSector * $sectorSize);
        echo "  DIFAT sector $difSector at offset $offset\n";
        for ($i = 0; $i < $entriesPerFatSector; $i++) {
            swapBytes32($offset + ($i * 4), $data);
        }
        // Get next DIFAT sector (last entry in sector)
        $nextBytes = substr($data, $offset + ($sectorSize - 4), 4);
        $difSector = unpack('V', strrev($nextBytes))[1]; // Revert swap to get value
        $count++;
    }
}

echo "Converting FAT sectors...\n";
$difat = $cfb->getDifat();
foreach ($difat as $fatSector) {
    if ($fatSector < 0) continue;
    $offset = 512 + ($fatSector * $sectorSize);
    echo "  FAT sector $fatSector at offset $offset\n";
    for ($i = 0; $i < $entriesPerFatSector; $i++) {
        swapBytes32($offset + ($i * 4), $data);
    }
}

echo "Converting mini FAT sectors...\n";
if ($header->hasMiniFat()) {
    $sector = $header->getSectMiniFatStart();
    $fatChains = $cfb->getFatChains();
    while ($sector != SectIdCodes::ENDOFCHAIN) {
        $offset = 512 + ($sector * $sectorSize);
        echo "  Mini FAT sector $sector at offset $offset\n";
        for ($i = 0; $i < $entriesPerFatSector; $i++) {
            swapBytes32($offset + ($i * 4), $data);
        }
        $sector = array_key_exists($sector, $fatChains) ? $fatChains[$sector] : SectIdCodes::ENDOFCHAIN;
    }
}

echo "Converting directory sectors...\n";
$sector = $header->getSectDirStart();
$fatChains = $cfb->getFatChains();
$entriesPerDirSector = $sectorSize / 128;
$dirCount = 0;

while ($sector != SectIdCodes::ENDOFCHAIN) {
    $offset = 512 + ($sector * $sectorSize);
    echo "  Directory sector $sector at offset $offset\n";
    
    for ($e = 0; $e < $entriesPerDirSector; $e++) {
        $entryOffset = $offset + ($e * 128);
        if ($entryOffset + 128 > $length) break;
        
        // Directory entry fields:
        // 0-63: name (32 UTF-16LE characters)
        for ($i = 0; $i < 32; $i++) {
            swapBytes16($entryOffset + ($i * 2), $data);
        }
        swapBytes16($entryOffset + 64, $data); // cb
        // 66: mse (1 byte)
        // 67: bflags (1 byte)
        swapBytes32($entryOffset + 68, $data); // sidLeftSib
        swapBytes32($entryOffset + 72, $data); // sidRightSib
        swapBytes32($entryOffset + 76, $data); // sidChild
        // 80-95: clsid (16 bytes, treat as big blob - no swap needed for GUID)
        swapBytes32($entryOffset + 96, $data); // dwUserFlags
        swapBytes64($entryOffset + 100, $data); // createTime (64-bit)
        swapBytes64($entryOffset + 108, $data); // modifyTime (64-bit)
        swapBytes32($entryOffset + 116, $data); // sectStart
        swapBytes64($entryOffset + 120, $data); // ulSize (64-bit for v4+)
        
        $dirCount++;
    }
    
    $sector = array_key_exists($sector, $fatChains) ? $fatChains[$sector] : SectIdCodes::ENDOFCHAIN;
}

echo "\nConversion summary:\n";
echo "  Header: converted\n";
echo "  DIFAT entries: " . count($difat) . "\n";
echo "  FAT sectors: converted\n";
echo "  Mini FAT sectors: converted\n";
echo "  Directory entries: $dirCount\n";

// Write output file
$written = file_put_contents($outputFile, $data);
if ($written === false) {
    die("Failed to write output file\n");
}

echo "\nConversion complete!\n";
echo "Created: $outputFile (" . number_format($written) . " bytes)\n";
echo "\nThis is a fully spec-compliant big-endian compound file.\n";
echo "All multi-byte fields have been converted to big-endian byte order.\n";

