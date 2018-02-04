<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\CodeGen;

use PhpYacc\Compress\Compress;
use PhpYacc\Compress\CompressResult;
use PhpYacc\Exception\LogicException;
use PhpYacc\Exception\TemplateException;
use PhpYacc\Grammar\Context;
use PhpYacc\Support\Utils;
use PhpYacc\Yacc\Macro\DollarExpansion;

class Template
{
    /**
     * @var string
     */
    protected $metaChar = '$';

    /**
     * @var array
     */
    protected $template = [];

    /**
     * @var int
     */
    protected $lineNumber = 0;

    /**
     * @var bool
     */
    protected $copyHeader = false;

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
     *
     * @param Language $language
     * @param string   $template
     * @param Context  $context
     *
     * @throws TemplateException
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
     *
     * @throws LogicException
     * @throws TemplateException
     */
    public function render(CompressResult $result, $resultFile, $headerFile = null)
    {
        $headerFile = $headerFile ?: \fopen('php://memory', 'rw');

        $this->language->begin($resultFile, $headerFile);

        $this->compress = $result;
        unset($result);

        $skipMode = false;
        $lineChanged = false;
        $tailCode = false;
        $reduceMode = [
            'enabled' => false,
            'm'       => -1,
            'n'       => 0,
            'mac'     => [],
        ];
        $tokenMode = [
            'enabled' => false,
            'mac'     => [],
        ];
        $buffer = '';

        foreach ($this->template as $line) {
            $line .= "\n";
            if ($tailCode) {
                $this->language->write($buffer.$line);
                continue;
            }

            if ($skipMode) {
                if ($this->metaMatch(\ltrim($line), 'endif')) {
                    $skipMode = false;
                }
                continue;
            }

            if ($reduceMode['enabled']) {
                if ($this->metaMatch(\trim($line), 'endreduce')) {
                    $reduceMode['enabled'] = false;
                    $this->lineNumber++;
                    if ($reduceMode['m'] < 0) {
                        $reduceMode['m'] = $reduceMode['n'];
                    }

                    foreach ($this->context->grams as $gram) {
                        if ($gram->action) {
                            for ($j = 0; $j < $reduceMode['m']; $j++) {
                                $this->expandMacro($reduceMode['mac'][$j], $gram->num, null);
                            }
                        } else {
                            for ($j = $reduceMode['m']; $j < $reduceMode['n']; $j++) {
                                $this->expandMacro($reduceMode['mac'][$j], $gram->num, null);
                            }
                        }
                    }
                    continue;
                } elseif ($this->metaMatch(\trim($line), 'noact')) {
                    $reduceMode['m'] = $reduceMode['n'];
                    continue;
                }
                $reduceMode['mac'][$reduceMode['n']++] = $line;
                continue;
            }

            if ($tokenMode['enabled']) {
                if ($this->metaMatch(\trim($line), 'endtokenval')) {
                    $tokenMode['enabled'] = false;
                    $this->lineNumber++;
                    for ($i = 1; $i < $this->context->countTerminals; $i++) {
                        $symbol = $this->context->symbol($i);
                        if ($symbol->name[0] != '\'') {
                            $str = $symbol->name;
                            if ($i === 1) {
                                $str = 'YYERRTOK';
                            }
                            foreach ($tokenMode['mac'] as $mac) {
                                $this->expandMacro($mac, $symbol->value, $str);
                            }
                        }
                    }
                } else {
                    $tokenMode['mac'][] = $line;
                }
                continue;
            }
            $p = $line;
            $buffer = '';
            for ($i = 0; $i < \mb_strlen($line); $i++) {
                $p = \mb_substr($line, $i);
                if ($p[0] !== $this->metaChar) {
                    $buffer .= $line[$i];
                } elseif ($i + 1 < \mb_strlen($line) && $p[1] === $this->metaChar) {
                    $i++;
                    $buffer .= $this->metaChar;
                } elseif ($this->metaMatch($p, '(')) {
                    $start = $i + 2;
                    $val = \mb_substr($p, 2);
                    while ($i < \mb_strlen($line) && $line[$i] !== ')') {
                        $i++;
                    }
                    if (!isset($line[$i])) {
                        throw new TemplateException('$(: missing ")"');
                    }
                    $length = $i - $start;

                    $buffer .= $this->genValueOf(\mb_substr($val, 0, $length));
                } elseif ($this->metaMatch($p, 'TYPEOF(')) {
                    throw new LogicException('TYPEOF is not implemented');
                } else {
                    break;
                }
            }
            if (isset($p[0]) && $p[0] === $this->metaChar) {
                if (\trim($buffer) !== '') {
                    throw new TemplateException('Non-blank character before $-keyword');
                }
                if ($this->metaMatch($p, 'header')) {
                    $this->copyHeader = true;
                } elseif ($this->metaMatch($p, 'endheader')) {
                    $this->copyHeader = false;
                } elseif ($this->metaMatch($p, 'tailcode')) {
                    $this->printLine();
                    $tailCode = true;
                    continue;
                } elseif ($this->metaMatch($p, 'verification-table')) {
                    throw new TemplateException('verification-table is not implemented');
                } elseif ($this->metaMatch($p, 'union')) {
                    throw new TemplateException('union is not implemented');
                } elseif ($this->metaMatch($p, 'tokenval')) {
                    $tokenMode = [
                        'enabled' => true,
                        'mac'     => [],
                    ];
                } elseif ($this->metaMatch($p, 'reduce')) {
                    $reduceMode = [
                        'enabled' => true,
                        'm'       => -1,
                        'n'       => 0,
                        'mac'     => [],
                    ];
                } elseif ($this->metaMatch($p, 'switch-for-token-name')) {
                    for ($i = 0; $i < $this->context->countTerminals; $i++) {
                        if ($this->context->cTermIndex[$i] >= 0) {
                            $symbol = $this->context->symbol($i);
                            $this->language->caseBlock($buffer, $symbol->value, $symbol->name);
                        }
                    }
                } elseif ($this->metaMatch($p, 'production-strings')) {
                    foreach ($this->context->grams as $gram) {
                        $info = \array_slice($gram->body, 0);

                        $this->language->write($buffer.'"');
                        $this->language->writeQuoted($info[0]->name);
                        $this->language->writeQuoted(' :');

                        if (\count($info) === 1) {
                            $this->language->writeQuoted(' /* empty */');
                        }

                        for ($i = 1, $l = \count($info); $i < $l; $i++) {
                            $this->language->writeQuoted(' '.$info[$i]->name);
                        }

                        if ($gram->num + 1 === $this->context->countGrams) {
                            $this->language->write("\"\n");
                        } else {
                            $this->language->write("\",\n");
                        }
                    }
                } elseif ($this->metaMatch($p, 'listvar')) {
                    $var = \trim(\mb_substr($p, 9));
                    $this->genListVar($buffer, $var);
                } elseif ($this->metaMatch($p, 'ifnot')) {
                    $skipMode = $skipMode || !$this->skipIf($p);
                } elseif ($this->metaMatch($p, 'if')) {
                    $skipMode = $skipMode || $this->skipIf($p);
                } elseif ($this->metaMatch($p, 'endif')) {
                    $skipMode = false;
                } else {
                    throw new TemplateException("Unknown \$: $line");
                }
                $lineChanged = true;
            } else {
                if ($lineChanged) {
                    $this->printLine();
                    $lineChanged = false;
                }
                $this->language->write($buffer, $this->copyHeader);
            }
        }

        $this->language->commit();
    }

    /**
     * @param $spec string
     *
     * @throws TemplateException
     *
     * @return bool
     */
    protected function skipIf(string $spec): bool
    {
        [ $dump, $test ] = \explode(' ', $spec, 2);

        $test = \trim($test);
        switch ($test) {
            case '-a':
                return $this->context->aflag;

            case '-t':
                return $this->context->tflag;

            case '-p':
                return (bool) $this->context->className;

            case '%union':
                return (bool) $this->context->unioned;

            case '%pure_parser':
                return $this->context->pureFlag;

            default:
                throw new TemplateException("$dump: unknown switch: $test");
        }
    }

    /**
     * @param string      $def
     * @param int         $value
     * @param string|null $str
     */
    protected function expandMacro(string $def, int $value, string $str = null)
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
                        $this->printLine($gram->position);
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

        $this->language->write($result, $this->copyHeader);
    }

    /**
     * @param string $indent
     * @param string $var
     *
     * @throws TemplateException
     */
    protected function genListVar(string $indent, string $var)
    {
        $size = -1;
        if (isset($this->compress->$var)) {
            $array = $this->compress->$var;
            if (isset($this->compress->{$var.'size'})) {
                $size = $this->compress->{$var.'size'};
            } elseif ($var === 'yydefault') {
                $size = $this->context->countNonLeafStates;
            } elseif (\in_array($var, ['yygbase', 'yygdefault'])) {
                $size = $this->context->countNonTerminals;
            } elseif (\in_array($var, ['yylhs', 'yylen'])) {
                $size = $this->context->countGrams;
            }
            $this->printArray($array, $size < 0 ? count($array) : $size, $indent);
        } elseif ($var === 'terminals') {
            $nl = 0;
            foreach ($this->context->terminals as $term) {
                if ($this->context->cTermIndex[$term->code] >= 0) {
                    $prefix = $nl++ ? ",\n" : '';
                    $this->language->write($prefix.$indent.'"');
                    $this->language->writeQuoted($term->name);
                    $this->language->write('"');
                }
            }
            $this->language->write("\n");
        } elseif ($var === 'nonterminals') {
            $nl = 0;
            foreach ($this->context->nonterminals as $nonterm) {
                $prefix = $nl++ ? ",\n" : '';
                $this->language->write($prefix.$indent.'"');
                $this->language->writeQuoted($nonterm->name);
                $this->language->write('"');
            }
            $this->language->write("\n");
        } else {
            throw new TemplateException("\$listvar: unknown variable $var");
        }
    }

    /**
     * @param array  $array
     * @param int    $limit
     * @param string $indent
     */
    protected function printArray(array $array, int $limit, string $indent)
    {
        $col = 0;
        for ($i = 0; $i < $limit; $i++) {
            if ($col === 0) {
                $this->language->write($indent);
            }
            $this->language->write(\sprintf($i + 1 === $limit ? '%5d' : '%5d,', $array[$i]));
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
     *
     * @throws TemplateException
     *
     * @return string
     */
    protected function genValueOf(string $var): string
    {
        switch ($var) {
            case 'YYSTATES':
                return \sprintf('%d', $this->context->countStates);
            case 'YYNLSTATES':
                return \sprintf('%d', $this->context->countNonLeafStates);
            case 'YYINTERRTOK':
                return \sprintf('%d', $this->compress->yytranslate[$this->context->errorToken->value]);
            case 'YYUNEXPECTED':
                return \sprintf('%d', Compress::UNEXPECTED);
            case 'YYDEFAULT':
                return \sprintf('%d', Compress::DEFAULT);
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
                return \sprintf('%d', $this->context->countNonTerminals);
            case 'YY2TBLSTATE':
                return \sprintf('%d', $this->compress->yybasesize - $this->context->countNonLeafStates);
            case 'CLASSNAME':
            case '-p':
                return $this->context->className ?: 'yy';
            default:
                throw new TemplateException("Unknown variable: \$($var)");
        }
    }

    /**
     * @param string $template
     *
     * @throws TemplateException
     */
    protected function parseTemplate(string $template)
    {
        $template = \preg_replace("(\r\n|\r)", "\n", $template);
        $lines = \explode("\n", $template);
        $this->lineNumber = 1;
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
            $this->lineNumber++;
            if ($this->metaMatch($p, 'include')) {
                $skip = true;
            } elseif ($this->metaMatch($p, 'meta')) {
                if (!isset($p[6]) || Utils::isWhite($p[6])) {
                    throw new TemplateException("\$meta: missing character in definition: $p");
                }
                $this->metaChar = $p[6];
            } elseif ($this->metaMatch($p, 'semval')) {
                $this->defSemvalMacro(\mb_substr($p, 7));
            } else {
                $this->template[] = $line;
            }
        }
    }

    /**
     * @param string $text
     * @param string $keyword
     *
     * @return bool
     */
    protected function metaMatch(string $text, string $keyword): bool
    {
        return isset($text[0]) && $text[0] === $this->metaChar && \mb_substr($text, 1, \mb_strlen($keyword)) === $keyword;
    }

    /**
     * @param string $macro
     *
     * @throws TemplateException
     */
    protected function defSemvalMacro(string $macro)
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
     * @param int         $line
     * @param string|null $filename
     */
    protected function printLine(int $line = -1, string $filename = null)
    {
        $line = $line === -1 ? $this->lineNumber : $line;
        $filename = $filename ?? $this->context->filename;

        $this->language->inlineComment(\sprintf('%s:%d', $filename, $line));
    }
}
