# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-15

### Added
- Initial public release
- Full CFBF specification compliance
- Big-endian file support
- 64-bit file size support (version 4+)
- UTF-16LE/BE stream name encoding
- PHP stream wrapper (`cfbf://` protocol)
- PSR-4 autoloading with Composer
- Comprehensive PHPUnit test suite
- PHPStan level 8 static analysis
- PHP-CS-Fixer for PSR-12 code style
- GitHub Actions CI/CD pipeline

### Features
- Parse little-endian and big-endian compound files
- Handle FAT, DIFAT, and mini-FAT chain structures
- Extract all streams from compound files
- Stream wrapper for resource-based access
- PHP 7.4+ compatibility with broad version support

### Technical
- Type-safe constants via final classes (StorageType, ByteOrder, SectIdCodes, DirectoryEntryColor)
- Added helper methods for byte-order-aware unpacking
- Optimized sector chain traversal
- Proper handling of ulSize field in big-endian files
- Separate name storage (preserved) from display (printable)

[1.0.0]: https://github.com/danrossiter/binary-compound-file-reader/releases/tag/v1.0.0
