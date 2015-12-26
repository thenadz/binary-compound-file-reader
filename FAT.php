<?php

class FAT {
	private
		$fatArray = array(),
		$sectorSize;

	/*		===============================================
	 * 		Construct the entire FAT/SAT from the DIFAT/MSAT
	 * 
	 *///	===============================================
	public function __construct( OLEFile $oleFile ) {
		$oleStream        = $oleFile->Get_OLE_Stream();
		$this->sectorSize = $oleFile->Get_Sector_Size();
		foreach ( $oleFile->Get_DIFAT() as $secID ) {
			if ( $secID != OLEHeader::FREESECT && $secID != OLEHeader::ENDOFCHAIN ) {
				fseek( $oleStream, ( $secID * $this->sectorSize ) + 512 );
				$this->fatArray = array_merge( $this->fatArray, unpack( 'V*', fread( $oleStream, $this->sectorSize ) ) );
			}
		}
	}

	public function Get_FAT() {
		return $this->fatArray;
	}
}