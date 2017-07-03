# Compound Files *FAST*

The compound file format goes by a few different names, including "Object Linking and Embedding (OLE) Compound File (CF)",
"Compound Binary File", "Compound Document File", and "OLE2." The underlying concept with this file format is to create
what amounts to a filesystem within a file. The compound file binary format is used for  `.doc`, `.ppt`, and `.xls`
files, among others.

This library was designed to address a lack of a quality options for parsing
[compound file binary format](https://en.wikipedia.org/wiki/Compound_File_Binary_Format) in PHP. When I needed to parse
compound files in PHP I looked around and found one implementation that was both slow made many assumptions that did
not conform to the specification. In implementing this library, speed and standard compliance were at the forefront,
ensuring the broadest compatibility with different file implementation and that when used at scale processing many
files will not kill server performance. The library was also designed to be easily extensible using Object-Oriented best
practices throughout.

To use the library, there are two options depending on your specific needs:
a [stream wrapper](http://php.net/manual/en/class.streamwrapper.php) or raw access to the parsed file fields. Below,
both of these approaches are demonstrated. The stream option provides a nice abstraction if you have functions that
accept a PHP resource that you want to pass in a single stream from a compound file.

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
    
# TODO

Below are the major future tasks I have in mind. Feel free to contribute :)

* Add `WordFile`, `PowerPointFile`, and `ExcelFile` extensions of the existing `CompoundFile` class which will provide
convenience methods to interact with these common compound file types.
* Add unit tests
* Support writing compound files (right now only reading is supported)