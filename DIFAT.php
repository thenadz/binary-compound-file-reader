<?php 
class DIFAT
{
	private 
				$DIFATArray=array();
	//private
	
	const 		
				majorVersion3SectorSize 	= 	512,
				majorVersion4SectorSize 	= 	4096;
	
	static 		$sectorSize;
	
	public function __construct ( OLEHeader $OLEHeader )
	{
		$fullDIFATString 		= 	$OLEHeader -> GetDIFAT ( );
		$fixedFullDIFATString	=	Helpers :: FixHex ( $fullDIFATString );
		echo $fixedFullDIFATString;
	}
	
	public function GetDIFATArray ( )
	{
		return $DIFATArray;
	}

}


?>