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
    public const EOF = -1;
    public const UNKNOW = 0;
    public const NAME = 1;
    public const NUMBER = 2;
    public const COLON = 3;
    public const SPACE = 4;
    public const NEWLINE = 5;
    public const MARK = 6;
    public const BEGININC = 7;
    public const ENDINC = 8;
    public const TOKEN = 9;
    public const LEFT = 10;
    public const RIGHT = 11;
    public const NONASSOC = 12;
    public const PRECTOK = 13;
    public const TYPE = 14;
    public const UNION = 15;
    public const START = 16;
    public const COMMENT = 17;
    public const EXPECT = 18;
    public const PURE_PARSER = 19;
    public const STRING = 20;
    public const COMMA = 21;
    public const SEMICOLON = 22;
    public const DOLLAR = 23;

    private const TOKEN_MAP = [
        self::NAME            => 'NAME',
        self::NUMBER          => 'NUMBER',
        self::COLON           => 'COLON',
        self::SPACE           => 'SPACE',
        self::NEWLINE         => 'NEWLINE',
        self::MARK            => 'MARK',
        self::BEGININC        => 'BEGININC',
        self::ENDINC          => 'ENDINC',
        self::TOKEN           => 'TOKEN',
        self::LEFT            => 'LEFT',
        self::RIGHT           => 'RIGHT',
        self::NONASSOC        => 'NONASSOC',
        self::PRECTOK         => 'PRECTOK',
        self::TYPE            => 'TYPE',
        self::UNION           => 'UNION',
        self::START           => 'START',
        self::COMMENT         => 'COMMENT',
        self::EXPECT          => 'EXPECT',
        self::PURE_PARSER     => 'PURE_PARSER',
        self::EOF             => 'EOF',
        self::UNKNOW          => 'UNKNOW',
        self::STRING          => 'STRING',
        self::COMMA           => 'COMMA',
        self::SEMICOLON       => 'SEMICOLON',
        self::DOLLAR          => 'DOLLAR',
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
     * @deprecated
     *
     * @return int|string
     */
    public function getId()
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

        return sprintf('[%s:%d] %s(%s)', $this->filename, $this->line, $tag, $this->value);
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
