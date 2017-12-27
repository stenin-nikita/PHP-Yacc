<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Compress;

function vacant_row(array $array, int $length): bool
{
    for ($i = 0; $i < $length; $i++) {
        if ($array[$i] !== Compress::VACANT) {
            return false;
        }
    }

    return true;
}

function eq_row(array $a, array $b, int $length): bool
{
    for ($i = 0; $i < $length; $i++) {
        if ($a[$i] !== $b[$i]) {
            return false;
        }
    }

    return true;
}

function printact(int $act): string
{
    if (Compress::VACANT === $act) {
        return '  . ';
    }

    return \sprintf('%4d', $act);
}
