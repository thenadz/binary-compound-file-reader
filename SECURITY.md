# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within this package, please send an email to Dan Rossiter at bugs@rossiters.org. All security vulnerabilities will be promptly addressed.

Please do not publicly disclose the issue until it has been addressed by the team.

### What to Include

When reporting a vulnerability, please include:

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Any suggested fixes (optional)

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Fix Timeline**: Depends on severity, typically 30 days for non-critical issues

## Security Considerations

### File Parsing

This library parses binary compound files. When using this library:

- **Validate Input**: Always validate that input files come from trusted sources
- **Resource Limits**: Be aware that malformed files could potentially consume excessive memory or CPU
- **Sector Chain Loops**: The library includes protection against infinite loops in FAT chains

### Best Practices

1. **Limit File Size**: Consider implementing maximum file size limits
2. **Timeout Protection**: Use PHP's `set_time_limit()` for long-running operations
3. **Memory Limits**: Monitor memory usage when parsing very large files
4. **Error Handling**: Always wrap file operations in try-catch blocks

## Known Limitations

- Very large files (>2GB on 32-bit systems) may not be fully supported
- Maliciously crafted files with deep FAT chains could impact performance
- Stream wrapper does not enforce read-only access

## Security Updates

Security updates will be released as patch versions (e.g., 1.0.1) and documented in the [CHANGELOG.md](CHANGELOG.md).
