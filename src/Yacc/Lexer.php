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
        Token::SPACE,
        Token::COMMENT,
        Token::NEWLINE,
    ];

    /**
     * Tag map.
     */
    private const TAG_MAP = [
        '%%'            => Token::MARK,
        '%{'            => Token::BEGININC,
        '%}'            => Token::ENDINC,
        '%token'        => Token::TOKEN,
        '%term'         => Token::TOKEN,
        '%left'         => Token::LEFT,
        '%right'        => Token::RIGHT,
        '%nonassoc'     => Token::NONASSOC,
        '%prec'         => Token::PRECTOK,
        '%type'         => Token::TYPE,
        '%union'        => Token::UNION,
        '%start'        => Token::START,
        '%expect'       => Token::EXPECT,
        '%pure_parser'  => Token::PURE_PARSER,
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
        $this->filename = $filename;

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
                return $this->token(Token::SPACE, $this->value);
            case $this->isNewline():
                return $this->token(Token::NEWLINE, $this->value);
            case $this->isComment():
                return $this->token(Token::COMMENT, $this->value);
            case $this->isEof():
                return $this->token(Token::EOF, $this->value);
        }

        $tag = $this->detectToken();

        switch (true) {
            case isset(self::TAG_MAP[$this->value]):
                return $this->token(self::TAG_MAP[$this->value], $this->value);
            case $this->value === ':':
                return $this->token(Token::COLON, $this->value);
            case $this->value === ';':
                return $this->token(Token::SEMICOLON, $this->value);
            case $this->value === '$':
                return $this->token(Token::DOLLAR, $this->value);
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
                        throw ParseException::unexpected($this->token(Token::EOF, "\0"), '*/');
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
        $tag = Token::UNKNOW;

        if ($this->char === '%') {
            $this->char = $this->getChar();
            if ($this->char === '%' || $this->char === '{' | $this->char === '}' || Utils::isSymChar($this->char)) {
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
                    $tag = Token::NAME;
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
            $tag = \ctype_digit($this->value) ? Token::NUMBER : Token::NAME;
        } elseif ($this->char === '\'' || $this->char === '"') {
            $quote = $this->char;
            $this->value .= $this->char;

            while (($this->char = $this->getChar()) !== $quote) {
                if ($this->char === "\0") {
                    throw ParseException::unexpected($this->token(Token::EOF, "\0"), $quote);
                }

                if ($this->char === "\n") {
                    throw ParseException::unexpected($this->token(Token::NEWLINE, "\n"), $quote);
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
            $tag = Token::STRING;
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
        return new Token($type, $value, $this->line, $this->filename);
    }
}
