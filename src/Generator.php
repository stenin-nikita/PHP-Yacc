<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc;

use PhpYacc\CodeGen\Language\PHP;
use PhpYacc\CodeGen\Template;
use PhpYacc\Compress\Compress;
use PhpYacc\Grammar\Context;
use PhpYacc\Lalr\Generator as Lalr;
use PhpYacc\Yacc\Lexer;
use PhpYacc\Yacc\MacroSet;
use PhpYacc\Yacc\Parser;

/**
 * Class Generator.
 */
class Generator
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var Lalr
     */
    protected $lalr;

    /**
     * @var Compress
     */
    protected $compressor;

    /**
     * Generator constructor.
     *
     * @param Parser|null   $parser
     * @param Lalr|null     $lalr
     * @param Compress|null $compressor
     */
    public function __construct(Parser $parser = null, Lalr $lalr = null, Compress $compressor = null)
    {
        $this->parser = $parser ?: new Parser(new Lexer(), new MacroSet());
        $this->lalr = $lalr ?: new Lalr();
        $this->compressor = $compressor ?: new Compress();
    }

    /**
     * @param Context $context
     * @param string  $grammar
     * @param string  $template
     * @param string  $resultFile
     *
     * @throws Exception\LexingException
     * @throws Exception\LogicException
     * @throws Exception\ParseException
     * @throws Exception\TemplateException
     *
     * @return void
     */
    public function generate(Context $context, string $grammar, string $template, string $resultFile)
    {
        $template = new Template(new PHP(), $template, $context);

        $this->parser->parse($grammar, $context);

        $this->lalr->compute($context);

        $template->render($this->compressor->compress($context), \fopen($resultFile, 'w'));
    }
}
