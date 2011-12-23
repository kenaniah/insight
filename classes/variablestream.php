<?php
/**
 * Global variable stream context wrapper
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class VariableStream {

	/**
	 * Tracks the internal position within the resource
	 * @var integer
	 */
	private $pos;

	/**
	 * Tracks the actual stream data
	 * @var binary
	 */
	private $stream;

	/**
	 * Opens a stream resource
	 */
	public function stream_open($path, $mode, $options, &$opened_path) {
		$url = parse_url($path);
		$this->stream = &$GLOBALS[$url["host"]];
		$this->pos = 0;
		return is_string($this->stream);
	}

	/**
	 * Read the resource
	 * @param integer $count
	 */
	public function stream_read($count) {
		$res = substr($this->stream, $this->pos, $count);
		$this->pos += strlen($res);
		return $res;
	}

	/**
	 * Write to the resource
	 * @param binary $data
	 * @return integer length of data written
	 */
	public function stream_write($data){
		$len = strlen($data);
		$this->stream = substr($this->stream, 0, $this->pos) . $data . substr($this->stream, $this->pos += $len);
		return $len;
	}

	/**
	 * Grabs the current position of the steam
	 */
	public function stream_tell() {
		return $this->pos;
	}

	/**
	 * Returns whether or not we have reached the end of the stream
	 */
	public function stream_eof() {
		return $this->pos >= strlen($this->stream);
	}

	/**
	 * Seeks to a current point within the stream
	 * @param integer $offset
	 * @param seektype $whence
	 */
	public function stream_seek($offset, $whence) {
		$len = strlen($this->stream);
		switch ($whence) {
			case SEEK_SET:
				$new_pos = $offset;
				break;
			case SEEK_CUR:
				$new_pos = $this->pos + $offset;
				break;
			case SEEK_END:
				$new_pos = $len + $offset;
				break;
			default:
				return false;
		}
		$res = $new_pos >=0 && $new_pos <= $len;
		if($res) $this->pos = $new_pos;
		return $res;
	}

	/**
	 * Stats the stream resource
	 */
	public function stream_stat(){
		return array(
			'size' => strlen($this->stream)
		);
	}

	/**
	 * Closes the stream
	 */
	public function stream_close(){
		unset($this->stream);
		$this->pos = 0;
	}

}