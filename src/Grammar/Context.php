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
use PhpYacc\Support\Utils;
use PhpYacc\Yacc\Macro\DollarExpansion;
use PhpYacc\Yacc\Production;

/**
 * Class Context.
 */
class Context
{
    /**
     * @var array
     */
    public $macros = [
        DollarExpansion::SEMVAL_LHS_TYPED   => '',
        DollarExpansion::SEMVAL_LHS_UNTYPED => '',
        DollarExpansion::SEMVAL_RHS_TYPED   => '',
        DollarExpansion::SEMVAL_RHS_UNTYPED => '',
    ];

    /**
     * @var int
     */
    public $nsymbols = 0;

    /**
     * @var int
     */
    public $nterminals = 0;

    /**
     * @var int
     */
    public $nnonterminals = 0;

    /**
     * @var array
     */
    protected $symbolHash = [];

    /**
     * @var array|Symbol[]
     */
    protected $_symbols = [];

    /**
     * @var Symbol
     */
    protected $_nilsymbol;

    /**
     * @var bool
     */
    protected $finished = false;

    /**
     * @var array|State[]
     */
    protected $_states;

    /**
     * @var int
     */
    public $nstates = 0;

    /**
     * @var int
     */
    public $nnonleafstates = 0;

    /**
     * @var bool
     */
    public $aflag = false;

    /**
     * @var bool
     */
    public $tflag = false;

    /**
     * @var string
     */
    public $pspref = '';

    /**
     * @var bool
     */
    public $debug = false;

    /**
     * @var string
     */
    public $filename = 'YY';

    /**
     * @var bool
     */
    public $pureFlag = false;
    public $startSymbol;
    public $expected;

    /**
     * @var bool
     */
    public $unioned = false;

    public $eofToken;
    public $errorToken;
    public $startPrime;

    /**
     * @var array|Production[]
     */
    protected $_grams = [];

    /**
     * @var int
     */
    public $ngrams = 0;

    /**
     * @var array
     */
    public $default_act = [];

    /**
     * @var array
     */
    public $default_goto = [];

    /**
     * @var array
     */
    public $term_action = [];

    /**
     * @var array
     */
    public $class_action = [];

    /**
     * @var array
     */
    public $nonterm_goto = [];

    /**
     * @var array
     */
    public $class_of = [];

    /**
     * @var array
     */
    public $ctermindex = [];

    /**
     * @var array
     */
    public $otermindex = [];

    /**
     * @var array
     */
    public $frequency = [];

    /**
     * @var array
     */
    public $state_imagesorted = [];

    /**
     * @var int
     */
    public $nprims = 0;

    /**
     * @var array
     */
    public $prims = [];

    /**
     * @var array
     */
    public $primof = [];

    /**
     * @var array
     */
    public $class2nd = [];

    /**
     * @var int
     */
    public $nclasses = 0;

    /**
     * @var int
     */
    public $naux = 0;

    /**
     * @var null|resource
     */
    public $debugFile;

    /**
     * Context constructor.
     *
     * @param string        $filename
     * @param resource|null $debugFile
     */
    public function __construct(string $filename = 'YY', resource $debugFile = null)
    {
        $this->filename = $filename;
        $this->debugFile = $debugFile;
    }

    /**
     * @param string $data
     */
    public function debug(string $data)
    {
        if ($this->debugFile) {
            \fwrite($this->debugFile, $data);
        }
    }

    /**
     * @param $name
     *
     * @throws LogicException
     *
     * @return \Generator
     */
    public function __get($name)
    {
        switch ($name) {
            case 'terminals': return $this->terminals();
            case 'nonterminals': return $this->nonTerminals();
        }
        if (!isset($this->{'_'.$name})) {
            throw new LogicException("Should never happen: unknown property $name");
        }

        return $this->{'_'.$name};
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->{'set'.$name}($value);
    }

    /**
     * @return void
     */
    public function finish()
    {
        if ($this->finished) {
            return;
        }
        $this->finished = true;
        $code = 0;
        foreach ($this->terminals() as $term) {
            $term->code = $code++;
        }
        foreach ($this->nonTerminals() as $nonterm) {
            $nonterm->code = $code++;
        }
        foreach ($this->nilSymbols() as $nil) {
            $nil->code = $code++;
        }

        \usort($this->_symbols, function ($a, $b) {
            return $a->code <=> $b->code;
        });
    }

    /**
     * @return Symbol
     */
    public function nilSymbol(): Symbol
    {
        if ($this->_nilsymbol === null) {
            $this->_nilsymbol = $this->intern('@nil');
        }

        return $this->_nilsymbol;
    }

    /**
     * @return \Generator
     */
    public function terminals(): \Generator
    {
        foreach ($this->_symbols as $symbol) {
            if ($symbol->isterminal) {
                yield $symbol;
            }
        }
    }

    /**
     * @return \Generator
     */
    public function nilSymbols(): \Generator
    {
        foreach ($this->_symbols as $symbol) {
            if ($symbol->isNilSymbol()) {
                yield $symbol;
            }
        }
    }

    /**
     * @return \Generator
     */
    public function nonTerminals(): \Generator
    {
        foreach ($this->_symbols as $symbol) {
            if ($symbol->isnonterminal) {
                yield $symbol;
            }
        }
    }

    /**
     * @return Symbol
     */
    public function genNonTerminal(): Symbol
    {
        $buffer = \sprintf('@%d', $this->nnonterminals);

        return $this->internSymbol($buffer, false);
    }

    /**
     * @param string $s
     * @param bool   $isTerm
     *
     * @return Symbol
     */
    public function internSymbol(string $s, bool $isTerm): Symbol
    {
        $p = $this->intern($s);

        if (!$p->isNilSymbol()) {
            return $p;
        }
        if ($isTerm || $s[0] === "'") {
            if ($s[0] === "'") {
                $p->value = Utils::characterValue(\mb_substr($s, 1, 1));
            } else {
                $p->value = -1;
            }
            $p->terminal = Symbol::TERMINAL;
        } else {
            $p->value = null;
            $p->terminal = Symbol::NONTERMINAL;
        }

        $p->associativity = Symbol::UNDEF;
        $p->precedence = Symbol::UNDEF;

        return $p;
    }

    /**
     * @param string $s
     *
     * @return Symbol
     */
    public function intern(string $s): Symbol
    {
        if (isset($this->symbolHash[$s])) {
            return $this->symbolHash[$s];
        }
        $p = new Symbol($this->nsymbols++, $s);

        return $this->addSymbol($p);
    }

    /**
     * @param Symbol $symbol
     *
     * @return Symbol
     */
    public function addSymbol(Symbol $symbol): Symbol
    {
        $this->finished = false;
        $this->_symbols[] = $symbol;
        $this->symbolHash[$symbol->name] = $symbol;
        $this->nterminals = 0;
        $this->nnonterminals = 0;
        foreach ($this->_symbols as $symbol) {
            if ($symbol->isterminal) {
                $this->nterminals++;
            } elseif ($symbol->isnonterminal) {
                $this->nnonterminals++;
            }
        }

        return $symbol;
    }

    /**
     * @return array
     */
    public function symbols(): array
    {
        return $this->_symbols;
    }

    /**
     * @param int $code
     *
     * @return Symbol
     */
    public function symbol(int $code): Symbol
    {
        foreach ($this->_symbols as $symbol) {
            if ($symbol->code === $code) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * @param Production $p
     *
     * @return Production
     */
    public function addGram(Production $p)
    {
        $p->num = $this->ngrams++;
        $this->_grams[] = $p;

        return $p;
    }

    /**
     * @param int $i
     *
     * @return Production
     */
    public function gram(int $i): Production
    {
        assert($i < $this->ngrams);

        return $this->_grams[$i];
    }

    /**
     * @param array $states
     */
    public function setStates(array $states)
    {
        foreach ($states as $state) {
            assert($state instanceof State);
        }
        $this->_states = $states;
        $this->nstates = \count($states);
    }

    /**
     * @param int $n
     */
    public function setNNonLeafStates(int $n)
    {
        $this->nnonleafstates = $n;
    }
}
