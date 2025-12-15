<?php

namespace DanRossiter\BinaryCompoundFile;

class DirectoryEntry
{
    /**
     * Length of a single directory entry.
     */
    public const DIRECTORY_ENTRY_LEN = 128;

    /**
     * @var CompoundFile The compound file containing this directory.
     */
    private $compoundFile;

    /**
     * @var FileHeader The file header.
     */
    private $file_header;

    /**
     * @var resource The stream.
     */
    private $stream;

    /**
     * @var string The element name
     */
    private $name = '';

    /**
     * @var int Length of the element name in characters (not bytes).
     */
    private $cb;

    /**
     * @var int Type of object (StorageType constant).
     */
    private $mse;

    /**
     * @var int Color (DirectoryEntryColor constant).
     */
    private $bflags;

    /**
     * @var int The left sibling of this element in the directory tree.
     */
    private $sidLeftSib;

    /**
     * @var int The right sibling of this element in the directory tree.
     */
    private $sidRightSib;

    /**
     * @var int The child acting as the root of all the children of this element (if mse = StorageType::STORAGE).
     */
    private $sidChild;

    /**
     * @var string CLSID of this storage (if mse = StorageType::STORAGE).
     */
    private $clsid;

    /**
     * @var int User flags of this storage (if mse = StorageType::STORAGE).
     */
    private $dwUserFlags;

    /**
     * @var float[] 'create'/'modify' time-stamps (if mse = StorageType::STORAGE).
     */
    private $time;

    /**
     * @var int Starting SECT of the stream (if mse = StorageType::STREAM).
     */
    private $sectStart;

    /**
     * @var int Size of stream in bytes (if mse = StorageType::STREAM).
     */
    private $ulSize;

    /**
     * @var bool whether directory is in the minor FAT.
     */
    private $isMinor;

    /**
     * DirectoryEntry constructor.
     *
     * @param CompoundFile $compoundFile The compound file containing this directory.
     * @param resource $stream The stream containing the file. Will be progressed by DIRECTORY_ENTRY_LEN.
     */
    public function __construct(CompoundFile $compoundFile, $stream)
    {
        $this->compoundFile = $compoundFile;
        $this->file_header = $compoundFile->getHeader();

        // Use appropriate unpack format based on byte order
        $isBigEndian = $this->file_header->isBigEndian();
        $v16 = $this->file_header->get16BitFormat();
        $V32 = $this->file_header->get32BitFormat();

        $header_format =
            'a64name/' .                     // 64 bytes for name (UTF-16LE or UTF-16BE)
            "{$v16}1cb/" .
            'C1mse/' .
            'C1bflags/' .
            "{$V32}1sidLeftSib/" .
            "{$V32}1sidRightSib/" .
            "{$V32}1sidChild/" .
            'H32clsid/' .
            "{$V32}1dwUserFlags/" .
            "{$V32}2createTime/" .
            "{$V32}2modifyTime/" .
            "{$V32}1sectStart/" .
            "{$V32}2ulSize";                 // 8 bytes for size (64-bit for version 4+)
        $header = unpack($header_format, @fread($stream, self::DIRECTORY_ENTRY_LEN));

        // Convert UTF-16 name to UTF-8 (LE or BE depending on file byte order)
        // Clamp cb to 64 bytes max to prevent reading beyond name field
        $nameLength = min($header['cb'] > 0 ? $header['cb'] : 64, 64);
        $nameBytes = substr($header['name'], 0, $nameLength);
        $encoding = $isBigEndian ? 'UTF-16BE' : 'UTF-16LE';
        $converted = iconv($encoding, 'UTF-8//IGNORE', $nameBytes);
        // Store original name with only trailing nulls removed
        $this->name = $converted !== false ? rtrim($converted, "\x00") : '';

        $this->cb = $header['cb'];
        $this->mse = $header['mse'];
        $this->bflags = $header['bflags'];
        $this->sidLeftSib = BinaryUtils::int32($header['sidLeftSib']);
        $this->sidRightSib = BinaryUtils::int32($header['sidRightSib']);
        $this->sidChild = BinaryUtils::int32($header['sidChild']);
        $this->clsid = $header['clsid'];
        $this->dwUserFlags = $header['dwUserFlags'];
        $this->time = [
            'create' => self::getLongLong($header['createTime1'], $header['createTime2'], $isBigEndian),
            'modify' => self::getLongLong($header['modifyTime1'], $header['modifyTime2'], $isBigEndian),
        ];
        $this->sectStart = BinaryUtils::int32($header['sectStart']);

        // ulSize is 64-bit for version 4+ files, 32-bit for version 3
        // Negative sectStart means empty/invalid stream
        if ($this->sectStart >= 0) {
            $fileVersion = $compoundFile->getHeader()->getDllVersion();
            if ($fileVersion >= 4) {
                // 64-bit size for version 4+ files
                $this->ulSize = self::getLongLong($header['ulSize1'], $header['ulSize2'], $isBigEndian);
            } else {
                // For version 3, only low 32 bits are used
                // For big-endian, low 32 bits are in ulSize2, not ulSize1
                $this->ulSize = $isBigEndian ? $header['ulSize2'] : $header['ulSize1'];
            }
        } else {
            $this->ulSize = 0;
        }

        $this->isMinor = $this->ulSize < $this->file_header->getMiniSectorCutoff() && $this->mse !== StorageType::ROOT;

        $this->stream = $stream;
    }

    /**
     * @return bool Whether directory is in the minor FAT.
     */
    public function isMinor(): bool
    {
        return $this->isMinor;
    }

    /**
     * @return int The sector size for this directory.
     */
    public function getSectSize(): int
    {
        return $this->isMinor() ? $this->file_header->getMinorSectorSize() : $this->file_header->getSectSize();
    }

    /**
     * @return string The directory name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**     * Returns a printable version of the name with control characters removed.
     *
     * @return string The printable element name.
     */
    public function getPrintableName(): string
    {
        // Strip control characters (0x00-0x06) for display purposes
        return trim($this->name, "\x00\x01\x02\x03\x04\x05\x06");
    }

    /**     * @return int The length of the element name in characters (not bytes).
     */
    public function getCb(): int
    {
        return $this->cb;
    }

    /**
     * @return int The storage type (StorageType constant).
     */
    public function getMse()
    {
        return $this->mse;
    }

    /**
     * @return int The color (DirectoryEntryColor constant).
     */
    public function getBflags()
    {
        return $this->bflags;
    }

    /**
     * @return int The SID of the left sibling in the directory tree.
     */
    public function getSidLeftSib(): int
    {
        return $this->sidLeftSib;
    }

    /**
     * @return int The SID of the right sibling in the directory tree.
     */
    public function getSidRightSib(): int
    {
        return $this->sidRightSib;
    }

    /**
     * @return int The SID of the child (root of children for storage entries).
     */
    public function getSidChild(): int
    {
        return $this->sidChild;
    }

    /**
     * @return string The CLSID (Class ID) of this storage object.
     */
    public function getClsid(): string
    {
        return $this->clsid;
    }

    /**
     * @return int User-defined flags for this storage object.
     */
    public function getDwUserFlags(): int
    {
        return $this->dwUserFlags;
    }

    /**
     * @return float[] Array with 'create' and 'modify' timestamps.
     */
    public function getTime(): array
    {
        return $this->time;
    }

    /**
     * @return int The starting sector of the stream.
     */
    public function getSectStart(): int
    {
        return $this->sectStart;
    }

    /**
     * @return int The size of the stream in bytes.
     */
    public function getUlSize(): int
    {
        return $this->ulSize;
    }

    /**
     * @return DirectoryEntry|null The left sibling directory entry, or null if none exists.
     */
    public function getLeftSib(): ?DirectoryEntry
    {
        return $this->getEntryBySid($this->sidLeftSib);
    }

    /**
     * @return DirectoryEntry|null The right sibling directory entry, or null if none exists.
     */
    public function getRightSib(): ?DirectoryEntry
    {
        return $this->getEntryBySid($this->sidRightSib);
    }

    /**
     * @return DirectoryEntry|null The child directory entry (root of children), or null if none exists.
     */
    public function getChildSib(): ?DirectoryEntry
    {
        return $this->getEntryBySid($this->sidChild);
    }

    /**
     * Retrieves a directory entry by its Stream ID (SID).
     *
     * Walks through the FAT chain to locate the sector containing the directory entry
     * at the specified SID, then reads and returns the entry.
     *
     * @param int $sid The Stream ID of the directory entry to retrieve.
     * @return DirectoryEntry|null The directory entry, or null if SID is invalid or not found.
     */
    private function getEntryBySid(int $sid): ?DirectoryEntry
    {
        if ($sid < 0) {
            return null;
        }

        $offset = $sid * self::DIRECTORY_ENTRY_LEN;
        $sector_size = $this->file_header->getSectSize();
        $sector = $this->file_header->getSectDirStart();
        $fatChains = $this->compoundFile->getFatChains();

        // Walk through sectors using FAT chains
        $entries_per_sector = $sector_size / self::DIRECTORY_ENTRY_LEN;
        $target_sector_index = floor($sid / $entries_per_sector);
        $entry_offset = ($sid % $entries_per_sector) * self::DIRECTORY_ENTRY_LEN;

        for ($i = 0; $i < $target_sector_index; $i++) {
            if (!array_key_exists($sector, $fatChains)) {
                return null;
            }
            $sector = $fatChains[$sector];
            if ($sector < 0) {
                return null;
            }
        }

        fseek($this->stream, $sector * $sector_size + FileHeader::HEADER_SIZE + $entry_offset);

        return new DirectoryEntry($this->compoundFile, $this->stream);
    }

    /**
     * Combines two 32-bit values into a 64-bit value, handling byte order.
     *
     * @param int $value1 First 32-bit value from unpack.
     * @param int $value2 Second 32-bit value from unpack.
     * @param bool $isBigEndian Whether the source data is big-endian.
     * @return int|float The combined 64-bit value (int on 64-bit PHP, float on 32-bit PHP).
     */
    private static function getLongLong(int $value1, int $value2, bool $isBigEndian)
    {
        // For big-endian, unpack gives [high, low] in correct order
        // For little-endian, unpack gives [low, high] so we need to swap
        if ($isBigEndian) {
            $high = $value1;
            $low = $value2;
        } else {
            $high = $value2;
            $low = $value1;
        }

        if (PHP_INT_SIZE >= 8) {
            return ($high << 32) | $low;
        } else {
            return $high * pow(2, 32) + $low;
        }
    }
}
