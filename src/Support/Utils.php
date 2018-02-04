<?php
/**
 * This file is part of Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Support;

use PhpYacc\Compress\Compress;
use PhpYacc\Grammar\Context;
use PhpYacc\Lalr\BitSet;
use PhpYacc\Lalr\Lr1;

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

    /**
     * @param array $array
     * @param int   $length
     *
     * @return bool
     */
    public static function vacantRow(array $array, int $length): bool
    {
        for ($i = 0; $i < $length; $i++) {
            if ($array[$i] !== Compress::VACANT) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $a
     * @param array $b
     * @param int   $length
     *
     * @return bool
     */
    public static function eqRow(array $a, array $b, int $length): bool
    {
        for ($i = 0; $i < $length; $i++) {
            if ($a[$i] !== $b[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $action
     *
     * @return string
     */
    public static function printAction(int $action): string
    {
        if ($action === Compress::VACANT) {
            return '  . ';
        }

        return \sprintf('%4d', $action);
    }

    /**
     * @param Lr1|null $left
     * @param Lr1|null $right
     *
     * @return bool
     */
    public static function isSameSet(Lr1 $left = null, Lr1 $right = null): bool
    {
        $p = $left;
        $t = $right;
        while ($t !== null) {
            // Not using !== here intentionally
            if ($p === null || $p->item != $t->item) {
                return false;
            }
            $p = $p->next;
            $t = $t->next;
        }

        return $p === null || $p->isHeadItem();
    }

    /**
     * @param Context $ctx
     * @param BitSet  $set
     *
     * @return string
     */
    public static function dumpSet(Context $ctx, BitSet $set): string
    {
        $result = '';
        foreach ($set as $code) {
            $symbol = $ctx->symbols[$code];
            $result .= "{$symbol->name} ";
        }

        return $result;
    }
}
