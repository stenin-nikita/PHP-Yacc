<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Yacc\Macro;

use PhpYacc\Exception\ParseException;
use PhpYacc\Grammar\Context;
use PhpYacc\Yacc\MacroAbstract;
use PhpYacc\Yacc\Token;

/**
 * Class DollarExpansion.
 */
class DollarExpansion extends MacroAbstract
{
    const SEMVAL_LHS_TYPED = 1;
    const SEMVAL_LHS_UNTYPED = 2;
    const SEMVAL_RHS_TYPED = 3;
    const SEMVAL_RHS_UNTYPED = 4;

    /**
     * @param Context   $ctx
     * @param array     $symbols
     * @param \Iterator $tokens
     * @param int       $n
     * @param array     $attribute
     *
     * @throws ParseException
     * @throws \PhpYacc\Exception\LogicException
     *
     * @return \Generator
     */
    public function apply(Context $ctx, array $symbols, \Iterator $tokens, int $n, array $attribute): \Generator
    {
        $type = null;
        for ($tokens->rewind(); $tokens->valid(); $tokens->next()) {
            /** @var Token $token */
            $token = $tokens->current();
            switch ($token->getType()) {
                case Token::NAME:
                    $type = null;
                    $v = -1;

                    for ($i = 0; $i <= $n; $i++) {
                        if ($symbols[$i]->name === $token->getValue()) {
                            if ($v < 0) {
                                $v = $i;
                            } else {
                                throw new ParseException("Ambiguous semantic value reference for $token");
                            }
                        }
                    }

                    if ($v < 0) {
                        for ($i = 0; $i <= $n; $i++) {
                            if ($attribute[$i] === $token->getValue()) {
                                $v = $i;
                                break;
                            }
                        }

                        if ($token->getValue() === $attribute[$n + 1]) {
                            $v = 0;
                        }
                    }

                    if ($v >= 0) {
                        $token = new Token($v === 0 ? Token::DOLLAR : 0, $token->getValue(), $token->getLine(), $token->getFilename());
                        goto semval;
                    }
                    break;

                case Token::DOLLAR:
                    $type = null;
                    $token = self::next($tokens);
                    if ($token->getId() === '<') {
                        $token = self::next($tokens);
                        if ($token->getId() !== Token::NAME) {
                            throw ParseException::unexpected($token, Token::NAME);
                        }
                        $type = $ctx->intern($token->getValue());
                        $dump = self::next($tokens);
                        if ($dump->getId() !== '>') {
                            throw ParseException::unexpected($dump, '>');
                        }
                        $token = self::next($tokens);
                    }

                    if ($token->getType() === Token::DOLLAR) {
                        $v = 0;
                    } elseif ($token->getValue()[0] === '-') {
                        $token = self::next($tokens);
                        if ($token->getId() !== Token::NUMBER) {
                            throw ParseException::unexpected($token, Token::NUMBER);
                        }
                        $v = -1 * ((int) $token->getValue());
                    } else {
                        if ($token->getId() !== Token::NUMBER) {
                            throw new \RuntimeException('Number expected');
                        }
                        $v = (int) $token->getValue();
                        if ($v > $n) {
                            throw new \RuntimeException('N is too big');
                        }
                    }
semval:
                    if ($type === null) {
                        $type = $symbols[$v]->type;
                    }

                    if ($type === null /* && $ctx->unioned */ && false) {
                        throw new ParseException('Type not defined for '.$symbols[$v]->name);
                    }

                    foreach ($this->parseDollar($ctx, $token, $v, $n, $type ? $type->name : null) as $token) {
                        yield $token;
                    }

                    continue 2;
            }

            yield $token;
        }
    }

    /**
     * @param Context     $ctx
     * @param Token       $token
     * @param int         $nth
     * @param int         $len
     * @param string|null $type
     *
     * @return array
     */
    protected function parseDollar(Context $ctx, Token $token, int $nth, int $len, string $type = null): array
    {
        if ($token->getValue() === '$') {
            if ($type) {
                $mp = $ctx->macros[self::SEMVAL_LHS_TYPED];
            } else {
                $mp = $ctx->macros[self::SEMVAL_LHS_UNTYPED];
            }
        } else {
            if ($type) {
                $mp = $ctx->macros[self::SEMVAL_RHS_TYPED];
            } else {
                $mp = $ctx->macros[self::SEMVAL_RHS_UNTYPED];
            }
        }

        $result = '';
        for ($i = 0; $i < \mb_strlen($mp); $i++) {
            if ($mp[$i] === '%') {
                $i++;
                switch ($mp[$i]) {
                    case 'n':
                        $result .= \sprintf('%d', $nth);
                        break;
                    case 'l':
                        $result .= \sprintf('%d', $len);
                        break;
                    case 't':
                        $result .= $type;
                        break;
                    default:
                        $result .= $mp[$i];
                }
            } else {
                $result .= $mp[$i];
            }
        }

        return $this->parse($result, $token->getLine(), $token->getFilename());
    }
}
