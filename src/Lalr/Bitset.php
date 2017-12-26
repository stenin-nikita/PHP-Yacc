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
 * Interface Bitset
 * @package PhpYacc\Lalr
 */
interface Bitset extends \IteratorAggregate
{
    /**
     * @param int $i
     * @return bool
     */
    public function testBit(int $i): bool;

    /**
     * @param int $i
     */
    public function setBit(int $i);

    /**
     * @param int $i
     */
    public function clearBit(int $i);

    /**
     * @param Bitset $other
     * @return bool
     */
    public function or(Bitset $other): bool;
}
