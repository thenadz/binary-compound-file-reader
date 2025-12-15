# Binary Compound File Reader

[![PHP Version](https://img.shields.io/packagist/php-v/danrossiter/binary-compound-file)](https://packagist.org/packages/danrossiter/binary-compound-file)
[![License](https://img.shields.io/github/license/thenadz/binary-compound-file-reader)](https://github.com/thenadz/binary-compound-file-reader/blob/master/LICENSE.txt)
[![Tests](https://img.shields.io/github/actions/workflow/status/thenadz/binary-compound-file-reader/ci.yml?branch=master&label=tests)](https://github.com/thenadz/binary-compound-file-reader/actions)

A modern, high-performance PHP library for reading Microsoft Compound Binary File Format (CFBF/OLE) documents.

The compound file format goes by several names: "Object Linking and Embedding (OLE) Compound File (CF)", "Compound Binary File", "Compound Document File", and "OLE2." This format creates a filesystem within a file and is used by `.doc`, `.xls`, `.ppt`, and other Microsoft Office files.

This library was designed to address the lack of quality options for parsing [compound file binary format](https://en.wikipedia.org/wiki/Compound_File_Binary_Format) natively in PHP. Existing implementations were either slow or made assumptions that didn't conform to the specification. This library prioritizes **speed** and **standards compliance**, ensuring broad compatibility and excellent performance at scale.

## Features

- **Full CFBF Spec Compliance**: Properly handles FAT, DIFAT, and mini-FAT chain structures
- **Stream Wrapper**: PHP stream wrapper for seamless resource-based access
- **Big-Endian Support**: Correctly parses both little-endian (standard) and big-endian compound files
- **64-bit File Support**: Handles large files with 64-bit ulSize fields in version 4+ files  
- **UTF-16 Encoding**: Properly converts UTF-16LE/BE stream names to UTF-8
- **PHP 7.4+ Compatible**: Works with PHP 7.4, 8.0, 8.1, 8.2, and 8.3+
- **PSR-4 Autoloading**: Composer-based installation and autoloading
- **Fully Tested**: Comprehensive PHPUnit test suite with unit and integration tests

## Installation

Install via Composer:

```bash
composer require danrossiter/binary-compound-file
```

## Usage

To use the library, there are two options depending on your specific needs:
a [stream wrapper](http://php.net/manual/en/class.streamwrapper.php) or raw access to the parsed file fields. Below, both of these approaches are demonstrated. The stream option provides a nice abstraction if you have functions that accept a PHP resource that you want to pass in a single stream from a compound file.

### Basic Usage

```php
use DanRossiter\BinaryCompoundFile\CompoundFile;
use DanRossiter\BinaryCompoundFile\StorageType;

// Open the compound file
$handle = fopen('document.doc', 'rb');
$cfb = new CompoundFile($handle);

// Extract a specific stream
$wordDocStream = $cfb->getDirectory('WordDocument');
$content = $cfb->getStream($wordDocStream);

// Iterate through all streams
$directories = $cfb->getDirectories();
foreach ($directories as $name => $dir) {
    if ($dir->getMse() === StorageType::STREAM) {
        $streamContent = $cfb->getStream($dir);
        echo "Stream: $name, Size: " . strlen($streamContent) . " bytes\n";
    }
}

fclose($handle);
```

### Stream Wrapper

For seamless integration with PHP's stream functions:

```php
use DanRossiter\BinaryCompoundFile\CompoundFileStream;

// Register the stream wrapper
stream_wrapper_register('cfbf', CompoundFileStream::class);

// Access streams using the cfbf:// protocol
$handle = fopen('cfbf://document.doc#WordDocument', 'rb');
$content = stream_get_contents($handle);
fclose($handle);
```

### Examples

See the [`examples/`](examples/) directory for complete working examples:
- [`extract-all-streams.php`](examples/extract-all-streams.php) - Extract all streams to individual files
- [`stream-wrapper.php`](examples/stream-wrapper.php) - Using the cfbf:// stream wrapper
- [`inspect-structure.php`](examples/inspect-structure.php) - Analyze file structure and directory tree
- [`create-bigendian-test.php`](examples/create-bigendian-test.php) - Generate big-endian test files

## Requirements

- PHP 7.4 or higher
- No external dependencies (pure PHP implementation)

## Development

### Running Tests

```bash
composer test
```

### Code Quality

```bash
# Static analysis
composer phpstan

# Code style checking
composer cs-check

# Code style fixing
composer cs-fix
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please see [SECURITY.md](SECURITY.md) for reporting procedures.

## Roadmap

- [ ] Write support (currently read-only)
- [ ] Additional format validation and corruption detection
- [ ] Convenience classes: `WordFile`, `ExcelFile`, `PowerPointFile` with format-specific methods

## License

GPL-3.0-or-later. See [LICENSE.txt](LICENSE.txt) for details.

## Credits

Created by [Dan Rossiter](https://github.com/thenadz)

## Acknowledgments

- [Microsoft's Compound File Binary Format Specification](https://github.com/microsoft/compoundfilereader)
- [OpenOffice Compound Document Format Documentation](http://www.openoffice.org/sc/compdocfileformat.pdf)