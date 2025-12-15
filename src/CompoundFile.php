<?php

namespace DanRossiter\BinaryCompoundFile;

/**
 * References:
 * + http://www.openoffice.org/sc/compdocfileformat.pdf
 * + http://www.digitalpreservation.gov/formats/digformatspecs/WindowsCompoundBinaryFileFormatSpecification.pdf
 */
class CompoundFile
{
    /** @var resource The file stream containing a compound binary file. */
    private $handle;

    /** @var \FileHeader Backs the getter. Do not access directly. */
    private $header;

    /** @var \DirectoryEntry[] Backs the getter. Do not access directly. */
    private $directories;

    /** @var \DirectoryEntry The root directory entry, if one exists in file. */
    private $rootDir;

    /** @var int[] Double indirect FAT. */
    private $difat;

    /** @var int[] FAT chains. */
    private $fatChains;

    /** @var int[] mini FAT chains. */
    private $miniFatChains;

    /**
     * CompoundFile constructor.
     *
     * @param resource $handle The stream pointing to the compound file contents.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
        $this->header = new FileHeader($handle);
        $this->populateDifat();
        $this->populateFatChains();
        $this->populateMiniFatChains();
        $this->populateDirectories();
    }

    /**
     * @param string $stream_name The name of the stream.
     *
     * @return DirectoryEntry|null The entry for the given stream.
     */
    public function lookupStream(string $stream_name): ?DirectoryEntry
    {
        $directories = $this->getDirectories();

        return array_key_exists($stream_name, $directories) ? $directories[$stream_name] : null;
    }

    /**
     * @return bool Whether the compound file given is valid.
     */
    public function isValid(): bool
    {
        $header = $this->getHeader();

        return $header->isValid();
    }

    /**
     * @param int $index The index of the sector to be returned.
     * @param bool $minor Whether we're looking for a minor sector.
     *
     * @return string|false The requested sector.
     */
    public function getSector(int $index, bool $minor = false)
    {
        $size = $minor ? $this->header->getMinorSectorSize() : $this->header->getSectSize();
        $this->seekSector($index, $minor);

        return fread($this->handle, $size);
    }

    /**
     * @param DirectoryEntry|string $name_or_dir Name of the stream to retrieve or its directory entry.
     *
     * @return string|null The contents of the stream or null if no matching stream found.
     */
    public function getStream($name_or_dir): ?string
    {
        $ret = null;

        $dir = $name_or_dir;
        if (is_string($name_or_dir)) {
            $dir = $this->getDirectory($name_or_dir);
        }

        if (!is_null($dir)) {
            $sector = $dir->getSectStart();
            $ret = '';
            $size = $dir->getUlSize();

            // Empty or invalid stream
            if ($sector < 0 || $size == 0) {
                return $ret;
            }

            $bytesRead = 0;

            if ($dir->isMinor()) {
                $chain = $this->miniFatChains;
                $sectorSize = $this->header->getMinorSectorSize();
            } else {
                $chain = $this->fatChains;
                $sectorSize = $this->header->getSectSize();
            }

            while ($sector != SectIdCodes::ENDOFCHAIN && $bytesRead < $size) {
                $ret .= $this->getSector($sector, $dir->isMinor());
                $bytesRead += $sectorSize;
                $sector = array_key_exists($sector, $chain) ? $chain[$sector] : SectIdCodes::ENDOFCHAIN;
            }

            // Trim to exact size
            if (strlen($ret) > $size) {
                $ret = substr($ret, 0, $size);
            }
        }

        return $ret;
    }

    /**
     * @return FileHeader The file header.
     */
    public function getHeader(): FileHeader
    {
        return $this->header;
    }

    /**
     * @return int[] The difat, including the first 109 entries from the 512-byte
     * header along with any additional entries that exist outside of the header.
     */
    public function getDifat(): array
    {
        return $this->difat;
    }

    /** @return int[] The FAT chains. */
    public function getFatChains(): array
    {
        return $this->fatChains;
    }

    /** @return int[] The mini FAT chains. */
    public function getMiniFatChains(): array
    {
        return $this->miniFatChains;
    }

    /**
     * @return DirectoryEntry[] The directories contained in this file, keyed by name.
     */
    public function getDirectories(): array
    {
        return $this->directories;
    }

    /**
     * @param string $name The directory name to be matched.
     *
     * @return DirectoryEntry|null The matched directory or null if no match exists.
     */
    public function getDirectory(string $name): ?DirectoryEntry
    {
        return array_key_exists($name, $this->directories) ? $this->directories[$name] : null;
    }

    /**
     * @return resource The file handle being read from.
     */
    protected function getHandle()
    {
        return $this->handle;
    }

    /**
     * Populates the DIFAT (Double Indirect FAT) data structure.
     *
     * Reads the initial 109 DIFAT entries from the header, then follows the DIFAT chain
     * to read any additional sectors if the FAT requires more than 109 entries.
     */
    private function populateDifat(): void
    {
        $header = $this->getHeader();
        $this->difat = $header->getDifat();

        $count = $header->getSectSize() / 4;
        $fmt = $header->get32BitFormat() . $count;
        $sector = $header->getSectDifStart();
        $i = 0;
        while ($sector != SectIdCodes::ENDOFCHAIN && $i++ < $header->getCsectDif()) {
            $values = unpack($fmt, $this->getSector($sector));
            $sector = BinaryUtils::int32($values[$count]);
            for ($j = 1; $j < $count; $j++) {
                $v = BinaryUtils::int32($values[$j]);
                if ($v < 0) {
                    break;
                }

                $this->difat[] = $v;
            }
        }
    }

    /**
     * Populates the FAT (File Allocation Table) chains.
     *
     * Reads all FAT sectors referenced by the DIFAT and builds a complete map
     * of sector chains for navigating the file structure.
     */
    private function populateFatChains(): void
    {
        $this->fatChains = [];

        $difat = $this->getDifat();
        $fmt = $this->header->get32BitFormat() . $this->header->getSectSize() / 4;
        $sectorIndex = 0;
        foreach ($difat as $sect) {
            if ($sect < 0) {
                continue;
            }

            $values = unpack($fmt, $this->getSector($sect));
            foreach ($values as $v) {
                $this->fatChains[$sectorIndex++] = BinaryUtils::int32($v);
            }
        }
    }

    /**
     * Populates the mini FAT chains.
     *
     * Reads the mini FAT sectors (used for streams smaller than the cutoff size)
     * and builds a map of mini sector chains.
     */
    private function populateMiniFatChains(): void
    {
        $this->miniFatChains = [];

        $sector = $this->header->getSectMiniFatStart();
        $fmt = $this->header->get32BitFormat() . $this->header->getSectSize() / 4;
        $miniSectorIndex = 0;
        while ($sector != SectIdCodes::ENDOFCHAIN) {
            $values = unpack($fmt, $this->getSector($sector));
            foreach ($values as $v) {
                $this->miniFatChains[$miniSectorIndex++] = BinaryUtils::int32($v);
            }

            $sector = array_key_exists($sector, $this->fatChains) ? $this->fatChains[$sector] : SectIdCodes::ENDOFCHAIN;
        }
    }

    /**
     * Populates the directory list.
     *
     * Reads all directory entries from the directory sectors, building a complete
     * map of all streams and storage objects. Uses iterative traversal rather than
     * the red-black tree structure due to reliability concerns in some implementations.
     */
    private function populateDirectories(): void
    {
        $this->directories = [];

        $header = $this->getHeader();
        $sector = $header->getSectDirStart();

        // NOTE: RB-tree reportedly unreliable in some implementations
        // so doing straight iterative directory traversal to be safe
        // http://www.openoffice.org/sc/compdocfileformat.pdf#page=13
        do {
            $this->seekSector($sector);
            $bytes = 0;
            do {
                $dir = new DirectoryEntry($this, $this->handle);
                $this->directories[$dir->getPrintableName()] = $dir;
                if ($dir->getMse() == StorageType::ROOT) {
                    $this->rootDir = $dir;
                }

                $bytes += DirectoryEntry::DIRECTORY_ENTRY_LEN;
            } while ($bytes < $this->header->getSectSize());

            $sector = array_key_exists($sector, $this->fatChains) ? $this->fatChains[$sector] : SectIdCodes::ENDOFCHAIN;
        } while ($sector != SectIdCodes::ENDOFCHAIN);
    }

    /**
     * @param int $index Seek within the compound file resource to the given sector.
     * @param bool $minor Whether we're looking for a minor sector.
     */
    protected function seekSector(int $index, bool $minor = false): void
    {
        if ($index < 0) {
            throw new InvalidArgumentException('Sector index must be non-negative.');
        }

        if ($minor) {
            // Mini sectors are stored in the root entry's stream
            // Need to walk the FAT chain to find the actual sector
            $miniSectorSize = $this->header->getMinorSectorSize();
            $sectorSize = $this->header->getSectSize();
            $miniSectorsPerSector = $sectorSize / $miniSectorSize;

            // Which sector of the root stream contains this mini sector?
            $rootSectorIndex = floor($index / $miniSectorsPerSector);
            $offsetInSector = ($index % $miniSectorsPerSector) * $miniSectorSize;

            // Walk the FAT chain to find the actual sector
            $sector = $this->rootDir->getSectStart();
            for ($i = 0; $i < $rootSectorIndex; $i++) {
                if (!array_key_exists($sector, $this->fatChains)) {
                    throw new RuntimeException('Invalid mini sector chain.');
                }
                $sector = $this->fatChains[$sector];
                if ($sector < 0) {
                    throw new RuntimeException('Mini sector chain ended prematurely.');
                }
            }

            $index = $sector * $sectorSize + FileHeader::HEADER_SIZE + $offsetInSector;
        } else {
            $index = $this->header->getSectSize() * $index + FileHeader::HEADER_SIZE;
        }

        fseek($this->handle, $index);
    }

    /**
     * Seeks to the start of the given directory's stream.
     *
     * @param DirectoryEntry $dir The directory to seek.
     */
    protected function seekDirectory(DirectoryEntry $dir): void
    {
        $this->seekSector($dir->getSectStart(), $dir->isMinor());
    }
}
