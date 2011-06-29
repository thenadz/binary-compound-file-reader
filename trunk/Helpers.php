<?php 
/*
 *	======================================================================================== 
 * 	A library of functions to simplify a bunch of things like small-endian to big-endian conversions,
 *  and some more specific functions to return hex strings in a certain format. * 
 * 	========================================================================================
 */
class Helpers
{
	/*
	 *	===============================================================
	 *	Converts small-endian hex strings to big-endian. Returns a string. 
	 * 	===============================================================
	 */
	public static function Fix_Hex ( $hexString , $chunkSize = 1)
	{
		$fixedHexString="";
		if ( $chunkSize == 1 ){ 
				$processingString_Array = str_split ( $hexString );
				for($i=count($processingString_Array)-1;$i>0;$i=$i-2)
					{
						$fixedHexString .= $processingString_Array[$i-1].$processingString_Array[$i];
					}	
		}
		else {
				$processingString_MasterArray = array ( );
				for($i=0;$i<strlen($hexString);$i=$i+$chunkSize)
				{
					$processingString_MasterArray [] = substr ( $hexString , $i , $chunkSize );	
				}

				foreach($processingString_MasterArray as $processingString_MasterArrayChunk)
				{
					$processingString_Array = str_split ( $processingString_MasterArrayChunk );
					for($i=count($processingString_Array)-1;$i>=0;$i=$i-2)
					{
						$fixedHexString .= $processingString_Array[$i-1].$processingString_Array[$i];
					}	
				}
		}
				
		return $fixedHexString;
	}

	public static function Hex_Str_To_Array ( $hexString, $chunkSize )
	{
		$processingString_MasterArray = array ();
		for($i=0;$i<strlen($hexString);$i=$i+$chunkSize)
				{
					$processingString_MasterArray [] 	= hexdec(substr ( $hexString , $i , $chunkSize ) );	
				}
		return $processingString_MasterArray;
	} 
	
	public static function Remove_Empty_Hex_Entries ( $inputHexString )
	{
		$inputArray		=	array ( );
		$outputArray	=	array ( );
		$limit 			=	strlen($inputHexString);
		
		for($i=0;$i<$limit;$i=$i+8)
		{
			$subString 	=	substr ( $inputHexString , $i , 8 );
			if ( hexdec ( $subString ) != 0xffffffff && hexdec ( $subString ) != 0xfffffffe)	$outputArray [] = $subString;
		}

		return $outputArray;
	}
	
	public static function Hex_Dump ( $input )
	{
		if (is_array($input))
		{
			for($i=0;$i<count($input);$i++)
			{
				$input [$i] = dechex ( $input [$i] );
			}
			return $input;
		}
		else return dechex ( $input );
	}
	

	public static function Clean_Hex_Dump ( $input )
	{
		if (is_array($input))
		{
			for($i=0;$i<count($input);$i++)
			{
				if ($input[$i]!=0xffffffff )
				$output [$i] = dechex ( $input [$i] );
			}
			return $output;
		}
		else return dechex ( $input );
	}
		
	/*		===============================================================
	 * 		FOR TESTING ONLY
	 * 		Use to measure performance of functions, script.
	 *///	===============================================================
	public static function diff_microtime($mt_old,$mt_new)
	{
	  list($old_usec, $old_sec) = explode(' ',$mt_old);
	  list($new_usec, $new_sec) = explode(' ',$mt_new);
	  $old_mt = ((float)$old_usec + (float)$old_sec);
	  $new_mt = ((float)$new_usec + (float)$new_sec);
	  return number_format($new_mt - $old_mt,4);
	}
}

?>