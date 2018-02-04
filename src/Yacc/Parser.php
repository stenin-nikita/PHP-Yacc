<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Yacc;

use PhpYacc\Exception\ParseException;
use PhpYacc\Grammar\Context;
use PhpYacc\Grammar\Symbol;
use PhpYacc\Support\Utils;

/**
 * Class Parser.
 */
class Parser
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Lexer
     */
    protected $lexer;

    /**
     * @var MacroSet
     */
    protected $macros;

    /**
     * @var Symbol
     */
    protected $eofToken;

    /**
     * @var Symbol
     */
    protected $errorToken;

    /**
     * @var Symbol
     */
    protected $startPrime;

    /**
     * @var int
     */
    protected $currentPrecedence = 0;

    /**
     * Parser constructor.
     *
     * @param Lexer    $lexer
     * @param MacroSet $macros
     */
    public function __construct(Lexer $lexer, MacroSet $macros)
    {
        $this->lexer = $lexer;
        $this->macros = $macros;
    }

    /**
     * @param string       $code
     * @param Context|null $context
     *
     * @throws ParseException
     * @throws \PhpYacc\Exception\LexingException
     *
     * @return Context
     */
    public function parse(string $code, Context $context = null)
    {
        $this->context = $context ?: new Context();

        $this->lexer->startLexing($code, $this->context->filename);

        $this->doDeclaration();
        $this->doGrammar();

        $this->context->eofToken = $this->eofToken;
        $this->context->errorToken = $this->errorToken;
        $this->context->startPrime = $this->startPrime;

        $this->context->finish();

        return $this->context;
    }

    /**
     * @param array $symbols
     * @param int   $n
     * @param $delm
     * @param array $attribute
     *
     * @throws ParseException
     * @throws \PhpYacc\Exception\LexingException
     *
     * @return string
     */
    protected function copyAction(array $symbols, int $n, $delm, array $attribute): string
    {
        $tokens = [];
        $ct = 0;

        while (($token = $this->lexer->getRawToken())->getValue() !== $delm || $ct > 0) {
            switch ($token->getValue()) {
                case "\0":
                    throw ParseException::unexpected($token, Token::decode($delm));
                case '{':
                    $ct++;
                    break;
                case '}':
                    $ct--;
                    break;
            }
            $tokens[] = $token;
        }

        $expanded = $this->macros->apply($this->context, $symbols, $tokens, $n, $attribute);

        $action = \implode('', \array_map(function (Token $token) {
            return $token->getValue();
        }, $expanded));

        return $action;
    }

    /**
     * @throws ParseException
     * @throws \PhpYacc\Exception\LexingException
     */
    protected function doType()
    {
        $type = $this->getType();
        while (true) {
            if (($token = $this->lexer->getToken())->getValue() === ',') {
                continue;
            }
            if ($token->getType() !== Token::T_NAME && $token->getValue()[0] !== "'") {
                break;
            }
            $p = $this->context->internSymbol($token->getValue(), false);
            if ($type !== null) {
                $p->type = $type;
            }
        }
        $this->lexer->ungetToken();
    }

    /**
     * @throws ParseException
     * @throws \PhpYacc\Exception\LexingException
     */
    protected function doGrammar()
    {
        $attribute = [];
        $buffer = [null];
        $production = new Production('', 0);

        $production->body = [$this->startPrime];
        $this->context->addGram($production);

        $token = $this->lexer->getToken();

        while ($token->getType() !== Token::T_MARK && $token->getType() !== Token::T_EOF) {
            if ($token->getType() === Token::T_NAME) {
                if ($this->lexer->peek()->getValue()[0] === '@') {
                    $attribute[0] = $token->getValue();
                    $this->lexer->getToken();
                    $token = $this->lexer->getToken();
                } else {
                    $attribute[0] = null;
                }
                $buffer[0] = $this->context->internSymbol($token->getValue(), false);
                $attribute[1] = null;
                if ($buffer[0]->isTerminal) {
                    throw new \RuntimeException("Non terminal symbol expected: $token");
                } elseif (($tmp = $this->lexer->getToken())->getType() !== Token::T_COLON) {
                    throw new \RuntimeException("':' expected, $tmp found");
                }
                if ($this->context->startSymbol === null) {
                    $this->context->startSymbol = $buffer[0];
                }
            } elseif ($token->getValue()[0] === '|') {
                if (!$buffer[0]) {
                    throw new \RuntimeException("Syntax Error, unexpected $token");
                }
                $attribute[1] = null;
            } elseif ($token->getType() === Token::T_BEGIN_INC) {
                $this->doCopy();
                $token = $this->lexer->getToken();
                continue;
            } else {
                throw new \RuntimeException("Syntax Error, unexpected $token");
            }

            $lastTerm = $this->startPrime;
            $action = null;
            $pos = 0;
            $i = 1;
            while (true) {
                $token = $this->lexer->getToken();

                if ($token->getValue()[0] === '=') {
                    $pos = $token->getLine();
                    if (($token = $this->lexer->getToken())->getValue()[0] === '{') {
                        $pos = $token->getLine();
                        $action = $this->copyAction($buffer, $i - 1, '}', $attribute);
                    } else {
                        $this->lexer->ungetToken();
                        $action = $this->copyAction($buffer, $i - 1, ';', $attribute);
                    }
                } elseif ($token->getValue()[0] === '{') {
                    $pos = $token->getLine();
                    $action = $this->copyAction($buffer, $i - 1, '}', $attribute);
                } elseif ($token->getType() === Token::T_PRECTOK) {
                    $lastTerm = $this->context->internSymbol($this->lexer->getToken()->getValue(), false);
                } elseif ($token->getType() === Token::T_NAME && $this->lexer->peek()->getType() === Token::T_COLON) {
                    break;
                } elseif ($token->getType() === Token::T_NAME && $this->lexer->peek()->getValue()[0] === '@') {
                    $attribute[$i] = $token->getValue();
                    $this->lexer->getToken();
                } elseif ($token->getType() === Token::T_NAME || $token->getType() === Token::T_STRING) {
                    if ($action) {
                        $g = $this->context->genNonTerminal();
                        $production = new Production($action, $pos);
                        $production->body = [$g];
                        $buffer[$i++] = $g;
                        $attribute[$i] = null;
                        $production->link = $production->body[0]->value;
                        $g->value = $this->context->addGram($production);
                    }
                    $buffer[$i++] = $w = $this->context->internSymbol($token->getValue(), false);
                    $attribute[$i] = null;
                    if ($w->isTerminal) {
                        $lastTerm = $w;
                    }
                    $action = null;
                } else {
                    break;
                }
            }
            if (!$action) {
                if ($i > 1 && $buffer[0]->type !== null && $buffer[0]->type !== $buffer[1]->type) {
                    throw new ParseException('Stack types are different');
                }
            }
            $production = new Production($action, $pos);

            $production->body = \array_slice($buffer, 0, $i);
            $production->precedence = $lastTerm->precedence;
            $production->associativity = $lastTerm->associativity & Symbol::MASK;
            $production->link = $production->body[0]->value;
            $buffer[0]->value = $this->context->addGram($production);

            if ($token->getType() === Token::T_SEMICOLON) {
                $token = $this->lexer->getToken();
            }
        }

        $this->context->gram(0)->body[] = $this->context->startSymbol;
        $this->startPrime->value = null;
        foreach ($this->context->nonterminals as $key => $symbol) {
            if ($symbol === $this->startPrime) {
                continue;
            }
            if (($j = $symbol->value) === null) {
                throw new ParseException("Non terminal {$symbol->name} used, but not defined");
            }
            $k = null;
            while ($j) {
                $w = $j->link;
                $j->link = $k;
                $k = $j;
                $j = $w;
            }
            $symbol->value = $k;
        }
    }

    /**
     * @throws ParseException
     * @throws \PhpYacc\Exception\LexingException
     */
    protected function doDeclaration()
    {
        $this->eofToken = $this->context->internSymbol('EOF', true);
        $this->eofToken->value = 0;
        $this->errorToken = $this->context->internSymbol('error', true);
        $this->startPrime = $this->context->internSymbol('$start', false);

        while (($token = $this->lexer->getToken())->getType() !== Token::T_MARK) {
            switch ($token->getType()) {
                case Token::T_TOKEN:
                case Token::T_RIGHT:
                case Token::T_LEFT:
                case Token::T_NON_ASSOC:
                    $this->doToken($token);
                    break;

                case Token::T_BEGIN_INC:
                    $this->doCopy();
                    break;

                case Token::T_UNION:
                    $this->doUnion();
                    $this->context->unioned = true;
                    break;

                case Token::T_TYPE:
                    $this->doType();
                    break;

                case Token::T_EXPECT:
                    $token = $this->lexer->getToken();
                    if ($token->getType() === Token::T_NUMBER) {
                        $this->context->expected = (int) $token->getValue();
                    } else {
                        throw ParseException::unexpected($token, Token::T_NUMBER);
                    }
                    break;

                case Token::T_START:
                    $token = $this->lexer->getToken();
                    $this->context->startSymbol = $this->context->internSymbol($token->getValue(), false);
                    break;

                case Token::T_PURE_PARSER:
                    $this->context->pureFlag = true;
                    break;

                case Token::T_EOF:
                    throw new ParseException('No grammar given');
                default:
                    throw new ParseException("Syntax error, unexpected {$token->getValue()}");
            }
        }

        $base = 256;
        foreach ($this->context->terminals as $terminal) {
            if ($terminal === $this->context->eofToken) {
                continue;
            }
            if ($terminal->value < 0) {
                $terminal->value = $base++;
            }
        }
    }

    /**
     * @param Token $tag
     *
     * @throws ParseException
     * @throws \PhpYacc\Exception\LexingException
     */
    protected function doToken(Token $tag)
    {
        $preIncr = 0;
        $type = $this->getType();
        $token = $this->lexer->getToken();

        while ($token->getType() === Token::T_NAME || $token->getType() === Token::T_STRING) {
            $p = $this->context->internSymbol($token->getValue(), true);

            if ($p->name[0] === "'") {
                $p->value = Utils::characterValue(\mb_substr($p->name, 1, -1));
            }

            if ($type) {
                $p->type = $type;
            }

            switch ($tag->getType()) {
                case Token::T_LEFT:
                    $p->associativity |= Symbol::LEFT;
                    break;
                case Token::T_RIGHT:
                    $p->associativity |= Symbol::RIGHT;
                    break;
                case Token::T_NON_ASSOC:
                    $p->associativity |= Symbol::NON;
                    break;
            }

            if ($tag->getType() !== Token::T_TOKEN) {
                $p->precedence = $this->currentPrecedence;
                $preIncr = 1;
            }

            $token = $this->lexer->getToken();
            if ($token->getType() === Token::T_NUMBER) {
                if ($p->value === null) {
                    $p->value = (int) $token->getValue();
                } else {
                    throw new ParseException(
                        sprintf('Unexpected Token::NUMBER as %s already has a value', $p->name)
                    );
                }
                $token = $this->lexer->getToken();
            }

            if ($token->getType() === Token::T_COMMA) {
                $token = $this->lexer->getToken();
            }
        }

        $this->lexer->ungetToken();
        $this->currentPrecedence += $preIncr;
    }

    /**
     * @throws ParseException
     * @throws \PhpYacc\Exception\LexingException
     *
     * @return null|Symbol
     */
    protected function getType()
    {
        $token = $this->lexer->getToken();

        if ($token->getValue()[0] !== '<') {
            $this->lexer->ungetToken();

            return;
        }

        $ct = 1;
        $p = '';
        $token = $this->lexer->getToken();

        while (true) {
            switch ($token->getValue()[0]) {
                case "\n":
                case "\0":
                    throw ParseException::unexpected($token, '>');
                case '<':
                    $ct++;
                    break;
                case '>':
                    $ct--;
                    break;
            }

            if ($ct === 0) {
                break;
            }

            $p .= $token->getValue();
            $token = $this->lexer->getRawToken();
        }
        $this->context->unioned = true;

        return $this->context->intern($p);
    }

    /**
     * @return void
     */
    protected function doCopy()
    {
        // TODO
    }

    /**
     * @return void
     */
    protected function doUnion()
    {
        // TODO
    }
}
