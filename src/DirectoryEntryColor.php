<?php

namespace DanRossiter\BinaryCompoundFile;

/**
 * Directory Entry color values for red-black tree navigation.
 */
final class DirectoryEntryColor
{
    /**
     * Red node in the directory tree (used for red-black tree balancing).
     */
    public const RED = 0;

    /**
     * Black node in the directory tree (used for red-black tree balancing).
     */
    public const BLACK = 1;
}
