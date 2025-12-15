<?php

namespace DanRossiter\BinaryCompoundFile;

/**
 * Byte order constants for binary file parsing.
 */
final class ByteOrder
{
    /** Little-endian byte order (0xFFFE) */
    public const LITTLE_ENDIAN = 0xFFFE;

    /**
     * Big-endian byte order detection value (0xFEFF)
     * This is the value you get when reading a big-endian file's byte order marker
     * using little-endian interpretation. The actual bytes are FF FE which read as
     * LE gives 0xFEFF.
     */
    public const BIG_ENDIAN = 0xFEFF;
}
