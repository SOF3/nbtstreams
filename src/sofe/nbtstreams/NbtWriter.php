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

class NbtWriter implements NbtTagConsts{
	private $os;

	private $contextStack = [];
	private $surfaceContext = NbtWriter::CONTEXT_COMPOUND;
	private $countStack = [];
	private $surfaceCount = [];

	/** @var string|null */
	private $name = null;

	public function __construct(string $file){
		$this->os = gzopen($file, "wb");
	}


	public function startList(int $size, int $type) : NbtWriter{
		$this->writeTagHeader(self::TAG_List);
		gzwrite($this->os, chr($type) . Binary::writeInt($size));
		$this->pushContext(NbtWriter::CONTEXT_LIST);

		assert(call_user_func(function() use ($size){
			$this->countStack[] = $this->surfaceCount;
			$this->surfaceCount = $size;
			return true;
		}));

		return $this;
	}

	public function endList() : NbtWriter{
		$this->popContext(NbtWriter::CONTEXT_LIST);

		assert($this->surfaceCount === 0, "Did not write enough entries for a list tag");

		assert(call_user_func(function(){
			$this->surfaceCount = array_pop($this->countStack);
			return true;
		}));

		return $this;
	}

	public function startCompound() : NbtWriter{
		$this->writeTagHeader(self::TAG_Compound);
		$this->pushContext(NbtWriter::CONTEXT_COMPOUND);
		return $this;
	}

	public function endCompound() : NbtWriter{
		$this->popContext(NbtWriter::CONTEXT_COMPOUND);
		gzwrite($this->os, "\12");
		return $this;
	}


	public function name(string $name) : NbtWriter{
		assert(!isset($this->name), "Writing name twice without writing the value");
		$this->name = $name;
	}
	

	public function writeByte(int $byte) : NbtWriter{
		$this->writeTagHeader(self::TAG_Byte);
		gzwrite($this->os, chr($byte));
		return $this;
	}

	public function writeShort(int $short) : NbtWriter{
		$this->writeTagHeader(self::TAG_Short);
		gzwrite($this->os, Binary::writeShort($short));
		return $this;
	}

	public function writeInt(int $int) : NbtWriter{
		$this->writeTagHeader(self::TAG_Int);
		gzwrite($this->os, Binary::writeInt($int));
		return $this;
	}

	public function writeLong(int $long) : NbtWriter{
		$this->writeTagHeader(self::TAG_Long);
		gzwrite($this->os, Binary::writeLong($long));
		return $this;
	}

	public function writeFloat(float $float) : NbtWriter{
		$this->writeTagHeader(self::TAG_Float);
		gzwrite($this->os, Binary::writeFloat($float));
		return $this;
	}

	public function writeDouble(float $double) : NbtWriter{
		$this->writeTagHeader(self::TAG_Double);
		gzwrite($this->os, Binary::writeDouble($double));
		return $this;
	}

	public function writeByteArray(string $buffer) : NbtWriter{
		$this->writeTagHeader(self::TAG_ByteArray);
		gzwrite($this->os, Binary::writeInt(strlen($buffer)) . $buffer);
		return $this;
	}

	public function writeByteArrayFromFile(string $file) : NbtWriter{
		$this->writeTagHeader(self::TAG_ByteArray);
		gzwrite($this->os, Binary::writeInt($expectedSize = filesize($file)));
		$fh = fopen($file, "rb");
		$size = 0;
		while(!feof($fh)){
			gzwrite($this->os, $buffer = fread($fh, 2048));
			$size += strlen($buffer);
		}
		assert($expectedSize === $size);
		fclose($fh);
		return $this;
	}

	public function startByteArrayWriterFromFile(string $file) : \Generator{
		$this->writeTagHeader(self::TAG_ByteArray);
		gzwrite($this->os, $expectedSize = Binary::writeInt(filesize($file)));
		$fh = fopen($file, "rb");
		while(!feof($fh)){
			$bufferSize = (yield) ?? 1;
			$buffer = fread($fh, $bufferSize);
			if($bufferSize === strlen($buffer))
		}
		fclose($fh);
//		does not return $this to prevent confusion
	}

	public function writeString(string $string) : NbtWriter{
		$this->writeTagHeader(self::TAG_String);
		$this->internalWriteString($string);
		return $this;
	}

	public function writeIntArray(array $ints) : NbtWriter{
		$this->writeTagHeader(self::TAG_IntArray);
		gzwrite($this->os, Binary::writeInt(count($ints)) . pack("N*", ...$ints));
		return $this;
	}

	public function writeLongArray(array $longs) : NbtWriter{
		$this->writeTagHeader(self::TAG_LongArray);
		gzwrite($this->os, Binary::writeInt(count($longs)) . pack("J*", ...$longs));
		return $this;
	}


	private function writeTagHeader(string $type){
		assert(strlen($type) === 1);
		assert(call_user_func(function(){
			if($this->surfaceContext === NbtWriter::CONTEXT_LIST){
				--$this->surfaceCount;
				if($this->surfaceCount < 0){
					return false;
				}
			}
			return true;
		}), "Wrote too many entries for a list tag");
		if($this->surfaceContext !== NbtWriter::CONTEXT_LIST){
			gzwrite($this->os, chr($type));
			assert($this->name !== null, "name must be set for compound tag entry");
			$this->internalWriteString($this->name);
			$this->name = null;
		}
	}

	private function internalWriteString(string $string){
		gzwrite($this->os, Binary::writeShort(strlen($string)) . $string);
	}

	private function pushContext(int $context){
		$this->contextStack[] = $this->surfaceContext;
		$this->surfaceContext = $context;
	}

	private function popContext(int $context){
		assert($this->surfaceContext === $context);
		$this->surfaceContext = array_pop($this->contextStack);
	}

	public function close(){
		gzclose($this->os);
	}
}
