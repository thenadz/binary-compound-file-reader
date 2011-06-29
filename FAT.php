<?php

class FAT
{
	private
				$fatArray = array(),
				$sectorSize;
	//private	

	const	
				ENDOFCHAIN 	= 	0xfffffffe	,
				FREESECT 	= 	0xffffffff	,
				FATSECT		= 	0xfffffffd	,
				DIFSECT		= 	0xfffffffc	; 
	//const
				
				
	/*		===============================================
	 * 		Construct the entire FAT/SAT from the DIFAT/MSAT
	 * 
	 *///	===============================================
	public function __construct ( OLEFile $oleFile )
	{
			$oleStream 				= 	$oleFile -> Get_OLE_Stream ( );
			$seekPos				= 	ftell ( $oleStream );
			$this -> sectorSize 	= 	$oleFile -> Get_Sector_Size();
			fseek ( $oleStream, 0 );
			$difatArray 	=	$oleFile -> Get_DIFAT ( );
			foreach( $difatArray as $secID )
			{
				if( $secID != FAT::FREESECT && $secID != FAT::ENDOFCHAIN )
				{
					fseek ( $oleStream, ( $secID *  $this -> sectorSize ) + 512 );
					$readBuf	=	Helpers :: Hex_Str_To_Array ( Helpers :: Fix_Hex ( bin2hex ( fread ( $oleStream, $this -> sectorSize ) ) , 8 ) , 8 );
					for($i=0;$i<count($readBuf);$i++)
					{
						$this -> fatArray [] = $readBuf [$i];
					}
				
				}
			} 	
	}

	public function Get_FAT ( )
	{
		return $this -> fatArray;
	}

}

?>