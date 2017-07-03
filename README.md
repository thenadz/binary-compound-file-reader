# Compound Files *FAST*

This library was designed to address a lack of a quality options for parsing
[compound file binary format](https://en.wikipedia.org/wiki/Compound_File_Binary_Format) in PHP. The compound
file binary format is used for `.doc`, `.ppt`, and `.xls` files, among others.

To use the library, there are two options depending on your specific needs:
a [stream wrapper](http://php.net/manual/en/class.streamwrapper.php) or raw access to the parsed file fields. Below,
both of these approaches are demonstrated.

## Stream Wrapper
    if ( in_array( 'cfbf', stream_get_wrappers() ) ) {
        stream_wrapper_unregister( 'cfbf' );
     }
     stream_wrapper_register( 'cfbf', 'CompoundFileStream' );
     
     // "WordDocument" is the name of the stream within the CFBF file
     $fp = fopen( 'cfbf://' . $filePath . '#WordDocument', 'r' );
     $content = stream_get_contents( $fp );
 
 ## Raw File Access
    $fp = fopen( $filePath, 'r' );
    $cfb = new CompoundFile( $fp );
    
     // "WordDocument" is the name of the stream within the CFBF file
    $content = $cfb->getStream( 'WordDocument' );