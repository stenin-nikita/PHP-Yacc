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
 * Class Lexer.
 */
class Lexer
{
    /**
     * Whitespace tokens.
     */
    private const SPACE_TOKENS = [
        Token::T_SPACE,
        Token::T_COMMENT,
        Token::T_NEWLINE,
    ];

    /**
     * Tag map.
     */
    private const TAG_MAP = [
        ':'             => Token::T_COLON,
        ';'             => Token::T_SEMICOLON,
        '$'             => Token::T_DOLLAR,
        '%%'            => Token::T_MARK,
        '%{'            => Token::T_BEGIN_INC,
        '%}'            => Token::T_END_INC,
        '%token'        => Token::T_TOKEN,
        '%term'         => Token::T_TOKEN,
        '%left'         => Token::T_LEFT,
        '%right'        => Token::T_RIGHT,
        '%nonassoc'     => Token::T_NON_ASSOC,
        '%prec'         => Token::T_PRECTOK,
        '%type'         => Token::T_TYPE,
        '%union'        => Token::T_UNION,
        '%start'        => Token::T_START,
        '%expect'       => Token::T_EXPECT,
        '%pure_parser'  => Token::T_PURE_PARSER,
    ];

    /**
     * @var string
     */
    protected $buffer;

    /**
     * @var string
     */
    protected $fileName;

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
     * @var string
     */
    protected $char;

    /**
     * @var string
     */
    protected $value;

    /**
     * @param string $code
     * @param string $filename
     */
    public function startLexing(string $code, string $filename = '')
    {
        $this->buffer = $code;
        $this->fileName = $filename;

        $this->reset();
    }

    /**
     * @return void
     */
    protected function reset()
    {
        $this->line = 1;
        $this->offset = 0;
        $this->backChar = null;
        $this->backToken = null;
        $this->prevIsDollar = false;
    }

    /**
     * @throws LexingException
     * @throws ParseException
     *
     * @return Token
     */
    public function getToken(): Token
    {
        $this->currentToken = $this->getRawToken();

        while (\in_array($this->currentToken->getType(), self::SPACE_TOKENS)) {
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
            throw new LexingException('Too many ungetToken calls');
        }

        $this->backToken = $this->currentToken;
    }

    /**
     * @throws LexingException
     * @throws ParseException
     *
     * @return Token
     */
    public function peek(): Token
    {
        $result = $this->getToken();
        $this->ungetToken();

        return $result;
    }

    /**
     * @throws LexingException
     * @throws ParseException
     *
     * @return Token
     */
    public function getRawToken()
    {
        if ($this->backToken !== null) {
            $this->currentToken = $this->backToken;
            $this->backToken = null;

            return $this->currentToken;
        }

        $this->char = $this->getChar();
        $this->value = '';

        switch (true) {
            case $this->isWhitespace():
                return $this->token(Token::T_SPACE, $this->value);
            case $this->isNewline():
                return $this->token(Token::T_NEWLINE, $this->value);
            case $this->isComment():
                return $this->token(Token::T_COMMENT, $this->value);
            case $this->isEof():
                return $this->token(Token::T_EOF, $this->value);
        }

        $tag = $this->detectToken();

        switch (true) {
            case isset(self::TAG_MAP[$this->value]):
                return $this->token(self::TAG_MAP[$this->value], $this->value);
            default:
                return $this->token($tag, $this->value);
        }
    }

    /**
     * @throws LexingException
     *
     * @return bool
     */
    protected function isWhitespace(): bool
    {
        if (Utils::isWhite($this->char)) {
            while (Utils::isWhite($this->char)) {
                $this->value .= $this->char;
                $this->char = $this->getChar();
            }
            $this->ungetChar($this->char);

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function isNewline(): bool
    {
        if ($this->char === "\n") {
            $this->value = $this->char;

            return true;
        }

        return false;
    }

    /**
     * @throws LexingException
     * @throws ParseException
     *
     * @return bool
     */
    protected function isComment(): bool
    {
        if ($this->char === '/') {
            if (($this->char = $this->getChar()) === '*') {
                $this->value = '/*';

                while (true) {
                    if (($this->char = $this->getChar()) === '*') {
                        if (($this->char = $this->getChar()) === '/') {
                            break;
                        }
                        $this->ungetChar($this->char);
                    }

                    if ($this->char === "\0") {
                        throw ParseException::unexpected($this->token(Token::T_EOF, "\0"), '*/');
                    }

                    $this->value .= $this->char;
                }

                $this->value .= '*/';

                return true;
            } elseif ($this->char === '/') {
                $this->value = '//';

                do {
                    $this->char = $this->getChar();
                    if ($this->char !== "\0") {
                        $this->value .= $this->char;
                    }
                } while ($this->char !== "\n" && $this->char !== "\0");

                return true;
            }

            $this->ungetChar($this->char);
            $this->char = '/';
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function isEof(): bool
    {
        if ($this->char === "\0") {
            $this->value = $this->char;

            return true;
        }

        return false;
    }

    /**
     * @throws LexingException
     * @throws ParseException
     *
     * @return int
     */
    protected function detectToken()
    {
        $tag = Token::T_UNKNOWN;

        if ($this->char === '%') {
            $this->char = $this->getChar();
            if ($this->char === '%' || \in_array($this->char, ['{', '}'], true) || Utils::isSymChar($this->char)) {
                $this->value .= '%';
            } else {
                $this->ungetChar($this->char);
                $this->char = '%';
            }
        }

        if ($this->char === '$') {
            if (!$this->prevIsDollar) {
                $this->value .= '$';
                $this->char = $this->getChar();

                if ($this->char === '$') {
                    $this->ungetChar($this->char);
                    $this->prevIsDollar = true;
                } elseif (!\ctype_digit($this->char) && Utils::isSymChar($this->char)) {
                    do {
                        $this->value .= $this->char;
                        $this->char = $this->getChar();
                    } while (Utils::isSymChar($this->char));
                    $this->ungetChar($this->char);
                    $tag = Token::T_NAME;
                } else {
                    $this->ungetChar($this->char);
                }
            } else {
                $this->value .= '$';
                $this->prevIsDollar = false;
            }
        } elseif (Utils::isSymChar($this->char)) {
            do {
                $this->value .= $this->char;
                $this->char = $this->getChar();
            } while ($this->char !== "\0" && Utils::isSymChar($this->char));

            $this->ungetChar($this->char);
            $tag = \ctype_digit($this->value) ? Token::T_NUMBER : Token::T_NAME;
        } elseif ($this->char === '\'' || $this->char === '"') {
            $quote = $this->char;
            $this->value .= $this->char;

            while (($this->char = $this->getChar()) !== $quote) {
                if ($this->char === "\0") {
                    throw ParseException::unexpected($this->token(Token::T_EOF, "\0"), $quote);
                }

                if ($this->char === "\n") {
                    throw ParseException::unexpected($this->token(Token::T_NEWLINE, "\n"), $quote);
                }

                $this->value .= $this->char;
                if ($this->char === '\\') {
                    $this->char = $this->getChar();

                    if ($this->char === "\0") {
                        break;
                    }

                    if ($this->char === "\n") {
                        continue;
                    }

                    $this->value .= $this->char;
                }
            }
            $this->value .= $this->char;
            $tag = Token::T_STRING;
        } else {
            $this->value .= $this->char;
        }

        return $tag;
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

        $char = $this->buffer[$this->offset++];

        if ($char === "\n") {
            $this->line++;
        }

        return $char;
    }

    /**
     * @param string $char
     *
     * @throws LexingException
     */
    protected function ungetChar(string $char)
    {
        if ($char == "\0") {
            return;
        }

        if ($this->backChar !== null) {
            throw new LexingException('To many ungetChar calls');
        }

        $this->backChar = $char;
    }

    /**
     * @param int    $type
     * @param string $value
     *
     * @return Token
     */
    protected function token(int $type, string $value): Token
    {
        return new Token($type, $value, $this->line, $this->fileName);
    }
}
