<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc;

use PhpYacc\Grammar\Context;

/**
 * Interface Macro
 */
interface Macro
{
    /**
     * @param Context $ctx
     * @param array $symbols
     * @param \Iterator $tokens
     * @param int $n
     * @param array $attribute
     * @return \Generator
     */
    public function apply(Context $ctx, array $symbols, \Iterator $tokens, int $n, array $attribute): \Generator;
}
