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

/**
 * Class Token.
 */
class Token
{
    public const T_EOF = -1;
    public const T_UNKNOWN = 0;
    public const T_NAME = 1;
    public const T_NUMBER = 2;
    public const T_COLON = 3;
    public const T_SPACE = 4;
    public const T_NEWLINE = 5;
    public const T_MARK = 6;
    public const T_BEGIN_INC = 7;
    public const T_END_INC = 8;
    public const T_TOKEN = 9;
    public const T_LEFT = 10;
    public const T_RIGHT = 11;
    public const T_NON_ASSOC = 12;
    public const T_PRECTOK = 13;
    public const T_TYPE = 14;
    public const T_UNION = 15;
    public const T_START = 16;
    public const T_COMMENT = 17;
    public const T_EXPECT = 18;
    public const T_PURE_PARSER = 19;
    public const T_STRING = 20;
    public const T_COMMA = 21;
    public const T_SEMICOLON = 22;
    public const T_DOLLAR = 23;

    private const TOKEN_MAP = [
        self::T_NAME            => 'NAME',
        self::T_NUMBER          => 'NUMBER',
        self::T_COLON           => 'COLON',
        self::T_SPACE           => 'SPACE',
        self::T_NEWLINE         => 'NEWLINE',
        self::T_MARK            => 'MARK',
        self::T_BEGIN_INC        => 'BEGININC',
        self::T_END_INC          => 'ENDINC',
        self::T_TOKEN           => 'TOKEN',
        self::T_LEFT            => 'LEFT',
        self::T_RIGHT           => 'RIGHT',
        self::T_NON_ASSOC        => 'NONASSOC',
        self::T_PRECTOK         => 'PRECTOK',
        self::T_TYPE            => 'TYPE',
        self::T_UNION           => 'UNION',
        self::T_START           => 'START',
        self::T_COMMENT         => 'COMMENT',
        self::T_EXPECT          => 'EXPECT',
        self::T_PURE_PARSER     => 'PURE_PARSER',
        self::T_EOF             => 'EOF',
        self::T_UNKNOWN          => 'UNKNOW',
        self::T_STRING          => 'STRING',
        self::T_COMMA           => 'COMMA',
        self::T_SEMICOLON       => 'SEMICOLON',
        self::T_DOLLAR          => 'DOLLAR',
    ];

    /**
     * @var int
     */
    protected $type;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var int
     */
    protected $line;

    /**
     * @var string
     */
    protected $filename;

    /**
     * Token constructor.
     *
     * @param int    $type
     * @param string $value
     * @param int    $line
     * @param string $filename
     *
     * @throws LexingException
     */
    public function __construct(int $type, string $value, int $line = 0, string $filename = '')
    {
        if (!isset(self::TOKEN_MAP[$type])) {
            throw new LexingException("Unknown token found: $type");
        }

        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
        $this->filename = $filename;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $tag = self::decode($this->type);

        return \sprintf('[%s:%d] %s(%s)', $this->filename, $this->line, $tag, $this->value);
    }

    /**
     * @param $tag
     *
     * @return string
     */
    public static function decode($tag): string
    {
        if (!isset(self::TOKEN_MAP[$tag])) {
            return "$tag";
        }

        return 'Token::'.self::TOKEN_MAP[$tag];
    }
}
