<?php

/**
 * This file is part of GameQ.
 *
 * GameQ is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * GameQ is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Tests;

use GameQ\Exception\ProtocolException;
use ReflectionException;
use ReflectionMethod;

/**
 * Buffer test class
 *
 * @package GameQ\Tests
 */
class Buffer extends TestBase
{
    /**
     * Build a mock Buffer
     *
     * @param string $data
     * @param string $numberType
     *
     * @return \GameQ\Buffer
     */
    protected function buildBuffer(string $data, string $numberType = 'm'): \GameQ\Buffer
    {
        return new \GameQ\Buffer($data, $numberType);
    }

    /**
     * Data provider for all of the integer testing. Loads external files since the file format has to be in
     * ascii format for the tests to work correctly
     *
     * @return list<array{non-empty-string, non-empty-string, non-empty-string, int|float}>
     */
    public static function integerDataProvider(): array
    {
        // Make the base path for the data to test since it has to be in ascii form
        $basePath = sprintf('%s/Providers/Buffer', __DIR__);

        // Build the result array
        $dataSet = [
            [ 'readInt8', 'm', sprintf('%s/8bitunsigned_1.txt', $basePath), 214 ],
            [ 'readInt8', 'm', sprintf('%s/8bitunsigned_2.txt', $basePath), 14 ],
            [ 'readInt8', 'le', sprintf('%s/8bitunsigned_1.txt', $basePath), 214 ],
            [ 'readInt8', 'le', sprintf('%s/8bitunsigned_2.txt', $basePath), 14 ],
            [ 'readInt8Signed', 'm', sprintf('%s/8bitsigned_1.txt', $basePath), 56 ],
            [ 'readInt8Signed', 'm', sprintf('%s/8bitsigned_2.txt', $basePath), -47 ],
            [ 'readInt8Signed', 'le', sprintf('%s/8bitsigned_1.txt', $basePath), 56 ],
            [ 'readInt8Signed', 'le', sprintf('%s/8bitsigned_2.txt', $basePath), -47 ],
            [ 'readInt16', 'm', sprintf('%s/16bitunsigned_1.txt', $basePath), 54844 ],
            [ 'readInt16', 'm', sprintf('%s/16bitunsigned_2.txt', $basePath), 1474 ],
            [ 'readInt16', 'le', sprintf('%s/16bitunsigned_1.txt', $basePath), 54844 ],
            [ 'readInt16', 'le', sprintf('%s/16bitunsigned_2.txt', $basePath), 1474 ],
            [ 'readInt16', 'be', sprintf('%s/16bitunsigned_be_1.txt', $basePath), 54844 ],
            [ 'readInt16', 'be', sprintf('%s/16bitunsigned_be_2.txt', $basePath), 1474 ],
            [ 'readInt16Signed', 'm', sprintf('%s/16bitsigned_1.txt', $basePath), 24574 ],
            [ 'readInt16Signed', 'm', sprintf('%s/16bitsigned_2.txt', $basePath), -5478 ],
            [ 'readInt16Signed', 'le', sprintf('%s/16bitsigned_1.txt', $basePath), 24574 ],
            [ 'readInt16Signed', 'le', sprintf('%s/16bitsigned_2.txt', $basePath), -5478 ],
            [ 'readInt16Signed', 'be', sprintf('%s/16bitsigned_be_1.txt', $basePath), 24574 ],
            [ 'readInt16Signed', 'be', sprintf('%s/16bitsigned_be_2.txt', $basePath), -5478 ],
            [ 'readInt32', 'm', sprintf('%s/32bitunsigned_1.txt', $basePath), 3248547147 ],
            [ 'readInt32', 'm', sprintf('%s/32bitunsigned_2.txt', $basePath), 1247612474 ],
            [ 'readInt32', 'le', sprintf('%s/32bitunsigned_1.txt', $basePath), 3248547147 ],
            [ 'readInt32', 'le', sprintf('%s/32bitunsigned_2.txt', $basePath), 1247612474 ],
            [ 'readInt32', 'be', sprintf('%s/32bitunsigned_be_1.txt', $basePath), 3248547147 ],
            [ 'readInt32', 'be', sprintf('%s/32bitunsigned_be_2.txt', $basePath), 1247612474 ],
            [ 'readInt32Signed', 'm', sprintf('%s/32bitsigned_1.txt', $basePath), 1247965816 ],
            [ 'readInt32Signed', 'm', sprintf('%s/32bitsigned_2.txt', $basePath), -1547872147 ],
            [ 'readInt32Signed', 'le', sprintf('%s/32bitsigned_1.txt', $basePath), 1247965816 ],
            [ 'readInt32Signed', 'le', sprintf('%s/32bitsigned_2.txt', $basePath), -1547872147 ],
            [ 'readInt32Signed', 'be', sprintf('%s/32bitsigned_be_1.txt', $basePath), 1247965816 ],
            [ 'readInt32Signed', 'be', sprintf('%s/32bitsigned_be_2.txt', $basePath), -1547872147 ],
            [ 'readFloat32', 'm', sprintf('%s/32float_1.txt', $basePath), 0.15474000573158264 ],
            [ 'readFloat32', 'm', sprintf('%s/32float_2.txt', $basePath), -254.01409912109375 ],
            [ 'readFloat32', 'le', sprintf('%s/32float_1.txt', $basePath), 0.15474000573158264 ],
            [ 'readFloat32', 'le', sprintf('%s/32float_2.txt', $basePath), -254.01409912109375 ],
            [ 'readFloat32', 'be', sprintf('%s/32float_be_1.txt', $basePath), 0.15474000573158264 ],
            [ 'readFloat32', 'be', sprintf('%s/32float_be_2.txt', $basePath), -254.01409912109375 ],
        ];

        // We are on 64-bit os
        if (PHP_INT_SIZE === 8) {
            // Add 64-bit tests
            $dataSet[] = [ 'readInt64', 'm', sprintf('%s/64bitunsigned_1.txt', $basePath), 90094348778156039 ];
            $dataSet[] = [ 'readInt64', 'm', sprintf('%s/64bitunsigned_2.txt', $basePath), 240 ];
            $dataSet[] = [ 'readInt64', 'le', sprintf('%s/64bitunsigned_1.txt', $basePath), 90094348778156039 ];
            $dataSet[] = [ 'readInt64', 'le', sprintf('%s/64bitunsigned_2.txt', $basePath), 240 ];
            $dataSet[] = [ 'readInt64', 'be', sprintf('%s/64bitunsigned_be_1.txt', $basePath), 90094348778156039 ];
            $dataSet[] = [ 'readInt64', 'be', sprintf('%s/64bitunsigned_be_2.txt', $basePath), 240 ];
        }

        return $dataSet;
    }

    /**
     * Test general methods for the Buffer class
     */
    public function testGeneral(): void
    {
        $data = "Some Kind of buffer";

        $buffer = $this->buildBuffer($data);

        // Test buffer and string are equal
        self::assertEquals($data, $buffer->getData(), 'Test string and buffer are not the same');

        // Test length is set correctly
        self::assertEquals(strlen($data), $buffer->getLength(), 'Test string and buffer length do not match');
    }

    /**
     * Test various buffer reads
     *
     */
    #[\PHPUnit\Framework\Attributes\Depends('testGeneral')]
    public function testRead(): void
    {
        $data = "Buffer of data";

        $buffer = $this->buildBuffer($data);

        // Test look ahead default
        self::assertEquals($data[0], $buffer->lookAhead());

        // Test longer look ahead
        self::assertEquals(substr($data, 0, 4), $buffer->lookAhead(4));

        // Test default is one character
        self::assertEquals($data[0], $buffer->read());

        // Test multiple character read
        self::assertEquals(substr($data, 1, 5), $buffer->read(5));

        // Read last character out of the buffer
        self::assertEquals($data[strlen($data) - 1], $buffer->readLast());

        // Get the remainder of the buffer
        self::assertEquals(substr($data, 6, -1), $buffer->getBuffer());
    }

    /**
     * Test for index positions
     *
     */
    #[\PHPUnit\Framework\Attributes\Depends('testRead')]
    public function testPosition(): void
    {
        $data = "Some like My Strings...";

        $buffer = $this->buildBuffer($data);

        // Test basic index position
        self::assertEquals(0, $buffer->getPosition());

        // Jump in the index
        $buffer->jumpto(8);

        // Make sure the index is correct returned
        self::assertEquals(8, $buffer->getPosition());

        // Reset
        $buffer->jumpto(0);

        // Test skip default
        $buffer->skip();

        self::assertEquals(substr($data, 1), $buffer->getBuffer());

        // Skip multiple
        $buffer->skip(3);

        self::assertEquals(substr($data, 4), $buffer->getBuffer());
    }

    /**
     * Test for proper read exception
     *
     */
    #[\PHPUnit\Framework\Attributes\Depends('testRead')]
    public function testReadException(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("Unable to read length=6 from buffer. Bad protocol format or return?");

        $buffer = $this->buildBuffer("12345");

        // Try to read a longer length than the buffer has in it
        $buffer->read(6);
    }

    public function testNegativeReadIsRejected(): void
    {
        $this->expectException(ProtocolException::class);

        $this->buildBuffer('12345')->read(-1);
    }

    public function testOutOfBoundsSkipIsRejected(): void
    {
        $this->expectException(ProtocolException::class);

        $this->buildBuffer('12345')->skip(6);
    }

    public function testReadLastRejectsAnEmptyBuffer(): void
    {
        $this->expectException(ProtocolException::class);

        $this->buildBuffer('')->readLast();
    }

    /**
     * Test reading some strings
     *
     */
    #[\PHPUnit\Framework\Attributes\Depends('testRead')]
    public function testReadString(): void
    {
        $data = "This is string 1\x00This is string 2\x00";

        $buffer = $this->buildBuffer($data);

        // Read first
        self::assertEquals('This is string 1', $buffer->readString());

        // Read again
        self::assertEquals('This is string 2', $buffer->readString());

        // Reset the index
        $buffer->jumpto(0);

        // Test the read using non-default, this should return the whole string
        self::assertEquals($data, $buffer->readString("\xFF"));
    }

    /**
     * Test reading strings terminated by any of multiple delimiters.
     */
    public function testReadStringMulti(): void
    {
        $buffer = $this->buildBuffer("first\r\nsecond|remaining");
        $delimiter = null;

        self::assertSame('first', $buffer->readStringMulti(['|', "\r\n"], $delimiter));
        self::assertSame("\r\n", $delimiter);
        self::assertSame('second', $buffer->readStringMulti(['|', "\r\n"], $delimiter));
        self::assertSame('|', $delimiter);
        self::assertSame('remaining', $buffer->readStringMulti(['|', "\r\n", ''], $delimiter));
        self::assertNull($delimiter);
        self::assertSame(0, $buffer->getLength());
    }

    /**
     * Test number reads reads
     *
     *
     * @param non-empty-string $method
     * @param non-empty-string $numberType
     * @param non-empty-string $file
     * @throws ProtocolException
     * @throws ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\Depends('testRead')]
    #[\PHPUnit\Framework\Attributes\DataProvider('integerDataProvider')]
    public function testNumberReads(string $method, string $numberType, string $file, int|float $expected): void
    {
        // Make the buffer
        $buffer = $this->buildBuffer(self::fixtureContents($file), $numberType);

        // Run the test
        $reader = new ReflectionMethod($buffer, $method);
        self::assertEquals($expected, $reader->invoke($buffer));

        unset($buffer, $reader);
    }
}
