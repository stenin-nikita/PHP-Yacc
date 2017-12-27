<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Exception;

use PhpYacc\Yacc\Token;

/**
 * Class ParseException.
 */
class ParseException extends PhpYaccException
{
    /**
     * @param Token $token
     * @param $expecting
     *
     * @return ParseException
     */
    public static function unexpected(Token $token, $expecting): self
    {
        return new self(\sprintf('Unexpected %s, expecting %s at %s:%d', Token::decode($token->t), Token::decode($expecting), $token->fn, $token->ln));
    }
}
