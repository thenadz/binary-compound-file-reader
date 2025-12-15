<?php

namespace DanRossiter\BinaryCompoundFile;

class CompoundFileStream
{
    public $context;

    /**
     * @var int The position in the stream.
     */
    private $position = 0;

    /**
     * @var bool|resource The result of opening inner stream.
     */
    private $inner_stream = false;

    /**
     * @var string The name of the stream.
     */
    private $stream_name;

    /**
     * @var CompoundFile The compound file instance.
     */
    private $compound_file;

    /**
     * @var DirectoryEntry The directory entry for requested stream.
     */
    private $dir;

    /**
     * @var string The most-recently-read sector.
     */
    private $cur_sector;

    /**
     * @var int The most-recently-read sector index.
     */
    private $cur_sector_i;

    /**
     * Opens a stream to a specific stream within a compound file.
     *
     * @param string $path The path in format: cfbf://file.doc#StreamName
     * @param string $mode The file mode (only read modes supported).
     * @param int $options Stream options bitmask.
     * @param string|null &$opened_path Reference to store the opened path.
     * @return bool True if stream opened successfully, false otherwise.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        // pull off URI scheme
        $split = explode('://', $path, 2);
        $path = (count($split) === 2) ? $split[1] : $split[0];

        // split file path from stream name
        $split = explode('#', $path, 2);

        // both file path and stream name are required
        if (count($split) === 2) {
            $this->inner_stream = fopen('file://' . $split[0], $mode/*, null, $this->context*/);
            if (is_resource($this->inner_stream)) {
                $this->stream_name = $split[1];
                $this->init_compound_file();
            }
        }

        return is_resource($this->inner_stream) && isset($this->dir);
    }

    /**
     * Reads data from the stream.
     *
     * @param int $count Number of bytes to read.
     * @return string The data read from the stream.
     */
    public function stream_read(int $count): string
    {
        if ($count <= 0 || $this->stream_eof()) {
            return '';
        }

        if (!isset($this->cur_sector)) {
            $this->set_current_sector($this->get_start_sector());
        }

        $ret = '';
        $chain = $this->dir->isMinor() ? $this->compound_file->getMiniFatChains() : $this->compound_file->getFatChains();

        while (!$this->stream_eof() && $count >= $this->get_sector_remaining()) {
            $count -= $this->get_sector_remaining();
            $ret .= substr($this->cur_sector, $this->get_sector_read(), $this->get_sector_remaining());
            $this->set_current_sector($chain[$this->cur_sector_i]);
        }

        if (!$this->stream_eof()) {
            $ret .= substr($this->cur_sector, 0, $count);
        }

        return $ret;
    }

    /**
     * Returns the current position in the stream.
     *
     * @return int The current byte offset.
     */
    public function stream_tell(): int
    {
        return $this->position;
    }

    /**
     * Tests for end-of-file on the stream.
     *
     * @return bool True if at end of stream, false otherwise.
     */
    public function stream_eof(): bool
    {
        return $this->cur_sector_i == SectIdCodes::ENDOFCHAIN;
    }

    /**
     * Seeks to a specific position in the stream.
     *
     * @param int $offset The offset to seek to.
     * @param int $whence SEEK_SET, SEEK_CUR, or SEEK_END.
     * @return bool True on success, false on failure.
     */
    public function stream_seek(int $offset, int $whence): bool
    {
        switch ($whence) {
            case SEEK_SET:
                break;

            case SEEK_CUR:
                $offset += $this->position;

                break;

            case SEEK_END:
            default:
                return false;
        }

        $sector_size = $this->dir->getSectSize();
        $sector = $this->get_start_sector();
        $difat = $this->compound_file->getDifat();
        $remaining = $offset;
        while ($remaining > $sector_size) {
            $sector = $difat[$sector];
            $remaining -= $sector_size;
            if ($sector == SectIdCodes::ENDOFCHAIN) {
                return false;
            }
        }

        $this->set_current_sector($sector);
        $this->position = $offset;

        return true;
    }

    /**
     * Retrieves information about the stream.
     *
     * Currently not implemented - most stat() fields (device, inode, permissions, etc.)
     * don't have meaningful equivalents within compound file streams.
     *
     * @return array|false Always returns false (not implemented).
     */
    public function stream_stat()
    {
        // Many stat values don't make sense for compound file streams
        return false;
    }

    /**
     * Initializes the compound file instance and locates the requested stream.
     */
    private function init_compound_file(): void
    {
        $this->compound_file = new CompoundFile($this->inner_stream);
        if ($this->compound_file->isValid()) {
            $this->dir = $this->compound_file->lookupStream($this->stream_name);
        } else {
            throw new InvalidArgumentException('Provided file is not in a valid format.');
        }
    }

    /**
     * Sets the current sector and loads its data.
     *
     * @param int $i The sector index to set as current.
     */
    private function set_current_sector(int $i): void
    {
        $this->cur_sector_i = $i;
        if (!$this->stream_eof()) {
            $this->cur_sector = $this->compound_file->getSector($i, $this->dir->isMinor());
        }
    }

    /**
     * Gets the starting sector of the stream.
     *
     * @return int The starting sector index.
     */
    private function get_start_sector(): int
    {
        return $this->dir->getSectStart();
    }

    /**
     * Gets the number of bytes already read from the current sector.
     *
     * @return int The offset within the current sector.
     */
    private function get_sector_read(): int
    {
        return $this->position % $this->dir->getSectSize();
    }

    /**
     * Gets the number of bytes remaining in the current sector.
     *
     * @return int The number of unread bytes in the current sector.
     */
    private function get_sector_remaining(): int
    {
        return $this->dir->getSectSize() - $this->get_sector_read();
    }
}
