# Examples

This directory contains working examples demonstrating various ways to use the Binary Compound File Reader library.

## Running Examples

All examples assume you have installed dependencies:

```bash
composer install
```

## Available Examples

### 1. Extract All Streams (`extract-all-streams.php`)

Extracts all streams from a compound file to individual files.

```bash
php examples/extract-all-streams.php
```

**What it demonstrates:**
- Opening a compound file
- Iterating through directory entries
- Filtering for stream types
- Extracting stream content to files

### 2. Stream Wrapper Usage (`stream-wrapper.php`)

Shows how to use the `cfbf://` stream wrapper for seamless integration with PHP's stream functions.

```bash
php examples/stream-wrapper.php
```

**What it demonstrates:**
- Registering the stream wrapper
- Accessing streams via `cfbf://` protocol
- Reading stream content
- Unregistering the wrapper

### 3. Inspect File Structure (`inspect-structure.php`)

Analyzes and displays the internal structure of a compound file.

```bash
php examples/inspect-structure.php
```

**What it demonstrates:**
- Reading file header information
- Detecting byte order (little-endian vs big-endian)
- Displaying directory tree
- Calculating statistics

## Test Files

The examples use these test files from `tests/fixtures/`:
- `Dan Rossiter Resume.doc` - Little-endian compound file
- `Dan Rossiter Resume-BE.doc` - Big-endian compound file (generated)

## Creating Your Own Examples

Basic structure for using the library:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DanRossiter\BinaryCompoundFile\CompoundFile;
use DanRossiter\BinaryCompoundFile\DirectoryEntry;

// Open file
$handle = fopen('yourfile.doc', 'rb');
$cfb = new CompoundFile($handle);

// Access streams
$directories = $cfb->getDirectories();
foreach ($directories as $name => $dir) {
    if ($dir->getMse() === DirectoryEntry::STGTY_STREAM) {
        $content = $cfb->getStream($dir);
        // Process content...
    }
}

fclose($handle);
```

## Additional Resources

- [Main README](../README.md) - Full documentation
- [API Documentation](../docs/) - Detailed API reference
- [Tests](../tests/) - More usage examples in test files
