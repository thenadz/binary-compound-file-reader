<?php

/*
 * ==============================================================================
 * Class that reads the Compound File Header from the Compound File.
 
 * Please see individual function comment headers for specific implementation 
 * notes.
 * 
 * Note that all comparisons are little-endian.
 * ==============================================================================
 */

class OLEHeader {
	const
		ENDOFCHAIN = 0xfffffffe,
		FREESECT = 0xffffffff,
		FATSECT = 0xfffffffd,
		DIFSECT = 0xfffffffc;

	protected
		$oleHeaderSignature,
		$oleHeaderCLSID,
		$oleMinorVersion,
		$oleMajorVersion,
		$oleByteOrder,
		$oleSectorShift,
		$oleMiniSectorShift,
		$oleReserved,
		$numDirSectors,
		$numFATSectors,
		$firstDirSectorLoc,
		$transactionSigNum,
		$miniStreamCutoffSize,
		$firstMiniFATSectorLoc,
		$numMiniFATSectors,
		$firstDIFATSectorLoc,
		$numDIFATSectors,
		$difat,
		$sectorSize; /* Not in specs. */

	/*	=================================================================
	 * 	Read contents of header and seek back to offset 0.
	 * 	=================================================================
	 */
	public function __construct( OLEFile $oleFile ) {
		static $header_format =
			'PoleHeaderSignature/' .
			'32HoleHeaderCLSID' .
			'voleMinorVersion/' .
			'voleMajorVersion' .
			'voleByteOrder/' .
			'voleSectorShift/' .
			'voleMiniSectorShift/' .
			'12HoleReserved/' .
			'VnumDirSectors/' .
			'VnumFATSectors/' .
			'VfirstDirSectorLoc/' .
			'VtransactionSigNum/' .
			'VminiStreamCutoffSize/' .
			'VfirstMiniFATSectorLoc/' .
			'VnumMiniFATSectors/' .
			'VfirstDIFATSectorLoc/' .
			'VnumDIFATSectors/' .
			'436Vdifat';

		$oleStream = $oleFile->Get_OLE_Stream();
		fseek( $oleStream, 0 );
		$header                      = unpack( $header_format, @fread( $oleStream, 512 ) );
		$this->oleHeaderSignature    = $header['oleHeaderSignature'];
		$this->oleHeaderCLSID        = $header['oleHeaderCLSID'];
		$this->oleMinorVersion       = $header['oleMinorVersion'];
		$this->oleMajorVersion       = $header['oleMajorVersion'];
		$this->oleByteOrder          = $header['oleByteOrder'];
		$this->oleSectorShift        = $header['oleSectorShift'];
		$this->oleMiniSectorShift    = $header['oleMiniSectorShift'];
		$this->oleReserved           = $header['oleReserved'];
		$this->numDirSectors         = $header['numDirSectors'];
		$this->numFATSectors         = $header['numFATSectors'];
		$this->firstDirSectorLoc     = $header['firstDirSectorLoc'];
		$this->transactionSigNum     = $header['transactionSigNum'];
		$this->miniStreamCutoffSize  = $header['miniStreamCutoffSize'];
		$this->firstMiniFATSectorLoc = $header['firstMiniFATSectorLoc'];
		$this->numMiniFATSectors     = $header['numMiniFATSectors'];
		$this->firstDIFATSectorLoc   = $header['firstDIFATSectorLoc'];
		$this->numDIFATSectors       = $header['numDIFATSectors'];
		$this->difat                 = array();
		foreach ( $header as $k => $v ) {
			if ( substr( $k, 0, 5 ) === 'difat' ) {
				$this->difat[ intval( substr( $k, 5 ) ) ] = $v;
			}
		}

		$this->oleStream = $oleStream;
		fseek( $this->oleStream, 0 );
	}

	/*
	 * ==============================================================================
	 * Function that validates the header to prevent further execution if the file is
	 * invalid to begin with.
	 * 
	 * Note that in the binary file, the byte-order is little endian. The comparisons
	 * below take that into account and implement string comparisons with the 
	 * hex-strings in little-endian order. Exceptions to this can be seen in the constructor
	 * where Helers::FixHex() is used to convert little-endian strings to big-endian where arithmetic
	 * is to be performed.
	 * 
	 * numFATSectors, firstDirSectorLoc, firstMiniFATSectorLoc, numMiniFATSectors, 
	 * firstDIFATSectorLoc, numDIFATSectors do not have 'MUST values' according to the specs. 
	 * transactionSigNum is a special case.
	 * ==============================================================================
	 */

	public function Validate_Header() {
		$errorFlag = TRUE;

		if ( $this->oleHeaderSignature != 0xe11ab1a1e011cfd0 ) {
			$errorFlag = FALSE;
		}
		if ( $this->oleHeaderCLSID != 0x00000000000000000000000000000000 ) {
			$errorFlag = FALSE;
		}
		if ( $this->oleMinorVersion != 0x003e ) {
			$errorFlag = FALSE;
		}

		if ( $this->oleMajorVersion != 0x0003
		     && $this->oleMajorVersion != 0x0004
		) {
			$errorFlag = FALSE;
		}

		if ( $this->oleByteOrder != 0xfffe ) {
			$errorFlag = FALSE;
		}
		if ( $this->oleMajorVersion == 0x0003 ) {
			if ( $this->oleSectorShift != 0x0009 ) {
				$errorFlag = FALSE;
			}
		} else if ( $this->oleMajorVersion == 0x0004 ) {
			if ( $this->oleSectorShift != 0x000c ) {
				$errorFlag = FALSE;
			}
		} else {
			$errorFlag = FALSE;
		}

		if ( $this->oleMiniSectorShift != 0x0006 ) {
			$errorFlag = FALSE;
		}
		if ( $this->oleReserved != 0x000000000000 ) {
			$errorFlag = FALSE;
		}
		if ( $this->numDirSectors != 0x00000000 ) {
			if ( $this->oleMajorVersion != 0x0003 ) {
				$errorFlag = FALSE;
			}
		}
		if ( $this->miniStreamCutoffSize != 0x0000001000 ) {
			$errorFlag = FALSE;
		}
		if ( $this->oleMajorVersion == 0x0003 ) {
			$this->sectorSize = 512;
		} else if ( $this->oleMajorVersion == 0x0004 ) {
			$this->sectorSize = 4096;
		} else {
			$errorFlag = FALSE;
		}

		return $errorFlag;
	}

	public function Get_Sector_Size() {
		return $this->sectorSize;
	}

	public function Get_Num_DIFAT_Sectors() {
		return $this->numDIFATSectors;
	}

	public function Get_DIFAT() {
		return $this->difat;
	}

	public function Get_Num_FAT_Sectors() {
		return $this->numFATSectors;
	}

	public function Get_Major_Version() {
		return $this->oleMajorVersion;
	}

	public function Get_Minor_Version() {
		return $this->oleMinorVersion;
	}

	public function Get_First_Dir_Sec_Loc() {
		return $this->firstDirSectorLoc;
	}

	public function Get_First_DIFAT_Sec_Loc() {
		return $this->firstDIFATSectorLoc;
	}
}