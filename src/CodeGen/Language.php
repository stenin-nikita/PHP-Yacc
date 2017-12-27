<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\CodeGen;

/**
 * Interface Language.
 */
interface Language
{
    /**
     * @param $file
     * @param $headerFile
     */
    public function begin($file, $headerFile);

    /**
     * @return void
     */
    public function commit();

    /**
     * @param string $text
     * @param bool   $includeHeader
     */
    public function write(string $text, bool $includeHeader = false);

    /**
     * @param string $text
     */
    public function writeQuoted(string $text);

    /**
     * @param string $text
     */
    public function comment(string $text);

    /**
     * @param string $text
     */
    public function inline_comment(string $text);

    /**
     * @param string $indent
     * @param int    $num
     * @param string $value
     */
    public function case_block(string $indent, int $num, string $value);
}
