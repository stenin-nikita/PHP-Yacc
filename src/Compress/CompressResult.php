<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Compress;

/**
 * Class CompressResult.
 */
class CompressResult
{
    /**
     * @var array
     */
    public $yytranslate = [];

    /**
     * @var array
     */
    public $yyaction = [];

    /**
     * @var array
     */
    public $yybase = [];

    /**
     * @var int
     */
    public $yybasesize;

    /**
     * @var array
     */
    public $yycheck = [];

    /**
     * @var array
     */
    public $yydefault = [];

    /**
     * @var array
     */
    public $yygoto = [];

    /**
     * @var array
     */
    public $yygbase = [];

    /**
     * @var array
     */
    public $yygcheck = [];

    /**
     * @var array
     */
    public $yygdefault = [];

    /**
     * @var array
     */
    public $yylhs = [];

    /**
     * @var array
     */
    public $yylen = [];

    /**
     * @var int
     */
    public $yyncterms = 0;

    /**
     * @var int
     */
    public $yytranslatesize = 0;
}
