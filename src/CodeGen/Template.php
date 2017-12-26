<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\CodeGen;

use PhpYacc\Exception\LogicException;
use PhpYacc\Exception\TemplateException;
use PhpYacc\Grammar\Context;
use PhpYacc\Compress\Compress;
use PhpYacc\Compress\CompressResult;
use PhpYacc\Support\Utils;
use PhpYacc\Yacc\Macro\DollarExpansion;

class Template
{
    /**
     * @var string
     */
    protected $metachar = '$';

    /**
     * @var array
     */
    protected $template = [];

    /**
     * @var int
     */
    protected $lineno = 0;

    /**
     * @var bool
     */
    protected $copy_header = false;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CompressResult
     */
    protected $compress;

    /**
     * @var Language
     */
    protected $language;

    /**
     * Template constructor.
     * @param Language $language
     * @param string $template
     * @param Context $context
     */
    public function __construct(Language $language, string $template, Context $context)
    {
        $this->language = $language;
        $this->context = $context;

        $this->parseTemplate($template);
    }

    /**
     * @param CompressResult $result
     * @param $resultFile
     * @param null $headerFile
     * @throws LogicException
     * @throws TemplateException
     */
    public function render(CompressResult $result, $resultFile, $headerFile = null)
    {
        $headerFile = $headerFile ?: \fopen('php://memory', 'rw');

        $this->language->begin($resultFile, $headerFile);

        $this->compress = $result;
        unset($result);

        $skipmode = false;
        $linechanged = false;
        $tailcode = false;
        $reducemode = [
            "enabled" => false,
            "m" => -1,
            "n" => 0,
            "mac" => [],
        ];
        $tokenmode = [
            "enabled" => false,
            "mac" => [],
        ];
        $buffer = '';
        $this->print_line();
        foreach ($this->template as $line) {
            $line .= "\n";
            if ($tailcode) {
                $this->language->write($buffer . $line);
                continue;
            }

            if ($skipmode) {
                if ($this->metamatch(\ltrim($line), 'endif')) {
                    $skipmode = false;
                }
                continue;
            }

            if ($reducemode['enabled']) {
                if ($this->metamatch(\trim($line), 'endreduce')) {
                    $reducemode['enabled'] = false;
                    $this->lineno++;
                    if ($reducemode['m'] < 0) {
                        $reducemode['m'] = $reducemode['n'];
                    }

                    foreach ($this->context->grams as $gram) {
                        if ($gram->action) {
                            for ($j = 0; $j < $reducemode['m']; $j++) {
                                $this->expand_mac($reducemode['mac'][$j], $gram->num, null);
                            }
                        } else {
                            for ($j = $reducemode['m']; $j < $reducemode['n']; $j++) {
                                $this->expand_mac($reducemode['mac'][$j], $gram->num, null);
                            }
                        }
                    }
                    continue;
                } elseif ($this->metamatch(\trim($line), 'noact')) {
                    $reducemode['m'] = $reducemode['n'];
                    continue;
                }
                $reducemode['mac'][$reducemode['n']++] = $line;
                continue;
            }

            if ($tokenmode['enabled']) {
                if ($this->metamatch(\trim($line), 'endtokenval')) {
                    $tokenmode['enabled'] = false;
                    $this->lineno++;
                    for ($i = 1; $i < $this->context->nterminals; $i++) {
                        $symbol = $this->context->symbol($i);
                        if ($symbol->name[0] != '\'') {
                            $str = $symbol->name;
                            if ($i === 1) {
                                $str = "YYERRTOK";
                            }
                            foreach ($tokenmode['mac'] as $mac) {
                                $this->expand_mac($mac, $symbol->value, $str);
                            }
                        }
                    }
                } else {
                    $tokenmode['mac'][] = $line;
                }
                continue;
            }
            $p = $line;
            $buffer = '';
            for ($i = 0; $i < \mb_strlen($line); $i++) {
                $p = \mb_substr($line, $i);
                if ($p[0] !== $this->metachar) {
                    $buffer .= $line[$i];
                } elseif ($i + 1 < \mb_strlen($line) && $p[1] === $this->metachar) {
                    $i++;
                    $buffer .= $this->metachar;
                } elseif ($this->metamatch($p, '(')) {
                    $start = $i + 2;
                    $val = \mb_substr($p, 2);
                    while ($i < \mb_strlen($line) && $line[$i] !== ')') {
                        $i++;
                    }
                    if (!isset($line[$i])) {
                        throw new TemplateException('$(: missing ")"');
                    }
                    $length = $i - $start;

                    $buffer .= $this->gen_valueof(\mb_substr($val, 0, $length));
                } elseif ($this->metamatch($p, 'TYPEOF(')) {
                    throw new LogicException("TYPEOF is not implemented");
                } else {
                    break;
                }
            }
            if (isset($p[0]) && $p[0] === $this->metachar) {
                if (\trim($buffer) !== '') {
                    throw new TemplateException("Non-blank character before \$-keyword");
                }
                if ($this->metamatch($p, 'header')) {
                    $this->copy_header = true;
                } elseif ($this->metamatch($p, 'endheader')) {
                    $this->copy_header = false;
                } elseif ($this->metamatch($p, 'tailcode')) {
                    $this->print_line();
                    $tailcode = true;
                    continue;
                } elseif ($this->metamatch($p, 'verification-table')) {
                    throw new TemplateException("verification-table is not implemented");
                } elseif ($this->metamatch($p, 'union')) {
                    throw new TemplateException("union is not implemented");
                } elseif ($this->metamatch($p, 'tokenval')) {
                    $tokenmode = [
                        "enabled" => true,
                        "mac" => [],
                    ];
                } elseif ($this->metamatch($p, 'reduce')) {
                    $reducemode = [
                        "enabled" => true,
                        "m" => -1,
                        "n" => 0,
                        "mac" => [],
                    ];
                } elseif ($this->metamatch($p, 'switch-for-token-name')) {
                    for ($i = 0; $i < $this->context->nterminals; $i++) {
                        if ($this->context->ctermindex[$i] >= 0) {
                            $symbol = $this->context->symbol($i);
                            $this->language->case_block($buffer, $symbol->value, $symbol->name);
                        }
                    }
                } elseif ($this->metamatch($p, 'production-strings')) {
                    foreach ($this->context->grams as $gram) {
                        $info = \array_slice($gram->body, 0);
                        $this->language->write($buffer . "\"");
                        $this->language->writeQuoted($info[0]->name);
                        $this->language->writeQuoted(' :');
                        if (\count($info) === 1) {
                            $this->language->writeQuoted(" /* empty */");
                        }
                        for ($i = 1; $i < \count($info); $i++) {
                            $this->language->writeQuoted(' ' . $info[$i]->name);
                        }
                        if ($gram->num + 1 === $this->context->ngrams) {
                            $this->language->write("\"\n");
                        } else {
                            $this->language->write("\",\n");
                        }
                    }
                } elseif ($this->metamatch($p, 'listvar')) {
                    $var = \trim(\mb_substr($p, 9));
                    $this->gen_list_var($buffer, $var);
                } elseif ($this->metamatch($p, 'ifnot')) {
                    $skipmode = $skipmode || !$this->skipif($p);
                } elseif ($this->metamatch($p, 'if')) {
                    $skipmode = $skipmode || $this->skipif($p);
                } elseif ($this->metamatch($p, 'endif')) {
                    $skipmode = false;
                } else {
                    throw new TemplateException("Unknown \$: $line");
                }
                $linechanged = true;
            } else {
                if ($linechanged) {
                    $this->print_line();
                    $linechanged = false;
                }
                $this->language->write($buffer, $this->copy_header);
            }
        }

        $this->language->commit();
    }

    /**
     * @param $spec string
     * @return bool
     * @throws TemplateException
     */
    protected function skipif(string $spec): bool
    {
        [ $dump, $test ] = \explode(' ', $spec, 2);
        $test = \trim($test);
        switch ($test) {
            case '-a':
                return $this->context->aflag;

            case '-t':
                return $this->context->tflag;

            case '-p':
                return !!$this->context->pspref;

            case '%union':
                return !!$this->context->union_body;

            case '%pure_parser':
                return $this->context->pureFlag;

            default:
                throw new TemplateException("$dump: unknown switch: $test");
        }
    }

    /**
     * @param string $def
     * @param int $value
     * @param string|null $str
     */
    protected function expand_mac(string $def, int $value, string $str = null)
    {
        $result = '';
        for ($i = 0; $i < \mb_strlen($def); $i++) {
            $p = $def[$i];
            if ($p === '%') {
                $p = $def[++$i];
                switch ($p) {
                    case 'n':
                        $result .= \sprintf('%d', $value);
                        break;

                    case 's':
                        $result .= $str !== null ? $str : '';
                        break;

                    case 'b':
                        $gram = $this->context->gram($value);
                        $this->print_line($gram->position);
                        $result .= $gram->action;
                        break;

                    default:
                        $result .= $p;
                        break;
                }
            } else {
                $result .= $p;
            }
        }

        $this->language->write($result, $this->copy_header);
    }

    /**
     * @param string $indent
     * @param string $var
     * @throws TemplateException
     */
    protected function gen_list_var(string $indent, string $var)
    {
        $size = -1;
        if (isset($this->compress->$var)) {
            $array = $this->compress->$var;
            if (isset($this->compress->{$var . 'size'})) {
                $size = $this->compress->{$var . 'size'};
            } elseif ($var === "yydefault") {
                $size = $this->context->nnonleafstates;
            } elseif (\in_array($var, ['yygbase', 'yygdefault'])) {
                $size = $this->context->nnonterminals;
            } elseif (\in_array($var, ['yylhs', 'yylen'])) {
                $size = $this->context->ngrams;
            }
            $this->print_array($array, $size < 0 ? count($array) : $size, $indent);
        } elseif ($var === 'terminals') {
            $nl = 0;
            foreach ($this->context->terminals as $term) {
                if ($this->context->ctermindex[$term->code] >= 0) {
                    $prefix = $nl++ ? ",\n" : "";
                    $this->language->write($prefix . $indent . "\"");
                    $this->language->writeQuoted($term->name);
                    $this->language->write("\"");
                }
            }
            $this->language->write("\n");
        } elseif ($var === 'nonterminals') {
            $nl = 0;
            foreach ($this->context->nonterminals as $nonterm) {
                $prefix = $nl++ ? ",\n" : "";
                $this->language->write($prefix . $indent . "\"");
                $this->language->writeQuoted($nonterm->name);
                $this->language->write("\"");
            }
            $this->language->write("\n");
        } else {
            throw new TemplateException("\$listvar: unknown variable $var");
        }
    }

    /**
     * @param array $array
     * @param int $limit
     * @param string $indent
     */
    protected function print_array(array $array, int $limit, string $indent)
    {
        $col = 0;
        for ($i = 0; $i < $limit; $i++) {
            if ($col === 0) {
                $this->language->write($indent);
            }
            $this->language->write(\sprintf($i + 1 === $limit ? "%5d" : "%5d,", $array[$i]));
            if (++$col === 10) {
                $this->language->write("\n");
                $col = 0;
            }
        }
        if ($col !== 0) {
            $this->language->write("\n");
        }
    }

    /**
     * @param string $var
     * @return string
     * @throws TemplateException
     */
    protected function gen_valueof(string $var): string
    {
        switch ($var) {
            case 'YYSTATES':
                return \sprintf('%d', $this->context->nstates);
            case 'YYNLSTATES':
                return \sprintf('%d', $this->context->nnonleafstates);
            case 'YYINTERRTOK':
                return \sprintf('%d', $this->compress->yytranslate[$this->context->errorToken->value]);
            case 'YYUNEXPECTED':
                return \sprintf('%d', Compress::YYUNEXPECTED);
            case 'YYDEFAULT':
                return \sprintf('%d', Compress::YYDEFAULT);
            case 'YYMAXLEX':
                return \sprintf('%d', \count($this->compress->yytranslate));
            case 'YYLAST':
                return \sprintf('%d', \count($this->compress->yyaction));
            case 'YYGLAST':
                return \sprintf('%d', \count($this->compress->yygoto));
            case 'YYTERMS':
            case 'YYBADCH':
                return \sprintf('%d', $this->compress->yyncterms);
            case 'YYNONTERMS':
                return \sprintf('%d', $this->context->nnonterminals);
            case 'YY2TBLSTATE':
                return \sprintf("%d", $this->compress->yybasesize - $this->context->nnonleafstates);
            case 'CLASSNAME':
            case '-p':
                return $this->context->pspref ?: 'yy';
            default:
                throw new TemplateException("Unknown variable: \$($var)");
        }
    }

    /**
     * @param string $template
     * @throws TemplateException
     */
    protected function parseTemplate(string $template)
    {
        $template = \preg_replace("(\r\n|\r)", "\n", $template);
        $lines = \explode("\n", $template);
        $this->lineno = 1;
        $skip = false;

        foreach ($lines as $line) {
            $p = $line;
            if ($skip) {
                $this->template[] = $line;
                continue;
            }
            while (\mb_strlen($p) > 0 && Utils::isWhite($p[0])) {
                $p = \mb_substr($p, 1);
            }
            $this->lineno++;
            if ($this->metamatch($p, "include")) {
                $skip = true;
            } elseif ($this->metamatch($p, "meta")) {
                if (! isset($p[6]) || Utils::isWhite($p[6])) {
                    throw new TemplateException("\$meta: missing character in definition: $p");
                }
                $this->metachar = $p[6];
            } elseif ($this->metamatch($p, "semval")) {
                $this->def_semval_macro(\mb_substr($p, 7));
            } else {
                $this->template[] = $line;
            }
        }
    }

    /**
     * @param string $text
     * @param string $keyword
     * @return bool
     */
    protected function metamatch(string $text, string $keyword): bool
    {
        return isset($text[0]) && $text[0] === $this->metachar && \mb_substr($text, 1, \mb_strlen($keyword)) === $keyword;
    }

    /**
     * @param string $macro
     * @throws TemplateException
     */
    protected function def_semval_macro(string $macro)
    {
        if (\mb_strpos($macro, '($)') !== false) {
            $this->context->macros[DollarExpansion::SEMVAL_LHS_UNTYPED] = \ltrim(\mb_substr($macro, 3));
        } elseif (\mb_strpos($macro, '($,%t)') !== false) {
            $this->context->macros[DollarExpansion::SEMVAL_LHS_TYPED] = \ltrim(\mb_substr($macro, 6));
        } elseif (\mb_strpos($macro, '(%n)') !== false) {
            $this->context->macros[DollarExpansion::SEMVAL_RHS_UNTYPED] = \ltrim(\mb_substr($macro, 4));
        } elseif (\mb_strpos($macro, '(%n,%t)') !== false) {
            $this->context->macros[DollarExpansion::SEMVAL_RHS_TYPED] = \ltrim(\mb_substr($macro, 7));
        } else {
            throw new TemplateException("\$semval: bad format $macro");
        }
    }

    /**
     * @param int $line
     * @param string|null $filename
     */
    protected function print_line(int $line = -1, string $filename = null)
    {
        if ($line === -1) {
            $line = $this->lineno;
        }
        if ($filename === null) {
            $filename = $this->context->filename;
        }
        //$this->language->inline_comment("{$filename}:$line");
    }
}
