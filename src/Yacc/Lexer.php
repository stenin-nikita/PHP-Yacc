<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Yacc;

use PhpYacc\Support\Utils;
use PhpYacc\Exception\LexingException;
use PhpYacc\Exception\ParseException;

/**
 * Class Lexer
 */
class Lexer
{
    /**
     * End of file
     */
    public const EOF = "EOF";

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
     * @var bool
     */
    protected $prevIsDollar = false;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var int
     */
    protected $lineNumber = 0;

    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var int
     */
    protected $bufferOffset = 0;

    /**
     * @var string
     */
    protected $backChar;

    /**
     * @var string|null
     */
    protected $backToken;

    /**
     * @var Token
     */
    protected $token;

    /**
     * @param string $code
     * @param string $filename
     */
    public function startLexing(string $code, string $filename)
    {
        $this->filename = $filename;
        $this->buffer = $code;
        $this->bufferOffset = 0;
        $this->backChar = null;
        $this->backToken = null;
        $this->token = null;
        $this->prevIsDollar = false;
    }

    /**
     * @return int
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * @return Token
     * @throws LexingException
     * @throws ParseException
     */
    public function peek(): Token
    {
        $result = $this->get();
        $this->unget();

        return $result;
    }

    /**
     * @return Token
     * @throws LexingException
     * @throws ParseException
     */
    public function get(): Token
    {
        $this->token = $this->rawGet();

        while (in_array($this->token->t, self::SPACE_TOKENS)) {
            $this->token = $this->rawGet();
        }

        return $this->token;
    }

    /**
     * @throws LexingException
     */
    public function unget()
    {
        if ($this->backToken) {
            throw new LexingException("Too many unget Token calls");
        }

        $this->backToken = $this->token;
    }

    /**
     * @return Token
     * @throws LexingException
     * @throws ParseException
     */
    public function rawGet(): Token
    {
        if ($this->backToken !== null) {
            $this->token = $this->backToken;
            $this->backToken = null;

            return $this->token;
        }

        $c = $this->getc();

        $p = '';

        if (Utils::isWhite($c)) {
            while (Utils::isWhite($c)) {
                $p .= $c;
                $c = $this->getc();
            }
            $this->ungetc($c);

            return $this->token(Token::SPACE, $p);
        }

        if ($c === "\n") {
            $this->lineNumber++;

            return $this->token(Token::NEWLINE, $c);
        }

        if ($c === "/") {
            if (($c = $this->getc()) === '*') {
                // skip comments
                $p = "/*";

                while (true) {
                    if (($c = $this->getc()) === '*') {
                        if (($c = $this->getc()) === '/') {
                            break;
                        }
                        $this->ungetc($c);
                    }
                    if ($c === self::EOF) {
                        throw ParseException::unexpected($this->token(self::EOF, ''), "*/");
                    }
                    $p .= $c;
                }

                $p .= "*/";

                return $this->token(Token::COMMENT, $p);
            } elseif ($c === '/') {
                // skip // comment
                $p = '//';

                do {
                    $c = $this->getc();
                    if ($c !== self::EOF) {
                        $p .= $c;
                    }
                } while ($c !== "\n" && $c !== self::EOF);

                return $this->token(Token::COMMENT, $p);
            }

            $this->ungetc($c);
            $c = '/';
        }

        if ($c === self::EOF) {
            return $this->token(self::EOF, '');
        }

        $tag = $c;
        if ($c === '%') {
            $c = $this->getc();
            if ($c === '%' || $c === '{' | $c === '}' || Utils::isSymChar($c)) {
                $p .= "%";
            } else {
                $this->ungetc($c);
                $c = '%';
            }
        }

        if ($c === '$') {
            if (!$this->prevIsDollar) {
                $p .= '$';
                $c = $this->getc();
                if ($c === '$') {
                    $this->ungetc($c);
                    $this->prevIsDollar = true;
                } elseif (!ctype_digit($c) && Utils::isSymChar($c)) {
                    do {
                        $p .= $c;
                        $c = $this->getc();
                    } while (Utils::isSymChar($c));
                    $this->ungetc($c);
                    $tag = Token::NAME;
                } else {
                    $this->ungetc($c);
                }
            } else {
                $p .= '$';
                $this->prevIsDollar = false;
            }
        } elseif (Utils::isSymChar($c)) {
            do {
                $p .= $c;
                $c = $this->getc();
            } while ($c !== self::EOF && Utils::isSymChar($c));

            $this->ungetc($c);
            $tag = ctype_digit($p) ? Token::NUMBER : Token::NAME;
        } elseif ($c === '\'' || $c === '"') {
            $p .= $c;

            while (($c = $this->getc()) !== $tag) {
                if ($c === self::EOF) {
                    throw ParseException::unexpected($this->token("EOF", ''), $tag);
                }

                if ($c === "\n") {
                    throw ParseException::unexpected($this->token(Token::NEWLINE, "\n"), $tag);
                }

                $p .= $c;
                if ($c === '\\') {
                    $c = $this->getc();
                    if ($c === self::EOF) {
                        break;
                    }
                    if ($c === "\n") {
                        continue;
                    }
                    $p .= $c;
                }
            }
            $p .= $c;
        } else {
            $p .= $c;
        }

        if (isset(self::TAG_MAP[$p])) {
            $tag = self::TAG_MAP[$p];
        }

        return $this->token($tag, $p);
    }

    /**
     * @param $id
     * @param $value
     * @return Token
     */
    protected function token($id, $value): Token
    {
        return new Token($id, $value, $this->lineNumber, $this->filename);
    }

    /**
     * @return string
     */
    protected function getc(): string
    {
        if ($this->backChar !== null) {
            $result = $this->backChar;
            $this->backChar = null;

            return (string) $result;
        }

        if ($this->bufferOffset >= strlen($this->buffer)) {
            return self::EOF;
        }

        return $this->buffer[$this->bufferOffset++];
    }

    /**
     * @param string $c
     * @throws LexingException
     */
    protected function ungetc(string $c)
    {
        if ($c === self::EOF) {
            return;
        }

        if ($this->backChar !== null) {
            throw new LexingException("To many unget calls");
        }

        $this->backChar = $c;
    }
}
