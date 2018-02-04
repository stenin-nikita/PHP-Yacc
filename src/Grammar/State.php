<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Grammar;

use PhpYacc\Lalr\Conflict;
use PhpYacc\Lalr\Lr1;
use PhpYacc\Lalr\Reduce;

/**
 * Class State.
 */
class State
{
    /**
     * @var array|State[]
     */
    public $shifts = [];

    /**
     * @var array|Reduce[]
     */
    public $reduce;

    /**
     * @var Conflict|null
     */
    public $conflict;

    /**
     * @var Symbol
     */
    public $through;

    /**
     * @var Lr1
     */
    public $items;

    /**
     * @var int
     */
    public $number;

    /**
     * State constructor.
     *
     * @param Symbol $through
     * @param Lr1    $items
     */
    public function __construct(Symbol $through, Lr1 $items)
    {
        $this->through = $through;
        $this->items = $items;
    }

    /**
     * @return bool
     */
    public function isReduceOnly(): bool
    {
        return empty($this->shifts) && $this->reduce[0]->symbol->isNilSymbol();
    }
}
