<?php

namespace DanRossiter\BinaryCompoundFile;

/**
 * Special sector ID codes used in compound binary files.
 */
final class SectIdCodes
{
    /** Free sector, may exist in the file, but is not part of any stream */
    public const FREESECT = -1;

    /** Trailing SecID in a SecID chain */
    public const ENDOFCHAIN = -2;

    /** Sector is used by the sector allocation table */
    public const FATSECT = -3;

    /** Sector is used by the master sector allocation table */
    public const DIFSECT = -4;
}
