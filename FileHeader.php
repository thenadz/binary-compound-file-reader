<?php

/**
 * Deserializes and provides getters for fields in the compound file header.
 */
class FileHeader {
	const
		ENDOFCHAIN = 0xfffffffe,
		FREESECT   = 0xffffffff,
		FATSECT    = 0xfffffffd,
		DIFSECT    = 0xfffffffc;

	/**
	 * @var string d0cf11e0a1b11ae1
	 */
	private $abSig;

	/**
	 * @var string
	 */
	private $clid;

	/**
	 * @var int The minor version.
	 */
	private $minorVersion;

	/**
	 * @var int The Major (DLL) version.
	 */
	private $dllVersion;

	/**
	 * @var int 0xFFFE indicates Intel byte-ordering
	 */
	private $byteOrder;

	/**
	 * @var int size of sectors in power-of-two (typically 9, indicating 512-byte sectors)
	 */
	private $sectorShift;

	/**
	 * @var int size of mini-sectors in power-of-two (typically 6, indicating 64-byte mini-sectors)
	 */
	private $minorSectorShift;

	/**
	 * @var int number of SECTs in the FAT chain
	 */
	private $csectFat;

	/**
	 * @var int first SECT in the Directory chain
	 */
	private $sectDirStart;

	/**
	 * @var int signature used for transactioning: must be zero. The reference implementation does not support transactioning
	 */
	private $signature;

	/**
	 * @var int maximum size for mini-streams: typically 4096 bytes
	 */
	private $miniSectorCutoff;

	/**
	 * @var int first SECT in the mini-FAT chain
	 */
	private $sectMiniFatStart;

	/**
	 * @var int number of SECTs in the mini-FAT chain
	 */
	private $csectMiniFat;

	/**
	 * @var int first SECT in the DIF chain
	 */
	private $sectDifStart;

	/**
	 * @var int number of SECTs in the DIF chain
	 */
	private $csectDif;

	/**
	 * @var int[] The double-indirect FAT.
	 */
	private $difat = array();

	/**
	 * Parse header along with remaining double-indirect FAT members
	 * outside of header (if present).
	 */
	public function __construct( $stream ) {
		static $header_format =
			'16HabSig/' .            // 8   bytes
			'32Hclid/' .             // 16  bytes
			'vuMinorVersion/' .      // 2   bytes
			'vuDllVersion' .         // 2   bytes
			'vuByteOrder/' .         // 2   bytes
			'vuSectorShift/' .       // 2   bytes
			'vuMiniSectorShift/' .   // 2   bytes
			'@44/' .                 // 10  bytes, reserved & unused
			'VcsectFat/' .           // 4   bytes
			'VsectDirStart/' .       // 4   bytes
			'Vsignature/' .          // 4   bytes
			'VulMiniSectorCutoff/' . // 4   bytes
			'VsectMiniFatStart/' .   // 4   bytes
			'VcsectMiniFat/' .       // 4   bytes
			'VsectDifStart/' .       // 4   bytes
			'VcsectDif/' .           // 4   bytes
			'109VsectFat';           // 436 bytes, the SECTs of the first 109 FAT sectors

		$header                      = unpack( $header_format, @fread( $stream, 512 ) );
		$this->abSig                 = $header['abSig'];
		$this->clid                  = $header['clid'];
		$this->minorVersion          = $header['uMinorVersion'];
		$this->dllVersion            = $header['uDllVersion'];
		$this->byteOrder             = $header['uByteOrder'];
		$this->sectorShift           = $header['uSectorShift'];
		$this->minorSectorShift      = $header['uMiniSectorShift'];
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
			if ( $v == self::FREESECT ) break;
			$this->difat[] = $v;
		}

		$this->difat[] = $this->sectDifStart;
		while ( ( $sect = array_pop( $this->difat ) ) != self::ENDOFCHAIN ) {
			fseek( $stream, ( $sect << $this->sectorShift ) + 512 );
			foreach ( unpack( 'V*', @fread( $stream, $this->getSectSize() ) ) as $sect ) {
				$this->difat[] = $sect;
				if ( $sect == self::ENDOFCHAIN ) break;
			}
		}
	}

	/**
	 * @return bool Whether the parsed header is valid.
	 */
	public function isValid() {
		$is_valid = true;

		if ( strcasecmp( $this->abSig, 'd0cf11e0a1b11ae1' ) !== 0 ) {
			$is_valid = false;
		}
		if ( $this->clid != '00000000000000000000000000000000' ) {
			$is_valid = false;
		}

		if ( $this->byteOrder != 0xfffe ) {
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
	 * @return mixed
	 */
	public function getClid() {
		return $this->clid;
	}

	/**
	 * @return mixed
	 */
	public function getMinorVersion() {
		return $this->minorVersion;
	}

	/**
	 * @return mixed
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
	 * @return mixed
	 */
	public function getSectorShift() {
		return $this->sectorShift;
	}

	/**
	 * @return mixed
	 */
	public function getMinorSectorShift() {
		return $this->minorSectorShift;
	}

	/**
	 * @return mixed
	 */
	public function getCsectFat() {
		return $this->csectFat;
	}

	/**
	 * @return mixed
	 */
	public function getSectDirStart() {
		return $this->sectDirStart;
	}

	/**
	 * @return mixed
	 */
	public function getSignature() {
		return $this->signature;
	}

	/**
	 * @return mixed
	 */
	public function getMiniSectorCutoff() {
		return $this->miniSectorCutoff;
	}

	/**
	 * @return mixed
	 */
	public function getSectMiniFatStart() {
		return $this->sectMiniFatStart;
	}

	/**
	 * @return mixed
	 */
	public function getCsectMiniFat() {
		return $this->csectMiniFat;
	}

	/**
	 * @return mixed
	 */
	public function getSectDifStart() {
		return $this->sectDifStart;
	}

	/**
	 * @return mixed
	 */
	public function getCsectDif() {
		return $this->csectDif;
	}

	/**
	 * @return int[] The double-indirect FAT.
	 */
	public function getDifat() {
		return $this->difat;
	}

	/**
	 * @return int The sector size.
	 */
	public function getSectSize() {
		return 1 << $this->sectorShift;
	}
}