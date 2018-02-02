<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\CodeGen\Language;

use PhpYacc\CodeGen\Language;

/**
 * Class PHP.
 */
class PHP implements Language
{
    /**
     * @var resource
     */
    protected $fp;

    /**
     * @var resource
     */
    protected $hp;

    /**
     * @var string
     */
    protected $fileBuffer = '';

    /**
     * @var string
     */
    protected $headerBuffer = '';

    /**
     * @param $file
     * @param $headerFile
     */
    public function begin($file, $headerFile)
    {
        $this->fp = $file;
        $this->hp = $headerFile;
        $this->fileBuffer = '';
        $this->headerBuffer = '';
    }

    /**
     * @return void
     */
    public function commit()
    {
        \fwrite($this->fp, $this->fileBuffer);
        \fwrite($this->hp, $this->headerBuffer);
        $this->fp = $this->hp = null;
        $this->fileBuffer = '';
        $this->headerBuffer = '';
    }

    /**
     * @param string $text
     */
    public function inline_comment(string $text)
    {
        $this->fileBuffer .= '/* '.$text.' */';
    }

    /**
     * @param string $text
     */
    public function comment(string $text)
    {
        $this->fileBuffer .= '//'.$text."\n";
    }

    /**
     * @param string $indent
     * @param int    $num
     * @param string $value
     */
    public function case_block(string $indent, int $num, string $value)
    {
        $this->fileBuffer .= \sprintf("%scase %d: return %s;\n", $indent, $num, \var_export($value, true));
    }

    /**
     * @param string $text
     * @param bool   $includeHeader
     */
    public function write(string $text, bool $includeHeader = false)
    {
        $this->fileBuffer .= $text;
        if ($includeHeader) {
            $this->headerBuffer .= $text;
        }
    }

    /**
     * @param string $text
     */
    public function writeQuoted(string $text)
    {
        $buf = [];
        for ($i = 0; $i < \mb_strlen($text); $i++) {
            $char = $text[$i];

            if ($char == '\\' || $char == '"' || $char == '$') {
                $buf[] = '\\';
            }

            $buf[] = $char;
        }

        $this->fileBuffer .= implode('', $buf);
    }
}
