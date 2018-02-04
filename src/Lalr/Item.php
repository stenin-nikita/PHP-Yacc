<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Lalr;

use PhpYacc\Exception\LogicException;
use PhpYacc\Yacc\Production;

class Item implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var Production
     */
    protected $production;

    /**
     * @var int
     */
    protected $pos = 0;

    /**
     * Item constructor.
     *
     * @param Production $production
     * @param int        $offset
     */
    public function __construct(Production $production, int $offset)
    {
        \assert($offset >= 1);
        \assert($offset <= \count($production->body));
        $this->production = $production;
        $this->pos = $offset;
    }

    /**
     * @return \Generator
     */
    public function getIterator(): \Generator
    {
        for ($i = $this->pos; $i < \count($this->production->body); $i++) {
            yield $this->production->body[$i];
        }
    }

    /**
     * @param int $n
     *
     * @return Item
     */
    public function slice(int $n): self
    {
        return new self($this->production, $this->pos + $n);
    }

    /**
     * @param mixed $index
     *
     * @return bool
     */
    public function offsetExists($index)
    {
        return isset($this->production->body[$index + $this->pos]);
    }

    /**
     * @param mixed $index
     *
     * @throws LogicException
     *
     * @return \PhpYacc\Grammar\Symbol
     */
    public function offsetGet($index)
    {
        if (!$this->offsetExists($index)) {
            throw new LogicException("Offset $index does not exist");
        }

        return $this->production->body[$index + $this->pos];
    }

    /**
     * @param mixed $index
     * @param mixed $value
     *
     * @throws LogicException
     */
    public function offsetSet($index, $value)
    {
        throw new LogicException('Not supported');
    }

    /**
     * @param mixed $index
     * @return void
     * @throws LogicException
     */
    public function offsetUnset($index)
    {
        throw new LogicException('Not supported');
    }

    /**
     * @return bool
     */
    public function isHeadItem(): bool
    {
        return $this->pos === 1;
    }

    /**
     * @return bool
     */
    public function isTailItem(): bool
    {
        return $this->pos === \count($this->production->body);
    }

    /**
     * @return Production
     */
    public function getProduction(): Production
    {
        return $this->production;
    }

    /**
     * @return int
     */
    public function getPos(): int
    {
        return $this->pos;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $result = '('.$this->production->num.')';
        for ($i = 0; $i < \count($this->production->body); $i++) {
            if ($i === 1) {
                $result .= ' :';
            }

            if ($i === $this->pos) {
                $result .= ' .';
            }

            $result .= ' '.$this->production->body[$i]->name;
        }

        if ($i === 1) {
            $result .= ' :';
        }

        if ($i === $this->pos) {
            $result .= ' .';
        }

        return $result;
    }
}
