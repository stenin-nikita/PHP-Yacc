<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Yacc;

use PhpYacc\Grammar\Context;
use PhpYacc\Yacc\Macro\DollarExpansion;

/**
 * Class MacroSet.
 */
class MacroSet
{
    /**
     * @var array
     */
    protected $macros = [];

    /**
     * MacroSet constructor.
     *
     * @param MacroAbstract[] ...$macros
     */
    public function __construct(MacroAbstract ...$macros)
    {
        $this->addMacro(new DollarExpansion());
        $this->addMacro(...$macros);
    }

    /**
     * @param MacroAbstract[] ...$macros
     */
    public function addMacro(MacroAbstract ...$macros)
    {
        foreach ($macros as $macro) {
            $this->macros[] = $macro;
        }
    }

    /**
     * @param Context $ctx
     * @param array   $symbols
     * @param array   $tokens
     * @param int     $n
     * @param array   $attribute
     *
     * @return array
     */
    public function apply(Context $ctx, array $symbols, array $tokens, int $n, array $attribute): array
    {
        $tokens = new \ArrayIterator($tokens);
        $macroCount = \count($this->macros);

        if ($macroCount === 1) {
            // special case
            return \iterator_to_array($this->macros[0]->apply($ctx, $symbols, $tokens, $n, $attribute));
        }

        foreach ($this->macros as $macro) {
            $tokens = $macro->apply($ctx, $symbols, $tokens, $n, $attribute);
        }

        $tokens = self::cache($tokens);

        return \iterator_to_array($tokens);
    }

    /**
     * @param \Traversable $t
     *
     * @return \Traversable
     */
    public static function cache(\Traversable $t): \Traversable
    {
        return new \ArrayIterator(iterator_to_array($t));
    }
}
