<?php

namespace DanRossiter\BinaryCompoundFile;

/**
 * Binary utility functions for compound file parsing.
 */
class BinaryUtils
{
    /**
     * Convert an unsigned 32-bit integer to signed.
     *
     * @param int $value The unsigned value to convert.
     * @return int The signed 32-bit integer.
     */
    public static function int32(int $value): int
    {
        return ($value ^ 0x80000000) - 0x80000000;
    }
}
