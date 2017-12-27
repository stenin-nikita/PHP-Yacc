<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Yacc;

use PhpYacc\Grammar\Symbol;

/**
 * Class Production.
 */
class Production
{
    const EMPTY = 0x10;

    /**
     * @var Production|null
     */
    public $link;

    /**
     * @var int
     */
    public $associativity;

    /**
     * @var int
     */
    public $precedence;

    /**
     * @var int
     */
    public $position;

    /**
     * @var null|string
     */
    public $action;

    /**
     * @var array|Symbol[]
     */
    public $body;

    /**
     * @var int
     */
    public $num = -1;

    /**
     * Production constructor.
     *
     * @param string|null $action
     * @param int         $position
     */
    public function __construct(string $action = null, int $position)
    {
        $this->action = $action;
        $this->position = $position;
        $this->body = [];
    }

    /**
     * @param int $flag
     */
    public function setAssociativityFlag(int $flag)
    {
        $this->associativity |= $flag;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return \count($this->body) <= 1;
    }
}
