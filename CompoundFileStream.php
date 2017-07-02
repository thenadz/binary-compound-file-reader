<?php

include_once 'CompoundFile.php';

class CompoundFileStream {
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

	function stream_open($path, $mode, $options, &$opened_path) {
		$split = explode( '#', substr( $path, 6 ) );
		if ( count( $split ) === 2 ) {
			$this->inner_stream = fopen( 'file:/' . $split[0], $mode/*, null, $this->context*/ );
			if ( is_resource( $this->inner_stream ) ) {
				$this->stream_name = $split[1];
				$this->init_compound_file();
			}
		}

		return is_resource( $this->inner_stream ) && isset( $this->dir );
	}

	function stream_read( $count ) {
		if ( $count <= 0 || $this->stream_eof() ) return '';

		if ( !isset( $this->cur_sector ) ) {
			$this->set_current_sector( $this->get_start_sector() );
		}

		$ret = '';
		$chain = $this->dir->isMinor() ? $this->compound_file->getMiniFatChains() : $this->compound_file->getFatChains();

		while( $count >= $this->get_sector_remaining() && !$this->stream_eof() ) {
			$count -= $this->get_sector_remaining();
			$ret .= substr( $this->cur_sector, $this->get_sector_read(), $this->get_sector_remaining() );
			$this->set_current_sector( $chain[$this->cur_sector_i] );
		}
		$ret .= substr( $this->cur_sector, 0, $count );

		return $ret;
	}

	function stream_tell() {
		return $this->position;
	}

	function stream_eof() {
		return $this->cur_sector_i == CompoundFile::ENDOFCHAIN;
	}

	function stream_seek($offset, $whence) {
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
		while ( $remaining > $sector_size ) {
			$sector = $difat[$sector];
			$remaining -= $sector_size;
			if ( $sector == CompoundFile::ENDOFCHAIN ) {
				return false;
			}
		}

		$this->set_current_sector( $sector );
		$this->position = $offset;
	}

	function stream_stat() {
		// TODO: http://php.net/manual/en/function.stat.php
		// We may be able to provide some of these values, but many don't
		// makes sense in the context of a compound file.
		return false;
	}

	private function init_compound_file() {
		$this->compound_file = new CompoundFile( $this->inner_stream );
		if ( $this->compound_file->isValid() ) {
			$this->dir = $this->compound_file->lookupStream( $this->stream_name );
		} else {
			throw new InvalidArgumentException('Provided file is not in a valid format.');
		}
	}

	private function set_current_sector( $i ) {
		$this->cur_sector_i = $i;
		if ( !$this->stream_eof() ) {
			$this->cur_sector = $this->compound_file->getSector( $i, $this->dir->isMinor() );
		}
	}

	private function get_start_sector() {
		return $this->dir->getSectStart();
	}

	private function get_sector_read() {
		return $this->position % $this->dir->getSectSize();
	}

	private function get_sector_remaining() {
		return $this->dir->getSectSize() - $this->get_sector_read();
	}
}