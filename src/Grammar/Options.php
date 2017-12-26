<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Grammar;

/**
 * Class Options
 */
class Options
{
    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var bool
     */
    protected $verbose = false;

    /**
     * @var string
     */
    protected $className = 'YaccParser';

    /**
     * @var string
     */
    protected $templateFile;
}