<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Lalr;

/**
 * Class LalrResult.
 */
class LalrResult
{
    /**
     * @var array
     */
    public $grams;

    /**
     * @var int
     */
    public $countStates = 0;

    /**
     * @var array
     */
    public $states;

    /**
     * @var string
     */
    public $output;

    /**
     * @var int
     */
    public $countNonLeafStates;

    /**
     * LalrResult constructor.
     *
     * @param array  $grams
     * @param array  $states
     * @param int    $countNonLeafStates
     * @param string $output
     */
    public function __construct(array $grams, array $states, int $countNonLeafStates, string $output)
    {
        $this->grams = $grams;
        $this->states = $states;
        $this->countStates = \count($states);
        $this->output = $output;
        $this->countNonLeafStates = $countNonLeafStates;
    }
}
