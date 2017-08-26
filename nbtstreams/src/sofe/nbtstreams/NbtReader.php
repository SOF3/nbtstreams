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


	public function readName(string &$type = null){
		assert($this->surfaceContext[self::CONTEXT_INDEX_CONTEXT_TYPE] === self::CONTEXT_COMPOUND, "Cannot read name for list context entries");
		assert(!isset($this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE]), "Cannot read name twice name");
		$type = $this->peek(1);
		if($type === self::TAG_End){
			return null;
		}
		$this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE] = $type = $this->read(1);
		return $this->internalReadString();
	}

	public function startCompound(){
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Compound, "Mismatched tag type, was actually \\x" . bin2hex($type));
		$this->pushContext([self::CONTEXT_COMPOUND, null]);
	}

	public function endCompound(){
		assert($this->peek(1) === self::TAG_End);
		$this->read(1);
		$this->popContext(self::CONTEXT_COMPOUND);
	}

	public function startList(string &$subtype = "", int &$size = 0){
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Long, "Mismatched tag type, was actually \\x" . bin2hex($type));
		$subtype = $this->read(1);
		$size = Binary::readInt($this->read(4));
		$this->pushContext([self::CONTEXT_LIST, $subtype, $size]);
	}

	public function endList(){
		assert($this->surfaceContext[self::CONTEXT_INDEX_TAG_COUNT] === 0, "Did not read all list tag entries");
		$this->popContext(self::CONTEXT_LIST);
	}

	public function readValue(string $type){
		static $typeToMethod = [
			self::TAG_Byte => "readByte",
			self::TAG_Short => "readShort",
			self::TAG_Int => "readInt",
			self::TAG_Long => "readLong",
			self::TAG_Float => "readFloat",
			self::TAG_Double => "readDouble",
			self::TAG_ByteArray => "readByteArray",
			self::TAG_String => "readString",
			self::TAG_IntArray => "readIntArray",
			self::TAG_LongArray => "readLongArray",
		];

		if(!isset($typeToMethod[$type])){
			throw new \RuntimeException("Type \\x" . bin2hex($type) . " not supported by readValue");
		}
		$callable = [$this, $typeToMethod[$type]];
		return $callable();
	}

	public function readByte($signed = true) : int{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Byte, "Mismatched tag type, was actually \\x" . bin2hex($type));
		$ord = ord($this->read(1));
		return $signed && ($ord & 0x80) ? ($ord & ~0x7F) : $ord;
	}

	public function readShort() : int{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Short, "Mismatched tag type, was actually \\x" . bin2hex($type));
		return Binary::readSignedShort($this->read(2));
	}

	public function readInt() : int{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Int, "Mismatched tag type, was actually \\x" . bin2hex($type));
		return Binary::readInt($this->read(4));
	}

	public function readLong() : int{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Long, "Mismatched tag type, was actually \\x" . bin2hex($type));
		return Binary::readLong($this->read(8));
	}

	public function readFloat() : float{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Float, "Mismatched tag type, was actually \\x" . bin2hex($type));
		return Binary::readFloat($this->read(4));
	}

	public function readDouble() : float{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_Double, "Mismatched tag type, was actually \\x" . bin2hex($type));
		return Binary::readDouble($this->read(8));
	}

	public function readByteArray() : string{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_ByteArray, "Mismatched tag type, was actually \\x" . bin2hex($type));
		$size = Binary::readInt($this->read(4));
		return $this->read($size);
	}

	public function peekInt() : int{
		return Binary::readInt($this->peek(4));
	}

	public function generateByteArrayReader(int $bufferSize = 2048) : \Generator{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_ByteArray, "Mismatched tag type, was actually \\x" . bin2hex($type));
		$size = Binary::readInt($this->read(4));
		for($i = $size; $i > 0; $i -= strlen($buffer)){
			$buffer = $this->read(min($i, $bufferSize));
			yield $buffer;
		}
	}

	public function readString() : string{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_String, "Mismatched tag type, was actually \\x" . bin2hex($type));
		return $this->internalReadString();
	}

	public function readIntArray() : array{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_IntArray, "Mismatched tag type, was actually \\x" . bin2hex($type));
		$array = [];
		$size = Binary::readInt($this->read(4));
		for($i = 0; $i < $size; ++$i){
			$array[] = Binary::readInt($this->read(4));
		}
		return $array;
	}

	public function readLongArray() : array{
		$type = $this->consumeExpectedType();
		assert($type === self::TAG_LongArray, "Mismatched tag type, was actually \\x" . bin2hex($type));
		$array = [];
		$size = Binary::readInt($this->read(4));
		for($i = 0; $i < $size; ++$i){
			$array[] = Binary::readLong($this->read(8));
		}
		return $array;
	}


	private function consumeExpectedType() : string{
		if($this->surfaceContext[self::CONTEXT_INDEX_CONTEXT_TYPE] === self::CONTEXT_COMPOUND){
			assert(isset($this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE]), "Has not yet read tag type for reading a compound context entry");
			$ret = $this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE];
			$this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE] = null;
			return $ret;
		}

		// CONTEXT_LIST
		if((--$this->surfaceContext[self::CONTEXT_INDEX_TAG_COUNT]) < 0){
			throw new \UnderflowException("The list tag has already ended!");
		}
		return $this->surfaceContext[self::CONTEXT_INDEX_TAG_TYPE];
	}

	private function pushContext(array $context){
		$this->contextStack[] = $this->surfaceContext;
		$this->surfaceContext = $context;
	}

	private function popContext(int $context){
		assert($this->surfaceContext[self::CONTEXT_INDEX_CONTEXT_TYPE] === $context);
		$this->surfaceContext = array_pop($this->contextStack);
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
				throw new \UnderflowException("End of NBT stream");
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
		throw new \LogicException("This exception will never be thrown");
	}

	private function peek(int $length) : string{
		while(strlen($this->inputBuffer) < $this->inputBufferOffset + $length){
			if(gzeof($this->is)){
				throw new \UnderflowException("End of NBT stream");
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
