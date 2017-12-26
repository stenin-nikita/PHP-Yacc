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
 * Class Reduce
 */
class Reduce
{
    /**
     * @var Symbol
     */
    public $symbol;

    /**
     * @var int
     */
    public $number;

    /**
     * Reduce constructor.
     * @param Symbol $symbol
     * @param int $number
     */
    public function __construct(Symbol $symbol, int $number)
    {
        $this->symbol = $symbol;
        $this->number = $number;
    }
}
