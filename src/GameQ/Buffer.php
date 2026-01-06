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
 *
 *
 */

namespace GameQ;

use GameQ\Exception\ProtocolException;

/**
 * Class Buffer
 *
 * Read specific byte sequences from a provided string or Buffer
 *
 * @package GameQ
 *
 * @author  Austin Bischoff <austin@codebeard.com>
 * @author  Aidan Lister <aidan@php.net>
 * @author  Tom Buskens <t.buskens@deviation.nl>
 */
class Buffer
{
    /**
     * Constants for the byte code types we need to read as
     */
    public const NUMBER_TYPE_BIGENDIAN = 'be';
    public const NUMBER_TYPE_LITTLEENDIAN = 'le';

    /**
     * The number type we use for reading integers.  Defaults to little endian
     */
    private string $number_type;

    /**
     * The original data
     */
    private string $data;

    /**
     * The original data
     */
    private int $length;

    /**
     * Position of pointer
     */
    private int $index = 0;

    public function __construct(string $data, string $number_type = self::NUMBER_TYPE_LITTLEENDIAN)
    {
        $this->number_type = $number_type;
        $this->data = $data;
        $this->length = strlen($data);
    }

    /**
     * Return all the data
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Return data currently in the buffer
     */
    public function getBuffer(): string
    {
        return substr($this->data, $this->index);
    }

    /**
     * Returns the number of bytes in the buffer
     */
    public function getLength(): int
    {
        return max($this->length - $this->index, 0);
    }

    /**
     * Read from the buffer
     *
     * @throws ProtocolException
     */
    public function read(int $length = 1): string
    {
        if (($length + $this->index) > $this->length) {
            throw new ProtocolException("Unable to read length=$length from buffer. Bad protocol format or return?");
        }

        $string = substr($this->data, $this->index, $length);
        $this->index += $length;

        return $string;
    }

    /**
     * Read the last character from the buffer
     *
     * Unlike the other read functions, this function actually removes
     * the character from the buffer.
     */
    public function readLast(): string
    {
        $len = strlen($this->data);
        $string = $this->data[strlen($this->data) - 1];
        $this->data = substr($this->data, 0, $len - 1);
        --$this->length;

        return $string;
    }

    /**
     * Look at the buffer, but don't remove
     */
    public function lookAhead(int $length = 1): string
    {
        return substr($this->data, $this->index, $length);
    }

    /**
     * Skip forward in the buffer
     */
    public function skip(int $length = 1): void
    {
        $this->index += $length;
    }

    /**
     * Jump to a specific position in the buffer,
     * will not jump past end of buffer
     */
    public function jumpto(int $index): void
    {
        $this->index = min($index, $this->length - 1);
    }

    /**
     * Get the current pointer position
     */
    public function getPosition(): int
    {
        return $this->index;
    }

    /**
     * Read from buffer until delimiter is reached
     *
     * If not found, return everything
     *
     * @throws ProtocolException
     */
    public function readString(string $delim = "\x00"): string
    {
        // Get position of delimiter
        $len = strpos($this->data, $delim, min($this->index, $this->length));

        // If it is not found then return whole buffer
        if ($len === false) {
            return $this->read(strlen($this->data) - $this->index);
        }

        // Read the string and remove the delimiter
        $string = $this->read($len - $this->index);
        ++$this->index;

        return $string;
    }

    /**
     * Reads a pascal string from the buffer
     *
     * @param int  $offset      Number of bits to cut off the end
     * @param bool $read_offset True if the data after the offset is to be read
     *
     * @throws ProtocolException
     */
    public function readPascalString(int $offset = 0, bool $read_offset = false): string
    {
        // Get the proper offset
        $len = $this->readInt8();
        $offset = max($len - $offset, 0);

        // Read the data
        if ($read_offset) {
            return $this->read($offset);
        }

        return substr($this->read($len), 0, $offset);
    }

    /**
     * Read from buffer until any of the delimiters is reached
     *
     * If not found, return everything
     *
     * @throws ProtocolException
     *
     * @todo: Check to see if this is even used anymore
     */
    public function readStringMulti(array $delims, ?string &$delimfound = null): string
    {
        // Get position of delimiters
        $pos = [];
        foreach ($delims as $delim) {
            if ($index = strpos($this->data, $delim, min($this->index, $this->length))) {
                $pos[] = $index;
            }
        }

        // If none are found then return whole buffer
        if (empty($pos)) {
            return $this->read(strlen($this->data) - $this->index);
        }

        // Read the string and remove the delimiter
        sort($pos);
        $string = $this->read($pos[0] - $this->index);
        $delimfound = $this->read();

        return $string;
    }

    /**
     * Read an 8-bit unsigned integer
     *
     * @throws ProtocolException
     */
    public function readInt8(): int
    {

        $int = unpack('Cint', $this->read());

        return $int['int'];
    }

    /**
     * Read and 8-bit signed integer
     *
     * @throws ProtocolException
     */
    public function readInt8Signed(): int
    {

        $int = unpack('cint', $this->read());

        return $int['int'];
    }

    /**
     * Read a 16-bit unsigned integer
     *
     * @throws ProtocolException
     */
    public function readInt16(): int
    {
        // Change the integer type we are looking up
        $type = match ($this->number_type) {
            self::NUMBER_TYPE_BIGENDIAN => 'nint',
            self::NUMBER_TYPE_LITTLEENDIAN => 'vint',
            default => 'Sint',
        };

        $int = unpack($type, $this->read(2));

        return $int['int'];
    }

    /**
     * Read a 16-bit signed integer
     *
     * @throws ProtocolException
     */
    public function readInt16Signed(): int
    {
        // Read the data into a string
        $string = $this->read(2);

        // For big endian we need to reverse the bytes
        if ($this->number_type === self::NUMBER_TYPE_BIGENDIAN) {
            $string = strrev($string);
        }

        $int = unpack('sint', $string);

        unset($string);

        return $int['int'];
    }

    /**
     * Read a 32-bit unsigned integer
     *
     * @throws ProtocolException
     */
    public function readInt32($length = 4): int
    {
        // Change the integer type we are looking up
        $littleEndian = null;
        switch ($this->number_type) {
            case self::NUMBER_TYPE_BIGENDIAN:
                $type = 'N';
                $littleEndian = false;
                break;

            case self::NUMBER_TYPE_LITTLEENDIAN:
                $type = 'V';
                $littleEndian = true;
                break;

            default:
                $type = 'L';
        }

        // read from the buffer and append/prepend empty bytes for shortened int32
        $corrected = $this->read($length);

        // Unpack the number
        $int = unpack($type . 'int', self::extendBinaryString($corrected, 4, $littleEndian));

        return $int['int'];
    }

    /**
     * Read a 32-bit signed integer
     *
     * @throws ProtocolException
     */
    public function readInt32Signed(): int
    {
        // Read the data into a string
        $string = $this->read(4);

        // For big endian we need to reverse the bytes
        if ($this->number_type === self::NUMBER_TYPE_BIGENDIAN) {
            $string = strrev($string);
        }

        $int = unpack('lint', $string);

        unset($string);

        return $int['int'];
    }

    /**
     * Read a 64-bit unsigned integer
     *
     * @throws ProtocolException
     */
    public function readInt64(): int
    {
        // We have the pack 64-bit codes available. See: http://php.net/manual/en/function.pack.php
        if (PHP_INT_SIZE === 8 && version_compare(PHP_VERSION, '5.6.3') >= 0) {
            // Change the integer type we are looking up
            $type = match ($this->number_type) {
                self::NUMBER_TYPE_BIGENDIAN => 'Jint',
                self::NUMBER_TYPE_LITTLEENDIAN => 'Pint',
                default => 'Qint',
            };

            $int64 = unpack($type, $this->read(8));

            $int = $int64['int'];

            unset($int64);
        } else {
            if ($this->number_type === self::NUMBER_TYPE_BIGENDIAN) {
                $high = $this->readInt32();
                $low = $this->readInt32();
            } else {
                $low = $this->readInt32();
                $high = $this->readInt32();
            }

            // We have to determine the number via bitwise
            $int = ($high << 32) | $low;

            unset($low, $high);
        }

        return $int;
    }

    /**
     * Read a 32-bit float
     */
    public function readFloat32(): float
    {
        // Read the data into a string
        $string = $this->read(4);

        // For big endian we need to reverse the bytes
        if ($this->number_type === self::NUMBER_TYPE_BIGENDIAN) {
            $string = strrev($string);
        }

        $float = unpack('ffloat', $string);

        unset($string);

        return $float['float'];
    }

    private static function extendBinaryString(string $input, int $length = 4, $littleEndian = null): string
    {
        if (is_null($littleEndian)) {
            $littleEndian = self::isLittleEndian();
        }

        $extension = str_repeat(pack($littleEndian ? 'V' : 'N', 0b0000), $length - strlen($input));

        if ($littleEndian) {
            return $input . $extension;
        }

        return $extension . $input;
    }

    private static function isLittleEndian(): bool
    {
        return 0x00FF === current(unpack('v', pack('S', 0x00FF)));
    }
}
