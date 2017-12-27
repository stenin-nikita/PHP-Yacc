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
            if ($token->getType() !== Token::NAME && $token->getValue()[0] !== "'") {
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
        $gbuffer = [null];
        $r = new Production('', 0);

        $r->body = [$this->startPrime];
        $this->context->addGram($r);

        $token = $this->lexer->getToken();

        while ($token->getType() !== Token::MARK && $token->getType() !== Token::EOF) {
            if ($token->getType() === Token::NAME) {
                if ($this->lexer->peek()->getValue()[0] === '@') {
                    $attribute[0] = $token->getValue();
                    $this->lexer->getToken();
                    $token = $this->lexer->getToken();
                } else {
                    $attribute[0] = null;
                }
                $gbuffer[0] = $this->context->internSymbol($token->getValue(), false);
                $attribute[1] = null;
                if ($gbuffer[0]->isterminal) {
                    throw new \RuntimeException("Nonterminal symbol expected: $token");
                } elseif (($tmp = $this->lexer->getToken())->getType() !== Token::COLON) {
                    throw new \RuntimeException("':' expected, $tmp found");
                }
                if ($this->context->startSymbol === null) {
                    $this->context->startSymbol = $gbuffer[0];
                }
            } elseif ($token->getValue()[0] === '|') {
                if (!$gbuffer[0]) {
                    throw new \RuntimeException("Syntax Error, unexpected $token");
                }
                $attribute[1] = null;
            } elseif ($token->getType() === Token::BEGININC) {
                $this->doCopy();
                $token = $this->lexer->getToken();
                continue;
            } else {
                throw new \RuntimeException("Syntax Error Unexpected $token");
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
                        $action = $this->copyAction($gbuffer, $i - 1, '}', $attribute);
                    } else {
                        $this->lexer->ungetToken();
                        $action = $this->copyAction($gbuffer, $i - 1, ';', $attribute);
                    }
                } elseif ($token->getValue()[0] === '{') {
                    $pos = $token->getLine();
                    $action = $this->copyAction($gbuffer, $i - 1, '}', $attribute);
                } elseif ($token->getType() === Token::PRECTOK) {
                    $lastTerm = $this->context->internSymbol($this->lexer->getToken()->getValue(), false);
                } elseif ($token->getType() === Token::NAME && $this->lexer->peek()->getValue()[0] === ':') {
                    break;
                } elseif ($token->getType() === Token::NAME && $this->lexer->peek()->getValue()[0] === '@') {
                    $attribute[$i] = $token->getValue();
                    $this->lexer->getToken();
                } elseif ($token->getType() === Token::NAME || $token->getValue()[0] === "'") {
                    if ($action) {
                        $g = $this->context->genNonTerminal();
                        $r = new Production($action, $pos);
                        $r->body = [$g];
                        $gbuffer[$i++] = $g;
                        $attribute[$i] = null;
                        $r->link = $r->body[0]->value;
                        $g->value = $this->context->addGram($r);
                    }
                    $gbuffer[$i++] = $w = $this->context->internSymbol($token->getValue(), false);
                    $attribute[$i] = null;
                    if ($w->isterminal) {
                        $lastTerm = $w;
                    }
                    $action = null;
                } else {
                    break;
                }
            }
            if (!$action) {
                if ($i > 1 && $gbuffer[0]->type !== null && $gbuffer[0]->type !== $gbuffer[1]->type) {
                    throw new ParseException('Stack types are different');
                }
            }
            $r = new Production($action, $pos);

            $r->body = \array_slice($gbuffer, 0, $i);
            $r->precedence = $lastTerm->precedence;
            $r->associativity = $lastTerm->associativity & Symbol::MASK;
            $r->link = $r->body[0]->value;
            $gbuffer[0]->value = $this->context->addGram($r);

            if ($token->getType() === Token::SEMICOLON) {
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
                throw new ParseException("Nonterminal {$symbol->name} used but not defined");
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

        while (($token = $this->lexer->getToken())->getType() !== Token::MARK) {
            switch ($token->getType()) {
                case Token::TOKEN:
                case Token::RIGHT:
                case Token::LEFT:
                case Token::NONASSOC:
                    $this->doToken($token);
                    break;

                case Token::BEGININC:
                    $this->doCopy();
                    break;

                case Token::UNION:
                    $this->doUnion();
                    $this->context->unioned = true;
                    break;

                case Token::TYPE:
                    $this->doType();
                    break;

                case Token::EXPECT:
                    $token = $this->lexer->getToken();
                    if ($token->getType() === Token::NUMBER) {
                        $this->context->expected = (int) $token->getValue();
                    } else {
                        throw ParseException::unexpected($token, Token::NUMBER);
                    }
                    break;

                case Token::START:
                    $token = $this->lexer->getToken();
                    $this->context->startSymbol = $this->context->internSymbol($token->getValue(), false);
                    break;

                case Token::PURE_PARSER:
                    $this->context->pureFlag = true;
                    break;

                case Token::EOF:
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

        while ($token->getType() === Token::NAME || $token->getType() === Token::STRING) {
            $p = $this->context->internSymbol($token->getValue(), true);

            if ($p->name[0] === "'") {
                $p->value = Utils::characterValue(\mb_substr($p->name, 1, -1));
            }

            if ($type) {
                $p->type = $type;
            }

            switch ($tag->getType()) {
                case Token::LEFT:
                    $p->associativity |= Symbol::LEFT;
                    break;
                case Token::RIGHT:
                    $p->associativity |= Symbol::RIGHT;
                    break;
                case Token::NONASSOC:
                    $p->associativity |= Symbol::NON;
                    break;
            }

            if ($tag->getType() !== Token::TOKEN) {
                $p->precedence = $this->currentPrecedence;
                $preIncr = 1;
            }

            $token = $this->lexer->getToken();
            if ($token->getType() === Token::NUMBER) {
                if ($p->value === null) {
                    $p->value = (int) $token->getValue();
                } else {
                    throw new ParseException(sprintf('Unexpected Token::NUMBER as %s already has a value', $p->name));
                }
                $token = $this->lexer->getToken();
            }

            if ($token->getType() === Token::COMMA) {
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
}
