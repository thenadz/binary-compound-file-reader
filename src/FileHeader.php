<?php

namespace DanRossiter\BinaryCompoundFile;

/**
 * Deserializes and provides getters for fields in the compound file header.
 */
class FileHeader
{
    public const HEADER_SIZE = 512;

    public const VALID_SIG = 'd0cf11e0a1b11ae1';

    /**
     * @var string Backs the getter.
     */
    private $abSig;

    /**
     * @var int Backs the getter.
     */
    private $minorVersion;

    /**
     * @var int Backs the getter.
     */
    private $dllVersion;

    /**
     * @var int Backs the getter.
     */
    private $byteOrder;

    /**
     * @var int Backs the getter.
     */
    private $sectorShift;

    /**
     * @var int Backs the getter.
     */
    private $sectorSize;

    /**
     * @var int Backs the getter.
     */
    private $minorSectorShift;

    /**
     * @var int Backs the getter.
     */
    private $minorSectorSize;

    /**
     * @var int Backs the getter.
     */
    private $csectFat;

    /**
     * @var int Backs the getter.
     */
    private $sectDirStart;

    /**
     * @var int Backs the getter.
     */
    private $signature;

    /**
     * @var int Backs the getter.
     */
    private $miniSectorCutoff;

    /**
     * @var int Backs the getter.
     */
    private $sectMiniFatStart;

    /**
     * @var int Backs the getter.
     */
    private $csectMiniFat;

    /**
     * @var int Backs the getter.
     */
    private $sectDifStart;

    /**
     * @var int Backs the getter.
     */
    private $csectDif;

    /**
     * @var int[] Backs the getter.
     */
    private $difat = [];

    /**
     * @var bool Whether this file uses big-endian byte order.
     */
    private $isBigEndian = false;

    /**
     * Parse header along with remaining double-indirect FAT members
     * outside of header (if present).
     *
     * @param resource $stream File resource handle.
     */
    public function __construct($stream)
    {
        // Read the header bytes first
        $headerBytes = fread($stream, self::HEADER_SIZE);

        // Check byte order first (offset 28, 2 bytes)
        // Bytes FF FE indicate big-endian (reads as 0xFEFF in LE)
        // Bytes FE FF indicate little-endian (reads as 0xFFFE in LE)
        $byteOrderBytes = substr($headerBytes, 28, 2);
        $byteOrderTest = unpack('v', $byteOrderBytes)[1];
        $isBigEndian = ($byteOrderTest == 0xFEFF);

        // Store the byte order flag first so we can use the helper methods
        $this->isBigEndian = $isBigEndian;

        // Use appropriate unpack format based on byte order
        $v16 = $this->get16BitFormat();
        $V32 = $this->get32BitFormat();

        $header_format =
            'H16abSig/' .                    // 8   bytes
            '@24/' .                         // UUID -- always zero
            "{$v16}1uMinorVersion/" .       // 2   bytes
            "{$v16}1uDllVersion/" .         // 2   bytes
            "{$v16}1uByteOrder/" .          // 2   bytes
            "{$v16}1uSectorShift/" .        // 2   bytes
            "{$v16}1uMiniSectorShift/" .    // 2   bytes
            '@44/' .                         // 10  bytes, reserved & unused
            "{$V32}1csectFat/" .            // 4   bytes
            "{$V32}1sectDirStart/" .        // 4   bytes
            "{$V32}1signature/" .           // 4   bytes
            "{$V32}1ulMiniSectorCutoff/" .  // 4   bytes
            "{$V32}1sectMiniFatStart/" .    // 4   bytes
            "{$V32}1csectMiniFat/" .        // 4   bytes
            "{$V32}1sectDifStart/" .        // 4   bytes
            "{$V32}1csectDif/" .            // 4   bytes
            "{$V32}109sectFat";             // 436 bytes, the SECTs of the first 109 FAT sectors

        $header = unpack($header_format, $headerBytes);
        $this->abSig = $header['abSig'];
        $this->minorVersion = $header['uMinorVersion'];
        $this->dllVersion = $header['uDllVersion'];
        $this->byteOrder = $header['uByteOrder'];
        $this->sectorShift = $header['uSectorShift'];
        $this->sectorSize = 1 << $this->sectorShift;
        $this->minorSectorShift = $header['uMiniSectorShift'];
        $this->minorSectorSize = 1 << $this->minorSectorShift;
        $this->csectFat = $header['csectFat'];
        $this->sectDirStart = BinaryUtils::int32($header['sectDirStart']); // no valid value is negative
        $this->signature = $header['signature'];
        $this->miniSectorCutoff = $header['ulMiniSectorCutoff'];
        $this->sectMiniFatStart = BinaryUtils::int32($header['sectMiniFatStart']); // ENDOFCHAIN if non existent
        $this->csectMiniFat = $header['csectMiniFat'];
        $this->sectDifStart = BinaryUtils::int32($header['sectDifStart']); // ENDOFCHAIN if no adt'l sectors
        $this->csectDif = $header['csectDif'];

        // populate the header-based portion of the master sector allocation table
        for ($i = 1; $i <= 109; $i++) {
            $v = BinaryUtils::int32($header['sectFat' . $i]);

            // NOTE: only possible negative values are special non-sectID values - filter them out
            if ($v >= 0) {
                $this->difat[] = $v;
            }
        }
    }

    /**
     * @return bool Whether the parsed header is valid.
     */
    public function isValid(): bool
    {
        $is_valid = true;

        if (strcasecmp($this->abSig, self::VALID_SIG) !== 0) {
            $is_valid = false;
        }

        if ($this->byteOrder != ByteOrder::LITTLE_ENDIAN && $this->byteOrder != ByteOrder::BIG_ENDIAN) {
            $is_valid = false;
        }

        return $is_valid;
    }

    /**
     * @return string The file signature.
     */
    public function getAbSig(): string
    {
        return $this->abSig;
    }

    /**
     * @return int The minor version.
     */
    public function getMinorVersion(): int
    {
        return $this->minorVersion;
    }

    /**
     * @return int The Major (DLL) version.
     */
    public function getDllVersion(): int
    {
        return $this->dllVersion;
    }

    /**
     * @return int The byte order value.
     */
    public function getByteOrder(): int
    {
        return $this->byteOrder;
    }

    /**
     * @return bool Whether file uses little endian encoding.
     */
    public function isLittleEndian(): bool
    {
        return !$this->isBigEndian();
    }

    /**
     * @return bool Whether file uses big endian encoding.
     * NOTE: This is not used in real-world files, but the spec allows for it.
     */
    public function isBigEndian(): bool
    {
        return $this->isBigEndian;
    }

    /**
     * @return int size of sectors in power-of-two (typically 9, indicating 512-byte sectors)
     */
    public function getSectorShift(): int
    {
        return $this->sectorShift;
    }

    /**
     * @return int The sector size.
     */
    public function getSectSize(): int
    {
        return $this->sectorSize;
    }

    /**
     * @return int size of mini-sectors in power-of-two (typically 6, indicating 64-byte mini-sectors)
     */
    public function getMinorSectorShift(): int
    {
        return $this->minorSectorShift;
    }

    /**
     * @return int Convenience variable derived from minorSectorShift.
     */
    public function getMinorSectorSize(): int
    {
        return $this->minorSectorSize;
    }

    /**
     * @return int number of SECTs in the FAT chain
     */
    public function getCsectFat(): int
    {
        return $this->csectFat;
    }

    /**
     * @return int first SECT in the Directory chain
     */
    public function getSectDirStart(): int
    {
        return $this->sectDirStart;
    }

    /**
     * @return int signature used for transactioning: must be zero. The reference implementation does not support transactioning
     */
    public function getSignature(): int
    {
        return $this->signature;
    }

    /**
     * @return int maximum size for mini-streams: typically 4096 bytes
     */
    public function getMiniSectorCutoff(): int
    {
        return $this->miniSectorCutoff;
    }

    /**
     * @return int first SECT in the mini-FAT chain
     */
    public function getSectMiniFatStart(): int
    {
        return $this->sectMiniFatStart;
    }

    public function hasMiniFat(): bool
    {
        return $this->sectMiniFatStart != SectIdCodes::ENDOFCHAIN;
    }

    /**
     * @return int number of SECTs in the mini-FAT chain
     */
    public function getCsectMiniFat(): int
    {
        return $this->csectMiniFat;
    }

    /**
     * @return int first SECT in the DIF chain
     */
    public function getSectDifStart(): int
    {
        return $this->sectDifStart;
    }

    public function hasDiFat(): bool
    {
        return $this->sectDifStart != SectIdCodes::ENDOFCHAIN;
    }

    /**
     * @return int number of SECTs in the DIF chain
     */
    public function getCsectDif(): int
    {
        return $this->csectDif;
    }

    /**
     * @return int[] The double-indirect FAT (up to the first 109 entries).
     */
    public function getDifat(): array
    {
        return $this->difat;
    }

    /**
     * @return string The appropriate unpack format code for 16-bit unsigned integers ('n' for BE, 'v' for LE).
     */
    public function get16BitFormat(): string
    {
        return $this->isBigEndian ? 'n' : 'v';
    }

    /**
     * @return string The appropriate unpack format code for 32-bit unsigned integers ('N' for BE, 'V' for LE).
     */
    public function get32BitFormat(): string
    {
        return $this->isBigEndian ? 'N' : 'V';
    }
}
