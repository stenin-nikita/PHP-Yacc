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
    public $yytranslate = [];

    public $yyaction = [];

    public $yybase = [];

    public $yybasesize;

    public $yycheck = [];

    public $yydefault = [];

    public $yygoto = [];

    public $yygbase = [];

    public $yygcheck = [];

    public $yygdefault = [];

    public $yylhs = [];

    public $yylen = [];

    public $yyncterms = 0;

    public $yytranslatesize = 0;
}
