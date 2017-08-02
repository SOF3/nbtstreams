nbtstreams
==========

This library implements an NBT parser and an NBT exporter in a stream syle.

Usage (this example uses special indentation for the sake of readability):

```php
$nbtWriter = new NbtWriter("nbtFile.dat");
$nbtWriter
    ->name("Example")
    ->startCompound()
        ->name("AnInt")->writeInt(1234)
        ->name("ReadableString")->writeString("I am readable")
        ->name("RawByteArray")->writeByteArray("\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78")
        ->name("ByteArrayFromFile")->writeByteArrayFromFile("binary-file.dat")
        ->name("ByteArrayWithGenerator");
        $generator = $nbtWriter->generateByteArrayFromFile("binary-file-2.dat");
        do{
            $generator->send(2048); // copy 2048 bytes
            sleep(5);
        }while($generator->valid());
    $nbtWriter->endCompound()
->close();
```

Advantages of using nbtstreams over the traditional NBT class from PocketMine:
* **Performs better** because there is no need to read the whole file before executing parts of it; can spread the file reading into ticks
* **Prevents memory leak** because there is no need to store the whole decompressed file in memory before using/saving it.