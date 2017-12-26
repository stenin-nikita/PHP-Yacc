<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Yacc;

use PhpYacc\Exception\LexingException;
use PhpYacc\Exception\ParseException;
use PhpYacc\Support\Utils;

/**
 * Class Lexer
 */
class Lexer
{
    /**
     * Whitespace tokens
     */
    private const SPACE_TOKENS = [
        Token::SPACE,
        Token::COMMENT,
        Token::NEWLINE,
    ];

    /**
     * Tag map
     */
    private const TAG_MAP = [
        "%%"            => Token::MARK,
        "%{"            => Token::BEGININC,
        "%}"            => Token::ENDINC,
        "%token"        => Token::TOKEN,
        "%term"         => Token::TOKEN,
        "%left"         => Token::LEFT,
        "%right"        => Token::RIGHT,
        "%nonassoc"     => Token::NONASSOC,
        "%prec"         => Token::PRECTOK,
        "%type"         => Token::TYPE,
        "%union"        => Token::UNION,
        "%start"        => Token::START,
        "%expect"       => Token::EXPECT,
        "%pure_parser"  => Token::PURE_PARSER,
    ];

    /**
     * @var string
     */
    protected $buffer;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var int
     */
    protected $line;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @var Token
     */
    protected $currentToken;

    /**
     * @var Token
     */
    protected $backToken;

    /**
     * @var string
     */
    protected $backChar;

    /**
     * @var bool
     */
    protected $prevIsDollar;

    /**
     * @param string $code
     * @param string $filename
     */
    public function startLexing(string $code, string $filename = '')
    {
        $this->buffer   = $code;
        $this->filename = $filename;

        $this->reset();
    }

    /**
     * @return void
     */
    protected function reset()
    {
        $this->line         = 1;
        $this->offset       = 0;
        $this->backChar     = null;
        $this->backToken    = null;
        $this->prevIsDollar = false;
    }

    /**
     * @return Token
     * @throws LexingException
     * @throws ParseException
     */
    public function getToken(): Token
    {
        $this->currentToken = $this->getRawToken();

        while (in_array($this->currentToken->getType(), self::SPACE_TOKENS)) {
            $this->currentToken = $this->getRawToken();
        }

        return $this->currentToken;
    }

    /**
     * @throws LexingException
     */
    public function ungetToken()
    {
        if ($this->backToken !== null) {
            throw new LexingException("Too many ungetToken calls");
        }

        $this->backToken = $this->currentToken;
    }

    /**
     * @return Token
     * @throws LexingException
     * @throws ParseException
     */
    public function peek(): Token
    {
        $result = $this->getToken();
        $this->ungetToken();

        return $result;
    }

    /**
     * @return Token
     * @throws LexingException
     * @throws ParseException
     */
    public function getRawToken()
    {
        if ($this->backToken !== null) {
            $this->currentToken = $this->backToken;
            $this->backToken = null;

            return $this->currentToken;
        }

        $char = $this->getChar();

        $buffer = "";

        // Whitespace
        if (Utils::isWhite($char)) {
            while (Utils::isWhite($char)) {
                $buffer .= $char;
                $char = $this->getChar();

            }
            $this->ungetChar($char);

            return $this->token(Token::SPACE, $buffer);
        }

        // End of line
        if ($char === "\n") {
            $this->line++;

            return $this->token(Token::NEWLINE, $char);
        }

        // Comment
        if ($char === '/') {
            if (($char = $this->getChar()) === '*') {
                $buffer = '/*';

                while (true) {
                    if (($char = $this->getChar()) === '*') {
                        if (($c = $this->getChar()) === '/') {
                            break;
                        }
                        $this->ungetChar($char);
                    }

                    if ($char === "\0") {
                        throw ParseException::unexpected($this->token(Token::EOF, "\0"), '*/');
                    }

                    $buffer .= $char;
                }

                $buffer .= '*/';

                return $this->token(Token::COMMENT, $buffer);
            } elseif ($char === '/') {
                $buffer = '//';

                do {
                    $char = $this->getChar();
                    if ($char !== "\0") {
                        $buffer .= $char;
                    }
                } while ($char !== "\n" && $char !== "\0");

                return $this->token(Token::COMMENT, $buffer);
            }

            $this->ungetChar($char);
            $char = '/';
        }

        // End of file
        if ($char === "\0") {
            return $this->token(Token::EOF, "\0");
        }

        $tag = Token::UNKNOW;

        if ($char === '%') {
            $char = $this->getChar();
            if ($char === '%' || $char === '{' | $char === '}' || Utils::isSymChar($char)) {
                $buffer .= "%";
            } else {
                $this->ungetChar($char);
                $char = '%';
            }
        }

        if ($char === '$') {
            if (! $this->prevIsDollar) {
                $buffer .= '$';
                $char = $this->getChar();

                if ($char === '$') {
                    $this->ungetChar($char);
                    $this->prevIsDollar = true;
                } elseif (! \ctype_digit($char) && Utils::isSymChar($char)) {
                    do {
                        $buffer .= $char;
                        $char = $this->getChar();
                    } while (Utils::isSymChar($char));
                    $this->ungetChar($char);
                    $tag = Token::NAME;
                } else {
                    $this->ungetChar($char);
                }
            } else {
                $buffer .= '$';
                $this->prevIsDollar = false;
            }
        } elseif (Utils::isSymChar($char)) {
            do {
                $buffer .= $char;
                $char = $this->getChar();
            } while ($char !== "\0" && Utils::isSymChar($char));

            $this->ungetChar($char);
            $tag = \ctype_digit($buffer) ? Token::NUMBER : Token::NAME;
        } elseif ($char === '\'' || $char === '"') {
            $quote = $char;
            $buffer .= $char;

            while (($char = $this->getChar()) !== $quote) {
                if ($char === "\0") {
                    throw ParseException::unexpected($this->token(Token::EOF, "\0"), $quote);
                }

                if ($char === "\n") {
                    throw ParseException::unexpected($this->token(Token::NEWLINE, "\n"), $quote);
                }

                $buffer .= $char;
                if ($char === '\\') {
                    $char = $this->getChar();

                    if ($char === "\0") {
                        break;
                    }

                    if ($char === "\n") {
                        continue;
                    }

                    $buffer .= $char;
                }
            }
            $buffer .= $char;
            $tag = Token::STRING;
        } else {
            $buffer .= $char;
        }

        if (isset(self::TAG_MAP[$buffer])) {
            return $this->token(self::TAG_MAP[$buffer], $buffer);
        }

        if ($buffer === ':') {
            return $this->token(Token::COLON, $buffer);
        }
        if ($buffer === ';') {
            return $this->token(Token::SEMICOLON, $buffer);
        }

        return $this->token($tag, $buffer);
    }

    /**
     * @return string
     */
    protected function getChar(): string
    {
        if ($this->backChar !== null) {
            $result = $this->backChar;
            $this->backChar = null;

            return $result;
        }

        if ($this->offset >= \mb_strlen($this->buffer)) {
            return "\0";
        }

        return $this->buffer[$this->offset++];
    }

    /**
     * @param string $char
     * @throws LexingException
     */
    protected function ungetChar(string $char)
    {
        if ($char == "\0") {
            return;
        }

        if ($this->backChar !== null) {
            throw new LexingException("To many ungetChar calls");
        }

        $this->backChar = $char;
    }

    /**
     * @param int $type
     * @param string $value
     * @return Token
     */
    protected function token(int $type, string $value): Token
    {
        return new Token($type, $value, $this->line, $this->filename);
    }
}
