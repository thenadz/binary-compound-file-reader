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

	function stream_open($path, $mode, $options, &$opened_path)
	{
		$split = explode( '#', $path );
		if ( count( $split ) === 2 ) {
			$this->inner_stream = fopen( $split[0], $mode, null, $this->context );
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
		$difat = $this->compound_file->getDifat();
		while( $count >= $this->get_sector_remaining() ) {
			$count -= $this->get_sector_remaining();
			$ret .= substr( $this->cur_sector, $this->get_sector_read(), $this->get_sector_remaining() );
			$this->set_current_sector( $difat[$this->cur_sector_i] );
		}
		$ret .= substr( $this->cur_sector, 0, $count );

		return $ret;
	}

	function stream_tell() {
		return $this->position;
	}

	function stream_eof() {
		return $this->cur_sector_i == FileHeader::ENDOFCHAIN;
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

		$sector_size = $this->compound_file->getSectSize();
		$sector = $this->get_start_sector();
		$difat = $this->compound_file->getDifat();
		$remaining = $offset;
		while ( $remaining > $sector_size ) {
			$sector = $difat[$sector];
			$remaining -= $sector_size;
			if ( $sector == FileHeader::ENDOFCHAIN ) {
				return false;
			}
		}

		$this->set_current_sector( $sector );
		$this->position = $offset;
	}

	private function init_compound_file() {
		$this->compound_file = new CompoundFile( $this->inner_stream );
		if ( $this->compound_file->isValid() ) {
			$this->dir = $this->compound_file->lookupStream( $this->stream_name );
		}
	}

	private function set_current_sector( $i ) {
		$this->cur_sector_i = $i;
		if ( !$this->stream_eof() ) {
			$this->cur_sector = $this->compound_file->getSector( $i );
		}

	}

	private function get_start_sector() {
		return $this->dir->getSectStart();
	}

	private function get_sector_read() {
		return $this->position % $this->compound_file->getSectSize();
	}

	private function get_sector_remaining() {
		return $this->compound_file->getSectSize() - $this->get_sector_read();
	}
}