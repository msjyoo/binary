<?php

namespace sekjun9878\Binary;

/*
 * PHP is mostly a language for web developers. It is also extremely easy to learn, therefore it is entirely
 * possible that a beginner or intermediate programmer may have to use this library or maintain this code.
 *
 * Therefore, every effort should be made to generalise the terms and names used, and every technical "snippet"
 * should include a comment explaining the actions under the assumption that the maintainer may not have any knowledge
 * in binary at all.
 *
 * As part of this effort, terms in function names are derived from usual MySQL terms, which a PHP developer has
 * plenty of experience in, rather than normal binary terms.
 *
 * See: https://dev.mysql.com/doc/refman/5.7/en/integer-types.html
 *
 * Such as using TINYINT instead of a BYTE. This is done to emphasize that:
 *     1. A binary type is not some mysterious different format - it's simply an integer.
 *     2. Easier wording to ease developer transition into using this library.
 *
 * If you are well versed in binary terms, feel free to make aliases to better represent the type you are using.
 * However, please do not make upstream patches changing these terms. Thanks!
 *
 * Also note that while in-line commenting is widely discouraged to enforce clean code among developers, that rule
 * will be ignored because for some developers reading this, binary-parsing is unknown territory. They will require
 * assistance reading this code.
 *
 * More info: https://igor.io/2012/09/24/binary-parsing.html
 */

/*
 * A Binary class is needed because we want to keep an offset of where the shift_ functions have last operated on.
 * This method is faster than functions using references such as `function shift_tint(&$buffer)` because using
 * references means that a variable (a memory space) has to be written to every time there is a shift, where as
 * using an object allows us to simply write an integer offset. An integer is smaller than an entire binary string.
 *
 * Performance is noticeably faster: https://3v4l.org/AhTJD
 *
 * Another benefit to having a Binary class is that it provides type safety as a value object.
 */

use Brick\Math\BigInteger;

final class Binary
{
	const BIG_ENDIAN = 1;
	const LITTLE_ENDIAN = 2;
	const MACHINE_DEPENDENT_ENDIAN = 3;

    public $data;
	public $endianness;

    public $offset = 0;

    public function __construct($data, $endianness = self::MACHINE_DEPENDENT_ENDIAN)
    {
        $this->data = $data;
	    $this->endianness = $endianness;
    }

    public function shift($length)
    {
        if($length < 0 or $length > strlen($this->data))
        {
            throw new \InvalidArgumentException("length cannot be zero or bigger than the current data buffer.");
        }

        $offset = $this->offset;
        $this->offset += $length;

        return substr($this->data, $offset, $length);
    }
}

/**
 * Shift and extract a signed TINYINT (1 Byte)
 *
 * @param Binary $data Raw binary data of any length wrapped in Binary value object
 * @return int Integer in value from -128 to 127
 */
function shift_tint(Binary $data)
{
    /*
     * More info in Two's Complements: http://www.cs.cornell.edu/~tomf/notes/cps104/twoscomp.html
     *
     * To represent signed numbers in the the binary format of computers, a technique called "two's complement" is used.
     * Basically, to convert a positive number to a negative number:
     *     1. Invert the binary
     *     2. Add 1 to the binary
     *
     * Number -116 as an example on 64 bit (8 Byte integers):
     *
     * 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0111 0100 <116 as a number>
     * 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1000 1011 <inverted>
     * 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1000 1100 <Two's complement>
     *
     * This process can easily be accomplished by using bitwise operators.
     * Note that the output of ord() by itself is completely wrong. (140 != -116)
     *
     * Binary representations of PHP numbers as they would be inside PHP's C code:
     * 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 1000 1100 <raw ord() output = 140>
     * 1000 1100 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 <after left shift by 56>
     * 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1111 1000 1100 <after right shift by 56 = -116>
     *
     * The only reason we perform a left shift, is so that we can perform the right shift afterwards which allows us to
     * conveniently:
     *     1. Invert the binary
     *     2. Preserve the sign of the binary e.g. not inverting if the number is positive
     *
     * This is due to PHP's bit shifting mechanic:
     *     > Bit shifting in PHP is arithmetic. Bits shifted off either end are discarded. Left shifts have zeros
     *     > shifted in on the right while the sign bit is shifted out on the left, meaning the sign of an operand is
     *     > not preserved. **Right shifts have copies of the sign bit shifted in on the left,
     *     > meaning the sign of an operand is preserved.**
     *
     * Example of a positive number (116):
     * 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0111 0100 <raw number>
     * 0111 0100 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 <after left shift by 56>
     * ^ Note that the first bit is 0, therefore in the right shift, 0 will be used instead
     * 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0111 0100 <after right shift by 56>
     *
     * As you can see, if an integer is positive, no "two's complement" operation has been performed and the binary
     * stays the same.
     *
     * The difference between 32-bit systems and 64-bit systems must also be accounted for.
     * In 32-bit systems, an integer is 4-bytes whereas on 64-bit systems, an integer is 8-bytes.
     * Therefore, on 64-bit systems 7-bytes (56-bits) are shifted and on 32-bit systems, 3-bytes (24-bits)
     * are shifted.
     *
     * | - - - - - - - - - - - - - - - - - - 64-bit  - - - - - - - - - - - - - - - - |
     *                                         | - - - - - - - 32-bit -  - - - - - - |
     * 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0111 0100 <116 as a number>
     */

	// Note - the range requirement is enforced by the fact that only 1 byte is gotten and the bit-shifting
	// Note - ord() is faster than unpack() for single bytes

    if(PHP_INT_SIZE === 8) // 64-bit
    {
        // Get the first byte of the binary data, convert it to an integer (for bitwise operations), and perform
        // the above operation sequence.
        return ((ord($data->shift(1)) << 56) >> 56);
    }
    else if(PHP_INT_SIZE === 4) // 32-bit
    {
        // Same as above.
        return ((ord($data->shift(1)) << 24) >> 24);
    }

    // Unknown-bit system
    throw new \RuntimeException("Unknown system architecture");
}

function shift_utint(Binary $data)
{
	// Note - the range requirement is enforced by the fact that only 1 byte is gotten
	return ord($data->shift(1));
}

function shift_sint(Binary $data)
{
	if(PHP_INT_SIZE === 8)
	{
		if($data->endianness === Binary::BIG_ENDIAN)
		{
			return unpack("n", $data->shift(2))[1] << 48 >> 48;
		}
		else if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			return unpack("v", $data->shift(2))[1] << 48 >> 48;
		}

		throw new \InvalidArgumentException("Unknown endianness provided");

	}
	else if(PHP_INT_SIZE === 4)
	{
		if($data->endianness === Binary::BIG_ENDIAN)
		{
			return unpack("n", $data->shift(2))[1] << 16 >> 16;
		}
		else if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			return unpack("v", $data->shift(2))[1] << 16 >> 16;
		}

		throw new \InvalidArgumentException("Unknown endianness provided");
	}

	throw new \RuntimeException("Unknown system architecture");
}

function shift_usint(Binary $data)
{
	if(PHP_INT_SIZE === 8)
	{
		if($data->endianness === Binary::BIG_ENDIAN)
		{
			return unpack("n", $data->shift(2))[1];
		}
		else if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			return unpack("v", $data->shift(2))[1];
		}

		throw new \InvalidArgumentException("Unknown endianness provided");

	}
	else if(PHP_INT_SIZE === 4)
	{
		if($data->endianness === Binary::BIG_ENDIAN)
		{
			return unpack("n", $data->shift(2))[1];
		}
		else if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			return unpack("v", $data->shift(2))[1];
		}

		throw new \InvalidArgumentException("Unknown endianness provided");
	}

	throw new \RuntimeException("Unknown system architecture");
}

//TODO: Do signed integers work with this workaround?
function shift_mint(Binary $data)
{
	/*
	 * PHP does not have a specific unpack function for 3-byte integers.
	 * Therefore, to overcome that, we pad the binary string to make it 4 bytes, parse it as a 4-byte integer,
	 * and then use bitwise operators to make the integer 3-bytes.
	 */

	if($data->endianness === Binary::BIG_ENDIAN)
	{
		if(PHP_INT_SIZE === 8)
		{
			return unpack("N", "\x00".$data->shift(3))[1] << 40 >> 40;
		}
		else if(PHP_INT_SIZE === 4)
		{
			return unpack("N", "\x00".$data->shift(3))[1] << 8 >> 8;
		}

		throw new \RuntimeException("Unknown system architecture");
	}
	else if($data->endianness === Binary::LITTLE_ENDIAN)
	{
		if(PHP_INT_SIZE === 8)
		{
			return unpack("V", $data->shift(3)."\x00")[1] << 40 >> 40;
		}
		else if(PHP_INT_SIZE === 4)
		{
			return unpack("V", $data->shift(3)."\x00")[1] << 8 >> 8;
		}

		throw new \RuntimeException("Unknown system architecture");
	}

	throw new \InvalidArgumentException("Unknown endianness provided");
}

function shift_umint(Binary $data)
{
	if($data->endianness === Binary::BIG_ENDIAN)
	{
		if(PHP_INT_SIZE === 8)
		{
			return unpack("N", "\x00".$data->shift(3))[1];
		}
		else if(PHP_INT_SIZE === 4)
		{
			return unpack("N", "\x00".$data->shift(3))[1];
		}

		throw new \RuntimeException("Unknown system architecture");
	}
	else if($data->endianness === Binary::LITTLE_ENDIAN)
	{
		if(PHP_INT_SIZE === 8)
		{
			return unpack("V", $data->shift(3)."\x00")[1];
		}
		else if(PHP_INT_SIZE === 4)
		{
			return unpack("V", $data->shift(3)."\x00")[1];
		}

		throw new \RuntimeException("Unknown system architecture");
	}

	throw new \InvalidArgumentException("Unknown endianness provided");
}

function shift_int(Binary $data)
{
	if(PHP_INT_SIZE === 8)
	{
		if($data->endianness === Binary::BIG_ENDIAN)
		{
			return unpack("N", $data->shift(4))[1] << 32 >> 32;
		}
		else if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			return unpack("V", $data->shift(4))[1] << 32 >> 32;
		}

		throw new \InvalidArgumentException("Unknown endianness provided");

	}
	else if(PHP_INT_SIZE === 4)
	{
		if($data->endianness === Binary::BIG_ENDIAN)
		{
			return unpack("N", $data->shift(4))[1];
		}
		else if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			return unpack("V", $data->shift(4))[1];
		}

		throw new \InvalidArgumentException("Unknown endianness provided");
	}

	throw new \RuntimeException("Unknown system architecture");
}

function shift_uint(Binary $data)
{
	if(PHP_INT_SIZE === 8)
	{
		if($data->endianness === Binary::BIG_ENDIAN)
		{
			return unpack("N", $data->shift(4))[1];
		}
		else if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			return unpack("V", $data->shift(4))[1];
		}

		throw new \InvalidArgumentException("Unknown endianness provided");

	}
	else if(PHP_INT_SIZE === 4)
	{
		/*
		 * On 32-bit systems, attempting to store a number of an unsigned 32-bit integer in a standard PHP integer
		 * (which is a signed 32-bit) is impossible. PHP will convert such numbers automatically to a float which is
		 * a behaviour we do not want.
		 *
		 * Therefore, it is necessary to manually gather individual bytes and put them back together to preserve
		 * precision.
		 */

		$v = unpack("C4", $data->shift(4));

		if($data->endianness === Binary::LITTLE_ENDIAN)
		{
			$v = array_reverse($v);
		}
		else if($data->endianness === Binary::BIG_ENDIAN)
		{
			$v = array_values($v); // Otherwise the array will start at index 1
		}
		else
		{
			throw new \InvalidArgumentException("Unknown endianness provided");
		}

		return BigInteger::of($v[0])
			->multipliedBy(BigInteger::of(2)->power(56))
			->plus(BigInteger::of($v[1])->multipliedBy(BigInteger::of(2)->power(48)))
			->plus(BigInteger::of($v[2])->multipliedBy(BigInteger::of(2)->power(40)))
			->plus(BigInteger::of($v[3])->multipliedBy(BigInteger::of(2)->power(32)))
			->plus(BigInteger::of($v[4])->multipliedBy(BigInteger::of(2)->power(24)))
			->plus(BigInteger::of($v[5])->multipliedBy(BigInteger::of(2)->power(16)))
			->plus(BigInteger::of($v[6])->multipliedBy(BigInteger::of(2)->power(8)))
			->plus(BigInteger::of($v[7]));
	}

	throw new \RuntimeException("Unknown system architecture");
}

function shift_bint(&$data)
{

}

function shift_ubint(&$data)
{

}

function get_system_endianness()
{
	if(unpack('S', "\x01\x00")[1] === 1)
	{
		return 2; // Little-Endian
	}
	else
	{
		return 1; // Big-Endian
	}
}