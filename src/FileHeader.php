<?php

/**
 * Deserializes and provides getters for fields in the compound file header.
 */
class FileHeader {

	const HEADER_SIZE = 512;

	const VALID_SIG = 'd0cf11e0a1b11ae1';

	const LITTLE_ENDIAN_BYTE_ORDER = 0xFFFE;

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
	private $difat = array();

	/**
	 * Parse header along with remaining double-indirect FAT members
	 * outside of header (if present).
	 */
	public function __construct( $stream ) {
		$header_format =
			'H16abSig/' .            // 8   bytes
			'@24/' .                 // UUID -- always zero
			'v1uMinorVersion/' .     // 2   bytes
			'v1uDllVersion/' .       // 2   bytes
			'v1uByteOrder/' .        // 2   bytes
			'v1uSectorShift/' .      // 2   bytes
			'v1uMiniSectorShift/' .  // 2   bytes
			'@44/' .                 // 10  bytes, reserved & unused
			'V1csectFat/' .          // 4   bytes
			'V1sectDirStart/' .      // 4   bytes
			'V1signature/' .         // 4   bytes
			'V1ulMiniSectorCutoff/' .// 4   bytes
			'V1sectMiniFatStart/' .  // 4   bytes
			'V1csectMiniFat/' .      // 4   bytes
			'V1sectDifStart/' .      // 4   bytes
			'V1csectDif/' .          // 4   bytes
			'V109sectFat';           // 436 bytes, the SECTs of the first 109 FAT sectors

		$header                      = unpack( $header_format, @fread( $stream, self::HEADER_SIZE ) );
		$this->abSig                 = $header['abSig'];
		$this->minorVersion          = $header['uMinorVersion'];
		$this->dllVersion            = $header['uDllVersion'];
		$this->byteOrder             = $header['uByteOrder'];
		$this->sectorShift           = $header['uSectorShift'];
		$this->sectorSize            = 1 << $this->sectorShift;
		$this->minorSectorShift      = $header['uMiniSectorShift'];
		$this->minorSectorSize       = 1 << $this->minorSectorShift;
		$this->csectFat              = $header['csectFat'];
		$this->sectDirStart          = $header['sectDirStart'];
		$this->signature             = $header['signature'];
		$this->miniSectorCutoff      = $header['ulMiniSectorCutoff'];
		$this->sectMiniFatStart      = $header['sectMiniFatStart'];
		$this->csectMiniFat          = $header['csectMiniFat'];
		$this->sectDifStart          = $header['sectDifStart'];
		$this->csectDif              = $header['csectDif'];

		for ( $i = 1; $i <= 109; $i++ ) {
			$v = $header['sectFat' . $i];
			if ( $v == CompoundFile::FREESECT ) break;
			$this->difat[] = $v;
		}
	}

	/**
	 * @return bool Whether the parsed header is valid.
	 */
	public function isValid() {
		$is_valid = true;

		if ( strcasecmp( $this->abSig, self::VALID_SIG ) !== 0 ) {
			$is_valid = false;
		}

		if ( $this->byteOrder != 0xfffe && $this->byteOrder != 0xffff ) {
			$is_valid = false;
		}

		return $is_valid;
	}

	/**
	 * @return string The file signature.
	 */
	public function getAbSig() {
		return $this->abSig;
	}

	/**
	 * @return int The minor version.
	 */
	public function getMinorVersion() {
		return $this->minorVersion;
	}

	/**
	 * @return int The Major (DLL) version.
	 */
	public function getDllVersion() {
		return $this->dllVersion;
	}

	/**
	 * @return mixed
	 */
	public function getByteOrder() {
		return $this->byteOrder;
	}

	/**
	 * @return bool Whether file uses little endian encoding.
	 */
	public function isLittleEndian() {
		return $this->byteOrder === self::LITTLE_ENDIAN_BYTE_ORDER;
	}

	/**
	 * @return int size of sectors in power-of-two (typically 9, indicating 512-byte sectors)
	 */
	public function getSectorShift() {
		return $this->sectorShift;
	}

	/**
	 * @return int The sector size.
	 */
	public function getSectSize() {
		return $this->sectorSize;
	}

	/**
	 * @return int size of mini-sectors in power-of-two (typically 6, indicating 64-byte mini-sectors)
	 */
	public function getMinorSectorShift() {
		return $this->minorSectorShift;
	}

	/**
	 * @return int Convenience variable derived from minorSectorShift.
	 */
	public function getMinorSectorSize() {
		return $this->minorSectorSize;
	}

	/**
	 * @return int number of SECTs in the FAT chain
	 */
	public function getCsectFat() {
		return $this->csectFat;
	}

	/**
	 * @return int first SECT in the Directory chain
	 */
	public function getSectDirStart() {
		return $this->sectDirStart;
	}

	/**
	 * @return int signature used for transactioning: must be zero. The reference implementation does not support transactioning
	 */
	public function getSignature() {
		return $this->signature;
	}

	/**
	 * @return int maximum size for mini-streams: typically 4096 bytes
	 */
	public function getMiniSectorCutoff() {
		return $this->miniSectorCutoff;
	}

	/**
	 * @return int first SECT in the mini-FAT chain
	 */
	public function getSectMiniFatStart() {
		return $this->sectMiniFatStart;
	}

	/**
	 * @return int number of SECTs in the mini-FAT chain
	 */
	public function getCsectMiniFat() {
		return $this->csectMiniFat;
	}

	/**
	 * @return int first SECT in the DIF chain
	 */
	public function getSectDifStart() {
		return $this->sectDifStart;
	}

	/**
	 * @return int number of SECTs in the DIF chain
	 */
	public function getCsectDif() {
		return $this->csectDif;
	}

	/**
	 * @return int[] The double-indirect FAT (up to the first 109 entries).
	 */
	public function getDifat() {
		return $this->difat;
	}
}