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
 * Class Conflict.
 */
abstract class Conflict
{
    /**
     * @var null|Conflict
     */
    protected $next;

    /**
     * @var Symbol
     */
    protected $symbol;

    /**
     * Conflict constructor.
     *
     * @param Symbol        $symbol
     * @param Conflict|null $next
     */
    protected function __construct(Symbol $symbol, self $next = null)
    {
        $this->next = $next;
        $this->symbol = $symbol;
    }

    /**
     * @return bool
     */
    public function isShiftReduce(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isReduceReduce(): bool
    {
        return false;
    }

    /**
     * @return Symbol
     */
    public function symbol(): Symbol
    {
        return $this->symbol;
    }

    /**
     * @return null|Conflict
     */
    public function next()
    {
        return $this->next;
    }

    /**
     * @param Conflict|null $next
     */
    public function setNext(self $next = null)
    {
        $this->next = $next;
    }
}
