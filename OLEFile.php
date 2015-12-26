<?php
include 'OLEHeader.php';
include 'FAT.php';

class OLEFile {
	const
		DWORD       = 4,
		USHORT      = 2,
		ULONGLONG   = 8,
		GUID        = 16,
		FILETIME    = 8,
		BYTE        = 1,
		WCHAR       = 2;

	private
		$OLEFileName,
		$OLEHeader,
		$fileStream,
		$difatArray,
		$fatArray;


	/* ===============================================================================
	 *  Takes a file stream object and does all the magic.
	 *  Note that Validate Header must be called BEFORE doing ANYTHING ELSE. 
	 *  Validation not only validates the header, but also initializes some variables
	 *  that are necessary for other functions.
	 *  If validation fails, the function returns with an error.
	 * ===============================================================================
	 */
	public function __construct( $OLEFileStream ) {
		$this->fileStream = $OLEFileStream;
		$seekPos          = ftell( $this->fileStream );
		fseek( $this->fileStream, 0 );
		$this->OLEHeader = new OLEHeader ( $this );

		try {
			if ( $this->OLEHeader->Validate_Header() == FALSE ) {
				throw new Exception ( "Header invalid!" );
			}
		} catch ( Exception $e ) {
			ob_end_clean();
			echo $e->getMessage();

			return;
		}


		$this->Build_DIFAT();
		$this->Build_FAT();
		fseek( $this->fileStream, $seekPos );
	}

	/*	=====================
	 *  Returns a file stream object
	 * 	====================
	 */

	private function Build_DIFAT() {
		$seekPos          = ftell( $this->fileStream );
		$this->difatArray = $this->OLEHeader->Get_DIFAT();
		$firstDIFATSecLoc = $this->OLEHeader->Get_First_Difat_Sec_Loc();
		if ( $firstDIFATSecLoc != OLEHeader::ENDOFCHAIN ) {
			fseek( $this->fileStream, ( $firstDIFATSecLoc * $this->Get_Sector_Size() ) + 512 );
			$this->difatArray = array_merge( $this->difatArray, unpack( 'V*', @fread( $this->fileStream, $this->Get_Sector_Size() ) ) );

			for ( $i = 1; $i < $this->OLEHeader->Get_Num_DIFAT_Sectors(); $i ++ ) {
				$last_i = count( $this->difatArray ) - 1;
				$last = $this->difatArray [ $last_i ];
				if ( $last == OLEHeader::ENDOFCHAIN ) break;

				fseek( $this->fileStream, ( $last * $this->Get_Sector_Size() ) + 512 );
				unset ( $this->difatArray [ $last_i ] );
				$this->difatArray = array_merge( $this->difatArray, unpack( 'V*', @fread( $this->fileStream, $this->Get_Sector_Size() ) ) );
			}
		}

		fseek( $this->fileStream, $seekPos );

		return;
	}

	/*	===========================
	 *  Returns a sector of size (specified by $sectorSize) from the compound file, offset from the zero'th position. 
	 *  Use for sequential reads.
	 *	===========================
	 */
	private function Build_FAT() {
		$seekPos        = ftell( $this->fileStream );
		$fatObject      = new FAT ( $this );
		$this->fatArray = $fatObject->Get_FAT();
		fseek( $this->fileStream, $seekPos );

		return;
	}

	/*	===========================
	 * 	Returns a sector of size (specified by sectorSize) from the compound file, offset by the index specified by $offset. 
	 * 	Seeks back to the position where fread left off prior to the function call. 
	 * 	Use this function to get an arbitrary sector.
	 * 	===========================
	 */
	public function Get_OLE_Stream() {
		return $this->fileStream;
	}

	public function Get_Next_Sector() {
		return fread( $this->fileStream, $this->OLEHeader->Get_Sector_Size() );
	}

	public function Get_Sector( $offset = 0 ) {
		$currentOffset = ftell( $this->fileStream );
		fseek( $this->fileStream, $this->Get_Sector_Size() * $offset );
		$sector = fread( $this->fileStream, $this->Get_Sector_Size() );
		fseek( $this->fileStream, $currentOffset );

		return $sector;
	}

	/*		============================================================
	 * 		Builds the DIFAT/MSAT table starting with the header. 	
	 *///	============================================================	

	public function Get_OLE_Header() {
		return $this->OLEHeader;
	}

	/*		============================================================
	 * 		Builds the FAT/SAT table starting with the DIFAT in the header.
	 *///	============================================================

	public function Get_DIFAT() {
		return $this->difatArray;
	}

	public function Get_Sector_Size() {
		return $this->OLEHeader->Get_Sector_Size();
	}

	/*
	 *	=====================
	 *	Close the stream prior to exit
	 *	=====================
	 */
	private function Close_Stream() {
		fclose( $this->fileStream );

		return;
	}
}