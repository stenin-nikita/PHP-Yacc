<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Lalr;

use PhpYacc\Grammar\Symbol;

/**
 * Class Lr1.
 */
class Lr1
{
    /**
     * @var Lr1|null
     */
    public $next;

    /**
     * @var Symbol|null
     */
    public $left;

    /**
     * @var Item
     */
    public $item;

    /**
     * @var BitSet
     */
    public $look;

    /**
     * Lr1 constructor.
     *
     * @param Symbol|null $left
     * @param BitSet      $look
     * @param Item        $item
     */
    public function __construct(Symbol $left = null, BitSet $look, Item $item)
    {
        $this->left = $left;
        $this->look = $look;
        $this->item = $item;
    }

    /**
     * @return bool
     */
    public function isTailItem(): bool
    {
        return $this->item->isTailItem();
    }

    /**
     * @return bool
     */
    public function isHeadItem(): bool
    {
        return $this->item->isHeadItem();
    }

    /**
     * @return string
     */
    public function dump(): string
    {
        $result = '';
        $lr1 = $this;
        while ($lr1 !== null) {
            $result .= $lr1->item."\n";
            $lr1 = $lr1->next;
        }

        return $result."\n";
    }
}
