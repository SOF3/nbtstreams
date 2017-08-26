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

if(PHP_INT_SIZE < 8){
	echo "nbtstreams only works on 64-bit systems\n";
	exit(1);
}

interface NbtTagConsts{
	const TAG_End = "\x0";
	const TAG_Byte = "\x1";
	const TAG_Short = "\x2";
	const TAG_Int = "\x3";
	const TAG_Long = "\x4";
	const TAG_Float = "\x5";
	const TAG_Double = "\x6";
	const TAG_ByteArray = "\x7";
	const TAG_String = "\x8";
	const TAG_List = "\x9";
	const TAG_Compound = "\xA";
	const TAG_IntArray = "\xB";
	const TAG_LongArray = "\xC";

	const CONTEXT_LIST = 1;
	const CONTEXT_COMPOUND = 2;
}
