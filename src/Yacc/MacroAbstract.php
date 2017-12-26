<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Yacc;

use PhpYacc\Exception\LogicException;
use PhpYacc\Macro;
use PhpYacc\Support\Utils;

/**
 * Class MacroAbstract
 */
abstract class MacroAbstract implements Macro
{
    /**
     * @param string $string
     * @param int $lineNumber
     * @param string $filename
     * @return array
     */
    protected function parse(string $string, int $lineNumber, string $filename): array
    {
        $i = 0;
        $length = \mb_strlen($string);
        $buffer = '';
        $tokens = [];

        while ($i < $length) {
            if (Utils::isSymChar($string[$i])) {
                do {
                    $buffer .= $string[$i++];
                } while ($i < $length && Utils::isSymChar($string[$i]));

                $type = \ctype_digit($buffer) ? Token::NUMBER : Token::NAME;
                $tokens[] = new Token($type, $buffer, $lineNumber, $filename);
                $buffer = '';
            } else {
                $tokens[] = new Token(Token::UNKNOW, $string[$i++], $lineNumber, $filename);
            }
        }

        return $tokens;
    }

    /**
     * @param \Iterator $it
     * @return Token
     * @throws LogicException
     */
    protected static function next(\Iterator $it): Token
    {
        $it->next();

        if (!$it->valid()) {
            throw new LogicException("Unexpected end of action stream: this should never happen");
        }

        return $it->current();
    }
}
