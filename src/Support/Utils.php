<?php
/**
 * This file is part of Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Support;

/**
 * Class Utils.
 */
final class Utils
{
    /**
     * @param string $char
     *
     * @return bool
     */
    public static function isWhite(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\r" || $char === "\x0b" || $char === "\x0c";
    }

    /**
     * @param string $char
     *
     * @return bool
     */
    public static function isSymChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }

    /**
     * @param string $char
     *
     * @return bool
     */
    public static function isOctal(string $char): bool
    {
        $n = \ord($char);

        return $n >= 48 && $n <= 55;
    }

    /**
     * @param string $string
     *
     * @return int
     */
    public static function characterValue(string $string): int
    {
        $n = 0;
        $length = \mb_strlen($string);

        if ($length === 0) {
            return 0;
        }

        $c = $string[$n++];

        if ($c !== '\\') {
            return \ord($c);
        }

        $c = $string[$n++];
        if (self::isOctal($c)) {
            $value = (int) $c;
            for ($i = 0; $n < $length && self::isOctal($string[$n]) && $i < 3; $i++) {
                $value = $value * 8 + $string[$n++];
            }

            return $value;
        }

        switch ($c) {
            case 'n':
                return \ord("\n");
            case 't':
                return \ord("\t");
            case 'b':
                return \ord("\x08");
            case 'r':
                return \ord("\r");
            case 'f':
                return \ord("\x0C");
            case 'v':
                return \ord("\x0B");
            case 'a':
                return \ord("\x07");
            default:
                return \ord($c);
        }
    }

    /**
     * @param array    $array
     * @param callable $cmp
     */
    public static function stableSort(array &$array, callable $cmp)
    {
        $indexedArray = [];
        $i = 0;
        foreach ($array as $item) {
            $indexedArray[] = [$item, $i++];
        }

        \usort($indexedArray, function (array $a, array $b) use ($cmp) {
            $result = $cmp($a[0], $b[0]);
            if ($result !== 0) {
                return $result;
            }

            return $a[1] - $b[1];
        });

        $array = [];
        foreach ($indexedArray as $item) {
            $array[] = $item[0];
        }
    }
}
