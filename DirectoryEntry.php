<?php

class DirectoryEntry {

	/**
	 * Length of a single directory entry.
	 */
	const DIRECTORY_ENTRY_LEN = 128;

	/**
	 * STGTY enum values
	 */
	const STGTY_INVALID   = 0,
		  STGTY_STORAGE   = 1,
	      STGTY_STREAM    = 2,
          STGTY_LOCKBYTES = 3,
          STGTY_PROPERTY  = 4,
		  STGTY_ROOT      = 5;

	/**
	 * DE enum values
	 */
	const DE_RED          = 0,
		  DE_BLACK        = 1;

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
	 * @var int Type of object -- taken from STGTY_* consts.
	 */
	private $mse;

	/**
	 * @var int Color -- taken from DE_* consts.
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
	 * @var int The  the child acting as the root of all the children of this element (if mse = STGTY_STORAGE)
	 */
	private $sidChild;

	/**
	 * @var string CLSID of this storage (if mse = STGTY_STORAGE)
	 */
	private $clsid;

	/**
	 * @var int User flags of this storage (if mse = STGTY_STORAGE)
	 */
	private $dwUserFlags;

	/**
	 * @var float[] 'create'/'modify' time-stamps (if mse = STGTY_STORAGE)
	 */
	private $time;

	/**
	 * @var int starting SECT of the stream (if mse = STGTY_STREAM)
	 */
	private $sectStart;

	/**
	 * @var int size of stream in bytes (if mse = STGTY_STREAM)
	 */
	private $ulSize;

	public function __construct( $file_header, $stream ) {
		static $header_format =
			'32vname/' .
			'vcb/' .
			'Cmse/' .
			'Cbflags/' .
			'VsidLeftSib/' .
			'VsidRightSib/' .
			'VsidChild/' .
			'32Hclsid/' .
			'VdwUserFlags/' .
			'2VcreateTime/' .
			'2VmodifyTime/' .
			'VsectStart/' .
			'VulSize';
		$header = unpack( $header_format,  @fread( $stream, self::DIRECTORY_ENTRY_LEN ) );
		for ( $i = 1; $i <= 32; $i++ ) {
			$chr = $header['name' . $i];
			if ( $chr ==  0 ) break;
			$this->name .= chr( $chr );
		}

		$this->cb = $header['cb'];
		$this->mse = $header['mse'];
		$this->bflags = $header['bflags'];
		$this->sidLeftSib = $header['sidLeftSib'];
		$this->sidRightSib = $header['sidRightSib'];
		$this->sidChild = $header['sidChild'];
		$this->clsid = $header['clsid'];
		$this->dwUserFlags = $header['dwUserFlags'];
		$this->time = array(
			'create' => self::getLongLong( $header['createTime1'], $header['createTime2'] ),
			'modify' => self::getLongLong( $header['modifyTime1'], $header['modifyTime2'] ) );
		$this->sectStart = $header['sectStart'];
		$this->ulSize = $header['ulSize'];

		$this->file_header = $file_header;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return int
	 */
	public function getCb() {
		return $this->cb;
	}

	/**
	 * @return int
	 */
	public function getMse() {
		return $this->mse;
	}

	/**
	 * @return int
	 */
	public function getBflags() {
		return $this->bflags;
	}

	/**
	 * @return int
	 */
	public function getSidLeftSib() {
		return $this->sidLeftSib;
	}

	/**
	 * @return int
	 */
	public function getSidRightSib() {
		return $this->sidRightSib;
	}

	/**
	 * @return int
	 */
	public function getSidChild() {
		return $this->sidChild;
	}

	/**
	 * @return string
	 */
	public function getClsid() {
		return $this->clsid;
	}

	/**
	 * @return int
	 */
	public function getDwUserFlags() {
		return $this->dwUserFlags;
	}

	/**
	 * @return float[]
	 */
	public function getTime() {
		return $this->time;
	}

	/**
	 * @return int
	 */
	public function getSectStart() {
		return $this->sectStart;
	}

	/**
	 * @return int
	 */
	public function getUlSize() {
		return $this->ulSize;
	}

	public function getLeftSib() {
		return $this->getEntryBySid( $this->sidLeftSib );
	}

	public function getRightSib() {
		return $this->getEntryBySid( $this->sidRightSib );
	}

	public function getChildSib() {
		return $this->getEntryBySid( $this->sidChild );
	}

	private function getEntryBySid( $sid ) {
		$offset = $sid * self::DIRECTORY_ENTRY_LEN;
		$difat = $this->file_header->getDifat();
		$sector_size = $this->file_header->getSectSize();
		$sector = $this->file_header->getSectDirStart();
		$sector_count = floor( $offset / $sector_size );
		while ( $sector_count-- > 0 ) {
			$sector = $difat[$sector];
		}
		$offset %= $sector_size;
		fseek( $this->stream, $sector * $sector_size + 512 + $offset );
		return new DirectoryEntry( $this->file_header, $this->stream );
	}

	/**
	 * @param $high int High order 32-bits
	 * @param $low int Low order 32-bits
	 *
	 * @return number The value as an int or float depending on int size.
	 */
	private static function getLongLong( $high, $low ) {
		if ( PHP_INT_SIZE >= 8 ) {
			return ( $high << 32 ) | $low;
		} else {
			return $high * pow( 2, 32 ) + $low;
		}
	}
}

