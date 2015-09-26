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

/**
 * Shift and extract a signed TINYINT (1 Byte)
 *
 * @param string $data Raw binary data of any length
 * @return int Integer in value from -128 to 127
 */
function shift_tint(&$data)
{
    $char = $data{0};
    $data = substr($data, 1);

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

    if(PHP_INT_SIZE === 8) // 64-bit
    {
        return ((ord($char) << 56) >> 56);
    }
    else if(PHP_INT_SIZE === 4) // 32-bit
    {
        return ((ord($char) << 24) >> 24);
    }

    // Unknown-bit system
    throw new \LogicException;
}

/**
 * Shift and extract an unsigned TINYINT (1 Byte)
 *
 * @param string $data Raw binary data of any length
 * @return int Integer in value from 0 to 255
 */
function shift_utint(&$data)
{
    $char = $data{0};
    $data = substr($data, 1);

    return ord($char);
}

function shift_sint(&$data)
{

}

function shift_usint(&$data)
{

}

function shift_mint(&$data)
{

}

function shift_umint(&$data)
{

}

function shift_int(&$data)
{

}

function shift_uint(&$data)
{

}

function shift_bint(&$data)
{

}

function shift_ubint(&$data)
{

}