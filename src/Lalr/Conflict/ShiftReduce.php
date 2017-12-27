<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Lalr\Conflict;

use PhpYacc\Grammar\State;
use PhpYacc\Grammar\Symbol;
use PhpYacc\Lalr\Conflict;

/**
 * Class ShiftReduce.
 */
class ShiftReduce extends Conflict
{
    /**
     * @var State
     */
    protected $state;

    /**
     * @var int
     */
    protected $reduce;

    /**
     * ShiftReduce constructor.
     *
     * @param State         $state
     * @param int           $reduce
     * @param Symbol        $symbol
     * @param Conflict|null $next
     */
    public function __construct(State $state, int $reduce, Symbol $symbol, Conflict $next = null)
    {
        $this->state = $state;
        $this->reduce = $reduce;
        parent::__construct($symbol, $next);
    }

    /**
     * @return bool
     */
    public function isShiftReduce(): bool
    {
        return true;
    }

    /**
     * @return State
     */
    public function state(): State
    {
        return $this->state;
    }

    /**
     * @return int
     */
    public function reduce(): int
    {
        return $this->reduce;
    }
}
