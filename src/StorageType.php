<?php

namespace DanRossiter\BinaryCompoundFile;

/**
 * Storage Type (STGTY) values for directory entry types.
 */
final class StorageType
{
    /**
     * Empty directory entry (unallocated).
     */
    public const INVALID = 0;

    /**
     * Storage object (like a directory/folder) that can contain other entries.
     */
    public const STORAGE = 1;

    /**
     * Stream object containing user data.
     */
    public const STREAM = 2;

    /**
     * Lock bytes object (not commonly used).
     */
    public const LOCKBYTES = 3;

    /**
     * Property object (not commonly used).
     */
    public const PROPERTY = 4;

    /**
     * Root storage object (always the first directory entry).
     */
    public const ROOT = 5;
}
