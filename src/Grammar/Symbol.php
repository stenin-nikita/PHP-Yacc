<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Grammar;

use PhpYacc\Exception\LogicException;
use PhpYacc\Yacc\Production;

/**
 * Class Symbol
 */
class Symbol
{
    const UNDEF = 0;
    const LEFT = 1;
    const RIGHT = 2;
    const NON = 3;
    const MASK = 3;
    const TERMINAL = 0x100;
    const NONTERMINAL = 0x200;

    /**
     * @var int
     */
    public $code;

    /**
     * @var null|Symbol
     */
    protected $_type;

    /**
     * @var mixed
     */
    protected $_value;

    /**
     * @var int
     */
    protected $_precedence;

    /**
     * @var int
     */
    protected $_associativity;

    /**
     * @var string
     */
    protected $_name;

    /**
     * @var bool
     */
    public $isterminal = false;

    /**
     * @var bool
     */
    public $isnonterminal = false;

    /**
     * @var int
     */
    protected $_terminal = self::UNDEF;

    /**
     * Symbol constructor.
     * @param int $code
     * @param string $name
     * @param null $value
     * @param int $terminal
     * @param int $precedence
     * @param int $associativity
     * @param Symbol|null $type
     */
    public function __construct(int $code, string $name, $value = null, int $terminal = self::UNDEF, int $precedence = self::UNDEF, int $associativity = self::UNDEF, Symbol $type = null)
    {
        $this->code = $code;
        $this->_name = $name;
        $this->_value = $value;
        $this->setTerminal($terminal);
        $this->_precedence = $precedence;
        $this->_associativity = $associativity;
        $this->_type = $type;
    }

    /**
     * @return bool
     */
    public function isNilSymbol(): bool
    {
        return $this->_terminal === self::UNDEF;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->{'_'.$name};
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->{'set' . $name}($value);
    }

    /**
     * @param int $terminal
     */
    public function setTerminal(int $terminal)
    {
        $this->_terminal = $terminal;
        if ($terminal === self::TERMINAL) {
            $this->isterminal = true;
            $this->isnonterminal = false;
        } elseif ($terminal === self::NONTERMINAL) {
            $this->isterminal = false;
            $this->isnonterminal = true;
        } else {
            $this->isterminal = false;
            $this->isnonterminal = false;
        }
        $this->setValue($this->_value); // force check to prevent issues
    }

    /**
     * @param int $associativity
     */
    public function setAssociativity(int $associativity)
    {
        $this->_associativity = $associativity;
    }

    /**
     * @param int $precedence
     */
    public function setPrecedence(int $precedence)
    {
        $this->_precedence = $precedence;
    }

    /**
     * @param $value
     * @throws LogicException
     */
    public function setValue($value)
    {
        if ($this->isterminal && !is_int($value)) {
            throw new \LogicException("Terminals value must be an integer, " . \gettype($value) . " provided");
        } elseif ($this->isnonterminal  && !($value instanceof Production || $value === null)) {
            throw new \LogicException("NonTerminals value must be a production, " . \gettype($value) . " provided");
        }
        $this->_value = $value;
    }

    /**
     * @param Symbol|null $type
     */
    public function setType(Symbol $type = null)
    {
        $this->_type = $type;
    }

    /**
     * @param int $flag
     */
    public function setAssociativityFlag(int $flag)
    {
        $this->_associativity |= $flag;
    }
}
