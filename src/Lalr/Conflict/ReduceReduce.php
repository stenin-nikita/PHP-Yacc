<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Lalr\Conflict;

use PhpYacc\Grammar\Symbol;
use PhpYacc\Lalr\Conflict;

/**
 * Class ReduceReduce.
 */
class ReduceReduce extends Conflict
{
    /**
     * @var int
     */
    protected $reduce1;

    /**
     * @var int
     */
    protected $reduce2;

    /**
     * ReduceReduce constructor.
     *
     * @param int           $reduce1
     * @param int           $reduce2
     * @param Symbol        $symbol
     * @param Conflict|null $next
     */
    public function __construct(int $reduce1, int $reduce2, Symbol $symbol, Conflict $next = null)
    {
        $this->reduce1 = $reduce1;
        $this->reduce2 = $reduce2;
        parent::__construct($symbol, $next);
    }

    /**
     * @return bool
     */
    public function isReduceReduce(): bool
    {
        return true;
    }

    /**
     * @return int
     */
    public function reduce1(): int
    {
        return $this->reduce1;
    }

    /**
     * @return int
     */
    public function reduce2(): int
    {
        return $this->reduce2;
    }
}
