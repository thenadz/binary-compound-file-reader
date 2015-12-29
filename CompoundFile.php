<?php
/**
 * References:
 * + http://www.openoffice.org/sc/compdocfileformat.pdf
 * + http://www.digitalpreservation.gov/formats/digformatspecs/WindowsCompoundBinaryFileFormatSpecification.pdf
 */

include 'FileHeader.php';

class CompoundFile {
	private
		$header,
		$fileStream;

	/**
	 * CompoundFile constructor.
	 *
	 * @param $stream resource The stream pointing to the compound file contents.
	 */
	public function __construct( $stream ) {
		$this->fileStream = $stream;
		$this->header     = new FileHeader ( $stream );
	}

	/**
	 * @param $stream_name string The name of the stream.
	 *
	 * @return \DirectoryEntry The entry for the given stream.
	 */
	public function lookupStream( $stream_name ) {
		$dir = null;
		$bytes = 0;
		$next_sect = $this->header->getSectDirStart();
		$difat = $this->getDifat();

		// NOTE: RB-tree reportedly unreliable in some implementations
		// so doing straight iterative directory traversal to be safe
		// http://www.openoffice.org/sc/compdocfileformat.pdf#page=13
		do {
			if ( ( $bytes % $this->getSectSize() ) === 0 ) {
				// reached end of entries w/o finding target
				if ( $next_sect == FileHeader::ENDOFCHAIN ) break;

				$this->seekSector( $next_sect );
				$next_sect = $difat[$next_sect];
			}

			$dir = new DirectoryEntry( $this->header, $this->fileStream );
			$bytes += DirectoryEntry::DIRECTORY_ENTRY_LEN;
		} while ( $dir->getName() !== $stream_name );

		return $dir;
	}

	/**
	 * @return bool Whether the compound file given is valid.
	 */
	public function isValid() {
		return $this->header->isValid();
	}

	/**
	 * @param $index int Seek within the compound file resource to the given sector.
	 */
	private function seekSector( $index ) {
		fseek( $this->fileStream, $this->getSectSize() * $index + 512 );
	}

	/**
	 * @param $index int The index of the sector to be returned.
	 *
	 * @return string The requested sector.
	 */
	public function getSector( $index ) {
		$this->seekSector( $index );
		return fread( $this->fileStream, $this->getSectSize() );
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
		return $this->header->getDifat();
	}

	/**
	 * @return int The size of a single sector.
	 */
	public function getSectSize() {
		return $this->header->getSectSize();
	}
}