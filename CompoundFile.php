<?php
/**
 * References:
 * + http://www.openoffice.org/sc/compdocfileformat.pdf
 * + http://www.digitalpreservation.gov/formats/digformatspecs/WindowsCompoundBinaryFileFormatSpecification.pdf
 */

include_once 'FileHeader.php';
include_once 'DirectoryEntry.php';

class CompoundFile {

	const ENDOFCHAIN = - 2;
	const FREESECT = - 1;
	const FATSECT = - 3;
	const DIFSECT = - 4;

	/** @var resource The file stream containing a compound binary file. */
	private $stream;

	/** @var \FileHeader Backs the getter. Do not access directly. */
	private $header;

	/** @var  \DirectoryEntry[] Backs the getter. Do not access directly. */
	private $directories;

	/** @var int[] Double indirect FAT. */
	private $difat;

	/** @var int[] FAT chains. */
	private $fatChains;

	/** @var int[] mini FAT chains. */
	private $miniFatChains;

	/**
	 * CompoundFile constructor.
	 *
	 * @param $stream resource The stream pointing to the compound file contents.
	 */
	public function __construct( $stream ) {
		$this->stream = $stream;
		$this->header = new FileHeader( $stream );
		$this->populateDifat();
		$this->populateFatChains();
		$this->populateMiniFatChains();
		$this->populateDirectories();
	}

	/**
	 * @param $stream_name string The name of the stream.
	 *
	 * @return \DirectoryEntry The entry for the given stream.
	 */
	public function lookupStream( $stream_name ) {
		$directories = $this->getDirectories();
		return array_key_exists( $stream_name, $directories ) ? $directories[$stream_name] : null;
	}

	/**
	 * @return bool Whether the compound file given is valid.
	 */
	public function isValid() {
		$header = $this->getHeader();
		return $header->isValid();
	}

	/**
	 * @param $index int The index of the sector to be returned.
	 * @param $minor bool Whether we're looking for a minor sector.
	 *
	 * @return string The requested sector.
	 */
	public function getSector( $index, $minor = false ) {
		$size = $minor ? $this->header->getMinorSectorSize() : $this->header->getSectSize();
		$this->seekSector( $index, $minor );
		return fread( $this->stream, $size );
	}

	/**
	 * @param $name string Name of the stream to retrieve.
	 *
	 * @return null|string The contents of the stream or null if no matching stream found.
	 */
	public function getStream( $name ) {
		$ret = null;

		if ( array_key_exists( $name, $this->directories ) ) {
			$ret = '';
			$dir = $this->directories[$name];
			$sector = $dir->getSectStart();
			$chain = $dir->isMinor() ? $this->miniFatChains : $this->fatChains;

			do {
				$ret .= $this->getSector( $sector, $dir->isMinor() );
				$sector = array_key_exists( $sector, $chain ) ? $chain[$sector] : self::ENDOFCHAIN;
			} while ( $sector != self::ENDOFCHAIN );
		}

		return $ret;
	}

	/**
	 * @return FileHeader The file header.
	 */
	public function getHeader() {
		return $this->header;
	}

	/**
	 * @return int[] The difat, including the first 109 entries from the 512-byte
	 * header along with any additional entries that exist outside of the header.
	 */
	public function getDifat() {
		return $this->difat;
	}

	/** @return int[] The FAT chains. */
	public function getFatChains() {
		return $this->fatChains;
	}

	/** @return int[] The mini FAT chains. */
	public function getMiniFatChains() {
		return $this->miniFatChains;
	}

	/**
	 * @return \DirectoryEntry[] The directories contained in this file, keyed by name.
	 */
	public function getDirectories() {
		return $this->directories;
	}

	/** Populates the DIFAT data structure. */
	private function populateDifat() {
		$header = $this->getHeader();
		$this->difat = $header->getDifat();

		$count = $header->getSectSize() / 4;
		$fmt = 'V' . $count;
		$sector = $header->getSectDirStart();
		$i = 0;
		while ( $sector != self::ENDOFCHAIN && $i++ < $header->getCsectDif() ) {
			$values = unpack( $fmt, $this->getSector( $sector ) );
			$sector = $values[$count - 1];
			for ( $j = 0; $j < $count - 1; $j++ ) {
				// TODO: array_merge better?
				$this->difat[] = $values[$j];
			}
		}
	}

	/** Populates the FAT chains. */
	private function populateFatChains() {
		$this->fatChains = array();

		$difat = $this->getDifat();
		$fmt = 'V' . $this->header->getSectSize() / 4;
		foreach ( $difat as $sect ) {
			$values = unpack( $fmt, $this->getSector( $sect ) );
			foreach ( $values as $v ) {
				// TODO: array_merge better?
				$this->fatChains[] = $v;
			}
		}
	}

	/** Populates the mini FAT chains. */
	private function populateMiniFatChains() {
		$this->miniFatChains = array();

		$sector = $this->header->getSectMiniFatStart();
		$fmt = 'V' . $this->header->getSectSize() / 4;
		while ( $sector != self::ENDOFCHAIN ) {
			$values = unpack( $fmt, $this->getSector( $sector ) );
			foreach ( $values as $v ) {
				// TODO: array_merge better?
				$this->miniFatChains[] = $v;
			}

			$sector = array_key_exists( $sector, $this->fatChains ) ? $this->fatChains[$sector] : self::ENDOFCHAIN;
		}
	}

	/** Populates the directory list. */
	private function populateDirectories() {
		$this->directories = array();

		$header = $this->getHeader();
		$sector = $header->getSectDirStart();

		// NOTE: RB-tree reportedly unreliable in some implementations
		// so doing straight iterative directory traversal to be safe
		// http://www.openoffice.org/sc/compdocfileformat.pdf#page=13
		do {
			$this->seekSector( $sector );
			$bytes = 0;
			do {
				$dir = new DirectoryEntry( $this->header, $this->stream );
				$this->directories[$dir->getName()] = $dir;
				$bytes += DirectoryEntry::DIRECTORY_ENTRY_LEN;
			} while ( $bytes < $this->header->getSectSize() );

			$sector = array_key_exists( $sector, $this->fatChains ) ? $this->fatChains[$sector] : self::ENDOFCHAIN;
		} while( $sector != self::ENDOFCHAIN );
	}

	/**
	 * @param $index int Seek within the compound file resource to the given sector.
	 * @param $minor bool Whether we're looking for a minor sector.
	 */
	private function seekSector( $index, $minor = false ) {
		if ( $minor ) {
			$header = $this->header;
			$dir = $this->directories[ DirectoryEntry::ROOT_NAME ];
			$index = $header->getSectSize() * $dir->getSectStart() +
			         $header->getMinorSectorSize() * $index +
			         FileHeader::HEADER_SIZE;
		} else {
			$index = $this->header->getSectSize() * $index + FileHeader::HEADER_SIZE;
		}

		fseek( $this->stream, $index );
	}
}