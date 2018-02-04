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
 * Class ArrayBitset.
 */
class ArrayBitSet implements BitSet
{
    const BITS = \PHP_INT_SIZE * 8;

    /**
     * @var int
     */
    public $numBits;

    /**
     * @var array
     */
    public $array;

    /**
     * ArrayBitset constructor.
     *
     * @param int $numBits
     */
    public function __construct(int $numBits)
    {
        $this->numBits = $numBits;
        $this->array = \array_fill(0, intdiv($numBits + self::BITS - 1, self::BITS), 0);
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $this->array = \array_values($this->array);
    }

    /**
     * @param int $i
     *
     * @return bool
     */
    public function testBit(int $i): bool
    {
        return ($this->array[$i / self::BITS] & (1 << ($i % self::BITS))) !== 0;
    }

    /**
     * @param int $i
     */
    public function setBit(int $i)
    {
        $this->array[$i / self::BITS] |= (1 << ($i % self::BITS));
    }

    /**
     * @param int $i
     */
    public function clearBit(int $i)
    {
        $this->array[$i / self::BITS] &= ~(1 << ($i % self::BITS));
    }

    /**
     * @param BitSet $other
     *
     * @return bool
     */
    public function or(BitSet $other): bool
    {
        /* @var $other ArrayBitSet */
        assert($this->numBits === $other->numBits);

        $changed = false;
        foreach ($this->array as $key => $value) {
            $this->array[$key] = $value | $other->array[$key];
            $changed = $changed || $value !== $this->array[$key];
        }

        return $changed;
    }

    /**
     * @return \Generator
     */
    public function getIterator(): \Generator
    {
        $count = \count($this->array);
        for ($n = 0; $n < $count; $n++) {
            $elem = $this->array[$n];
            if ($elem !== 0) {
                for ($i = 0; $i < self::BITS; $i++) {
                    if ($elem & (1 << $i)) {
                        yield $n * self::BITS + $i;
                    }
                }
            }
        }
    }
}
