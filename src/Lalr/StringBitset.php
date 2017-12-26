<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Lalr;

/**
 * Class StringBitset
 * @package PhpYacc\Lalr
 */
class StringBitset implements Bitset
{
    const NBITS = 8;

    const MASKS = [
        "\x01",
        "\x02",
        "\x04",
        "\x08",
        "\x10",
        "\x20",
        "\x40",
        "\x80",
    ];

    /**
     * @var int
     */
    public $numBits;

    /**
     * @var string
     */
    public $str;

    /**
     * StringBitset constructor.
     * @param int $numBits
     */
    public function __construct(int $numBits)
    {
        $this->numBits = $numBits;
        $this->str = \str_repeat("\0", \intdiv($numBits + self::NBITS - 1, self::NBITS));
    }

    /**
     * @param int $i
     * @return bool
     */
    public function testBit(int $i): bool
    {
        $offset = \intdiv($i, self::NBITS);
        return ((\ord($this->str[$offset]) >> ($i % self::NBITS)) & 1) !== 0;
    }

    /**
     * @param int $i
     */
    public function setBit(int $i)
    {
        $offset = \intdiv($i, self::NBITS);
        $char = $this->str[$offset];
        $char |= self::MASKS[$i % self::NBITS];
        $this->str[$offset] = $char;
    }

    /**
     * @param int $i
     */
    public function clearBit(int $i)
    {
        $offset = \intdiv($i, self::NBITS);
        $char = $this->str[$offset];
        $char &= ~self::MASKS[$i % self::NBITS];
        $this->str[$offset] = $char;
    }

    /**
     * @param Bitset $other
     * @return bool
     */
    public function or(Bitset $other): bool
    {
        /** @var StringBitset $other */
        \assert($this->numBits === $other->numBits);

        $changed = false;
        for ($i = 0; $i < $this->numBits; $i += self::NBITS) {
            $offset = $i / self::NBITS;
            if ("\0" !== ($other->str[$offset] & ~$this->str[$offset])) {
                $changed = true;
                $this->str[$offset] = $this->str[$offset] | $other->str[$offset];
            }
        }
        return $changed;
    }

    /**
     * @return \Generator
     */
    public function getIterator(): \Generator
    {
        for ($i = 0; $i < $this->numBits; $i++) {
            if ($this->testBit($i)) {
                yield $i;
            }
        }
    }
}
