<?php

/*
 *
 * nbtstreams
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace sofe\nbtstreams;

use pocketmine\utils\Binary;

class NbtReader implements NbtTagConsts{
	private $is;
	private $inputBuffer = "";
	private $inputBufferOffset = 0;

	private $contextStack = [];
	private $surfaceContext = [NbtWriter::CONTEXT_COMPOUND, null];
	const CONTEXT_INDEX_CONTEXT_TYPE = 0; // CONTEXT_COMPOUND or CONTEXT_LIST
	const CONTEXT_INDEX_TAG_TYPE = 1; // dynamic string|null for compound entries, consistent string for list entries
	const CONTEXT_INDEX_TAG_COUNT = 2; // void for compound entries, dynamic int for list entries

	public function __construct(string $file){
		$this->is = gzopen($file, "rb");
	}


	public function readName(string &$type = null) : string{
		assert($this->surfaceContext[self::CONTEXT_INDEX_CONTEXT_TYPE] === self::CONTEXT_COMPOUND, "Cannot read name for list context entries");
		$this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE] = $type = $this->read(1);
		return $this->internalReadString();
	}

	public function readByte() : int{

	}


	public function readValue(){
		return $this->readValueByType($this->consumeExpectedType());
	}

	private function consumeExpectedType() : string{
		if($this->surfaceContext[self::CONTEXT_INDEX_CONTEXT_TYPE] === self::CONTEXT_COMPOUND){
			assert(isset($this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE]), "Has not yet read tag type for reading a compound context entry");
			$ret = $this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE];
			$this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE] = null;
			return $ret;
		}else{ // CONTEXT_LIST
			if((--$this->surfaceContext[self::CONTEXT_INDEX_TAG_COUNT]) < 0){
				throw new \UnderflowException("The list tag has already ended!");
			}
			return $this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE];
		}
	}

	private function readValueByType(string $type){

	}


	private function read(int $length) : string{
		if(strlen($this->inputBuffer) >= $this->inputBufferOffset + $length){
			$output = substr($this->inputBuffer, $this->inputBufferOffset, $length);
			$this->inputBufferOffset += $length;
			return $output;
		}
		$output = "";
		while(true){
			if(gzeof($this->is)){
				throw new \UnderflowException();
			}
			$tmp = $this->refreshBuffer();
			$length -= strlen($tmp);
			$output .= $tmp;
			if(strlen($this->inputBuffer) >= $length){
				$output .= substr($this->inputBuffer, 0, $length);
				$this->inputBufferOffset += $length;
				return $output;
			}
		}
		throw new \LogicException();
	}

	private function peek(int $length) : string{
		while(strlen($this->inputBuffer) < $this->inputBufferOffset + $length){
			if(gzeof($this->is)){
				throw new \UnderflowException();
			}
			$this->inputBuffer .= gzread($this->is, 2048);
		}
		return substr($this->inputBuffer, $this->inputBufferOffset, $length);
	}

	private function refreshBuffer() : string{
		$ret = substr($this->inputBuffer, $this->inputBufferOffset);
		$this->inputBuffer = gzread($this->is, 2048);
		$this->inputBufferOffset = 0;
		return $ret;
	}


	private function internalReadString() : string{
		$length = Binary::readShort($this->read(2));
		return $this->read($length);
	}
}
