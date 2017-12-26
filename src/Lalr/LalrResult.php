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
 * Class LalrResult
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
    public $nstates = 0;

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
    public $nnonleafstates;

    /**
     * LalrResult constructor.
     * @param array $grams
     * @param array $states
     * @param int $nnonleafstates
     * @param string $output
     */
    public function __construct(array $grams, array $states, int $nnonleafstates, string $output)
    {
        $this->grams = $grams;
        $this->states = $states;
        $this->nstates = \count($states);
        $this->output = $output;
        $this->nnonleafstates = $nnonleafstates;
    }
}
