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
class OLEHeader
{
	const 
				ENDOFCHAIN 	= 	0xfffffffe	,
				FREESECT 	= 	0xffffffff	,
				FATSECT		= 	0xfffffffd	,
				DIFSECT		= 	0xfffffffc	; 
	//const
	
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
	//protected
	
	/*	=================================================================
	 * 	Read contents of header and seek back to offset 0.
	 * 	=================================================================
	 */		
	public function __construct ( OLEFile $oleFile )
	{
		$oleStream 						= $oleFile -> Get_OLE_Stream ( );
		fseek ( $oleStream , 0 );
		$this -> oleHeaderSignature	 	= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 8  ) ) ) );
		$this -> oleHeaderCLSID 		= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 16 ) ) ) );
		$this -> oleMinorVersion 		= hexdec ( Helpers :: Fix_Hex( bin2hex ( fread ( $oleStream , 2  ) ) ) );
		$this -> oleMajorVersion 		= hexdec ( Helpers :: Fix_Hex( bin2hex ( fread ( $oleStream , 2  ) ) ) );
		$this -> oleByteOrder 			= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 2  ) ) ) );
		$this -> oleSectorShift 		= hexdec ( Helpers :: Fix_Hex( bin2hex ( fread ( $oleStream , 2  ) ) ) );
		$this -> oleMiniSectorShift 	= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 2  ) ) ) );
		$this -> oleReserved 			= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 6  ) ) ) );
		$this -> numDirSectors 			= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> numFATSectors 			= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> firstDirSectorLoc 		= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> transactionSigNum 		= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> miniStreamCutoffSize 	= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> firstMiniFATSectorLoc 	= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> numMiniFATSectors 		= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> firstDIFATSectorLoc 	= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> numDIFATSectors 		= hexdec ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 4 ) ) ) );
		$this -> difat 					= Helpers :: Hex_Str_To_Array ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream , 436 ) ) , 8 ) , 8 ); 
		$this -> oleStream				= $oleStream;
		fseek ( $this -> oleStream , 0 );
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
	
	public function Validate_Header( )
	{
		$errorFlag=true;
		
		if ( $this -> oleHeaderSignature 			!= 0xe11ab1a1e011cfd0 ) 					$errorFlag = false;
		if ( $this -> oleHeaderCLSID 				!= 0x00000000000000000000000000000000 ) 	$errorFlag = false;
		if ( $this -> oleMinorVersion 				!= 0x003e ) 								$errorFlag = false;
		
		if ( $this -> oleMajorVersion 				!= 0x0003 
			 && $this -> oleMajorVersion 			!= 0x0004 ) 								$errorFlag = false; 
			 
		if ( $this -> oleByteOrder 					!= 0xfffe ) 								$errorFlag = false;
		if ( $this -> oleMajorVersion 				== 0x0003 ) {
				   if ( $this -> oleSectorShift  	!= 0x0009 )									$errorFlag = false; 
			} else if ( $this -> oleMajorVersion 	== 0x0004 ) { 
				   if ( $this -> oleSectorShift  	!= 0x000c )			 						$errorFlag = false; 
				} else $errorFlag = false;
			
		if ( $this -> oleMiniSectorShift 			!= 0x0006  ) 								$errorFlag = false;
		if ( $this -> oleReserved 					!= 0x000000000000 ) 						$errorFlag = false;
		if ( $this -> numDirSectors 				!= 0x00000000   ) { 
				   if ( $this->oleMajorVersion 		!= 0x0003 ) 								$errorFlag = false; }
		if ( $this -> miniStreamCutoffSize 			!= 0x0000001000 ) 							$errorFlag = false;
		if ( $this -> oleMajorVersion 				== 0x0003 )     $this -> sectorSize = 512;
		else if ( $this -> oleMajorVersion 			== 0x0004 ) 	$this -> sectorSize = 4096;
		
		return $errorFlag;
	}
	
	public function Get_Sector_Size ( )
	{
		return $this -> sectorSize;
	}
	
	public function Get_Num_DIFAT_Sectors ( )
	{
		return $this -> numDIFATSectors;
	}
	
	public function Get_DIFAT ( )
	{
		return $this -> difat;
	}
	
	public function Get_Num_FAT_Sectors ( )
	{
		return $this -> numFATSectors;
	}
	
	public function Get_Major_Version ( )
	{
		return $this -> oleMajorVersion;
	}
	
	public function Get_Minor_Version ( )
	{
		return $this -> oleMinorVersion;
	}
	
	public function Get_First_Dir_Sec_Loc ( )
	{
		return $this -> firstDirSectorLoc;
	}
	
	public function Get_First_DIFAT_Sec_Loc ( )
	{
		return $this -> firstDIFATSectorLoc;
	}
	
}
?>