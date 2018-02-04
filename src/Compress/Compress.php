<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Compress;

use PhpYacc\Grammar\Context;
use PhpYacc\Grammar\Symbol;
use PhpYacc\Support\Utils;

/**
 * Class Compress
 */
class Compress
{
    const UNEXPECTED = 32767;
    const DEFAULT = -32766;
    const VACANT = -32768;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var CompressResult
     */
    private $result;

    /**
     * @param Context $context
     *
     * @return CompressResult
     */
    public function compress(Context $context)
    {
        $this->result = new CompressResult();
        $this->context = $context;

        $this->makeupTable2();

        return $this->result;
    }

    /**
     * @return void
     */
    private function computePreImages()
    {
        /** @var PreImage[] $preImages */
        $preImages = [];

        for ($i = 0; $i < $this->context->countStates; $i++) {
            $preImages[$i] = new PreImage($i);
        }

        for ($i = 0; $i < $this->context->countClasses; $i++) {
            for ($j = 0; $j < $this->context->countTerminals; $j++) {
                $s = $this->context->classAction[$i][$j];
                if ($s > 0) {
                    $preImages[$s]->classes[$preImages[$s]->length++] = $i;
                }
            }
        }

        Utils::stableSort($preImages, PreImage::class.'::compare');

        $this->context->primof = \array_fill(0, $this->context->countStates, 0);
        $this->context->prims = \array_fill(0, $this->context->countStates, 0);
        $this->context->countPrims = 0;
        for ($i = 0; $i < $this->context->countStates;) {
            $p = $preImages[$i];
            $this->context->prims[$this->context->countPrims] = $p;
            for (; $i < $this->context->countStates && PreImage::compare($p, $preImages[$i]) === 0; $i++) {
                $this->context->primof[$preImages[$i]->index] = $p;
            }
            $p->index = $this->context->countPrims++;
        }
    }

    /**
     * @param array $t
     * @param int   $count
     *
     * @return array
     */
    private function encodeShiftReduce(array $t, int $count): array
    {
        for ($i = 0; $i < $count; $i++) {
            if ($t[$i] >= $this->context->countNonLeafStates) {
                $t[$i] = $this->context->countNonLeafStates + $this->context->defaultAct[$t[$i]];
            }
        }

        return $t;
    }

    /**
     * @return void
     */
    private function makeupTable2()
    {
        $this->context->termAction = \array_fill(0, $this->context->countNonLeafStates, 0);
        $this->context->classAction = \array_fill(0, $this->context->countNonLeafStates * 2, 0);
        $this->context->nonTermGoto = \array_fill(0, $this->context->countNonLeafStates, 0);
        $this->context->defaultAct = \array_fill(0, $this->context->countStates, 0);
        $this->context->defaultGoto = \array_fill(0, $this->context->countNonTerminals, 0);

        $this->resetFrequency();
        $this->context->stateImageSorted = \array_fill(0, $this->context->countNonLeafStates, 0);
        $this->context->classOf = \array_fill(0, $this->context->countStates, 0);

        for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
            $this->context->termAction[$i] = \array_fill(0, $this->context->countTerminals, self::VACANT);
            $this->context->nonTermGoto[$i] = \array_fill(0, $this->context->countNonTerminals, self::VACANT);

            foreach ($this->context->states[$i]->shifts as $shift) {
                if ($shift->through->isTerminal) {
                    $this->context->termAction[$i][$shift->through->code] = $shift->number;
                } else {
                    $this->context->nonTermGoto[$i][$this->nb($shift->through)] = $shift->number;
                }
            }
            foreach ($this->context->states[$i]->reduce as $reduce) {
                if ($reduce->symbol->isNilSymbol()) {
                    break;
                }
                $this->context->termAction[$i][$reduce->symbol->code] = -$this->encodeRederr($reduce->number);
            }
            $this->context->stateImageSorted[$i] = $i;
        }

        foreach ($this->context->states as $key => $state) {
            $r = null;
            foreach ($state->reduce as $r) {
                if ($r->symbol->isNilSymbol()) {
                    break;
                }
            }
            $this->context->defaultAct[$key] = $this->encodeRederr($r->number);
        }

        for ($j = 0; $j < $this->context->countNonTerminals; $j++) {
            $max = 0;
            $maxst = self::VACANT;
            $this->resetFrequency();

            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                $st = $this->context->nonTermGoto[$i][$j];
                if ($st > 0) {
                    $this->context->frequency[$st]++;
                    if ($this->context->frequency[$st] > $max) {
                        $max = $this->context->frequency[$st];
                        $maxst = $st;
                    }
                }
            }
            $this->context->defaultGoto[$j] = $maxst;
        }
        // 847

        Utils::stableSort($this->context->stateImageSorted, [$this, 'cmpStates']);

        $j = 0;

        for ($i = 0; $i < $this->context->countNonLeafStates;) {
            $k = $this->context->stateImageSorted[$i];
            $this->context->classAction[$j] = $this->context->termAction[$k];
            for (; $i < $this->context->countNonLeafStates && $this->cmpStates($this->context->stateImageSorted[$i], $k) === 0; $i++) {
                $this->context->classOf[$this->context->stateImageSorted[$i]] = $j;
            }
            $j++;
        }
        $this->context->countClasses = $j;

        if ($this->context->debug) {
            $this->context->debug("State=>class:\n");
            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                if ($i % 10 === 0) {
                    $this->context->debug("\n");
                }
                $this->context->debug(\sprintf('%3d=>%-3d ', $i, $this->context->classOf[$i]));
            }
            $this->context->debug("\n");
        }

        $this->computePreImages();

        if ($this->context->debug) {
            $this->printTable();
        }

        $this->extractCommon();

        $this->authodoxTable();
    }

    /**
     * @return void
     */
    private function printTable()
    {
        $this->context->debug("\nTerminal action:\n");
        $this->context->debug(\sprintf('%8.8s', 'T\\S'));
        for ($i = 0; $i < $this->context->countClasses; $i++) {
            $this->context->debug(\sprintf('%4d', $i));
        }
        $this->context->debug("\n");
        for ($j = 0; $j < $this->context->countTerminals; $j++) {
            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                if (self::VACANT !== $this->context->termAction[$i][$j]) {
                    break;
                }
            }
            if ($i < $this->context->countNonLeafStates) {
                $this->context->debug(\sprintf('%8.8s', $this->context->symbol($j)->name));
                for ($i = 0; $i < $this->context->countClasses; $i++) {
                    $this->context->debug(Utils::printAction($this->context->classAction[$i][$j]));
                }
                $this->context->debug("\n");
            }
        }

        $this->context->debug("\nNonterminal GOTO table:\n");
        $this->context->debug(\sprintf('%8.8s', 'T\\S'));
        for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
            $this->context->debug(\sprintf('%4d', $i));
        }
        $this->context->debug("\n");
        foreach ($this->context->nonterminals as $symbol) {
            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                if ($this->context->nonTermGoto[$i][$this->nb($symbol)] > 0) {
                    break;
                }
            }
            if ($i < $this->context->countNonLeafStates) {
                $this->context->debug(\sprintf('%8.8s', $symbol->name));
                for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                    $this->context->debug(Utils::printAction($this->context->nonTermGoto[$i][$this->nb($symbol)]));
                }
                $this->context->debug("\n");
            }
        }

        $this->context->debug("\nNonterminal GOTO table:\n");
        $this->context->debug(\sprintf('%8.8s default', 'T\\S'));
        for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
            $this->context->debug(\sprintf('%4d', $i));
        }
        $this->context->debug("\n");
        foreach ($this->context->nonterminals as $symbol) {
            $nb = $this->nb($symbol);
            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                if ($this->context->nonTermGoto[$i][$nb] > 0) {
                    break;
                }
            }
            if ($i < $this->context->countNonLeafStates) {
                $this->context->debug(\sprintf('%8.8s', $symbol->name));
                $this->context->debug(\sprintf('%8d', $this->context->defaultGoto[$nb]));
                for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                    if ($this->context->nonTermGoto[$i][$nb] === $this->context->defaultGoto[$nb]) {
                        $this->context->debug('  = ');
                    } else {
                        $this->context->debug(Utils::printAction($this->context->nonTermGoto[$i][$nb]));
                    }
                }
                $this->context->debug("\n");
            }
        }
    }

    /**
     * @return void
     */
    private function extractCommon()
    {
        $this->context->class2nd = \array_fill(0, $this->context->countClasses, -1);

        $auxList = null;
        $n = 0;

        for ($i = 0; $i < $this->context->countPrims; $i++) {
            $preImage = $this->context->prims[$i];
            if ($preImage->length < 2) {
                continue;
            }
            $p = new Auxiliary();
            $this->bestCovering($p, $preImage);
            if ($p->gain < 1) {
                continue;
            }
            $p->preImage = $preImage;
            $p->next = $auxList;
            $auxList = $p;
            $n++;
        }

        if ($this->context->debug) {
            $this->context->debug("\nNumber of prims: {$this->context->countPrims}\n");
            $this->context->debug("\nCandidates of aux table:\n");
            for ($p = $auxList; $p !== null; $p = $p->next) {
                $this->context->debug(\sprintf('Aux = (%d) ', $p->gain));
                $f = 0;
                for ($j = 0; $j < $this->context->countTerminals; $j++) {
                    if (self::VACANT !== $p->table[$j]) {
                        $this->context->debug(\sprintf($f++ ? ',%d' : '%d', $p->table[$j]));
                    }
                }
                $this->context->debug(' * ');
                for ($j = 0; $j < $p->preImage->length; $j++) {
                    $this->context->debug(\sprintf($j ? ',%d' : '%d', $p->preImage->classes[$j]));
                }
                $this->context->debug("\n");
            }
            $this->context->debug("Used aux table:\n");
        }
        $this->context->countAux = $this->context->countClasses;
        for (;;) {
            $maxGain = 0;
            $maxAux = null;
            $pre = null;
            $maxPreImage = null;
            for ($p = $auxList; $p != null; $p = $p->next) {
                if ($p->gain > $maxGain) {
                    $maxGain = $p->gain;
                    $maxAux = $p;
                    $maxPreImage = $pre;
                }
                /** @var Auxiliary $pre */
                $pre = $p;
            }

            if ($maxAux === null) {
                break;
            }

            if ($maxPreImage) {
                $maxPreImage->next = $maxAux->next;
            } else {
                $auxList = $maxAux->next;
            }

            $maxAux->index = $this->context->countAux;

            for ($j = 0; $j < $maxAux->preImage->length; $j++) {
                $cl = $maxAux->preImage->classes[$j];
                if (Utils::eqRow($this->context->classAction[$cl], $maxAux->table, $this->context->countTerminals)) {
                    $maxAux->index = $cl;
                }
            }

            if ($maxAux->index >= $this->context->countAux) {
                $this->context->classAction[$this->context->countAux++] = $maxAux->table;
            }

            for ($j = 0; $j < $maxAux->preImage->length; $j++) {
                $cl = $maxAux->preImage->classes[$j];
                if ($this->context->class2nd[$cl] < 0) {
                    $this->context->class2nd[$cl] = $maxAux->index;
                }
            }

            if ($this->context->debug) {
                $this->context->debug(\sprintf('Selected aux[%d]: (%d) ', $maxAux->index, $maxAux->gain));
                $f = 0;
                for ($j = 0; $j < $this->context->countTerminals; $j++) {
                    if (self::VACANT !== $maxAux->table[$j]) {
                        $this->context->debug(\sprintf($f++ ? ',%d' : '%d', $maxAux->table[$j]));
                    }
                }
                $this->context->debug(' * ');
                $f = 0;
                for ($j = 0; $j < $maxAux->preImage->length; $j++) {
                    $cl = $maxAux->preImage->classes[$j];
                    if ($this->context->class2nd[$cl] === $maxAux->index) {
                        $this->context->debug(\sprintf($f++ ? ',%d' : '%d', $cl));
                    }
                }
                $this->context->debug("\n");
            }

            for ($p = $auxList; $p != null; $p = $p->next) {
                $this->bestCovering($p, $p->preImage);
            }
        }

        if ($this->context->debug) {
            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                if ($this->context->class2nd[$this->context->classOf[$i]] >= 0 && $this->context->class2nd[$this->context->classOf[$i]] !== $this->context->classOf[$i]) {
                    $this->context->debug(\sprintf("state %d (class %d): aux[%d]\n", $i, $this->context->classOf[$i], $this->context->class2nd[$this->context->classOf[$i]]));
                } else {
                    $this->context->debug(\sprintf("state %d (class %d)\n", $i, $this->context->classOf[$i]));
                }
            }
        }
    }

    /**
     * @param Auxiliary $aux
     * @param PreImage  $prim
     */
    private function bestCovering(Auxiliary $aux, PreImage $prim)
    {
        $this->resetFrequency();
        $gain = 0;
        for ($i = 0; $i < $this->context->countTerminals; $i++) {
            $max = 0;
            $maxAction = -1;
            $countVacant = 0;

            for ($j = 0; $j < $prim->length; $j++) {
                if ($this->context->class2nd[$prim->classes[$j]] < 0) {
                    $c = $this->context->classAction[$prim->classes[$j]][$i];
                    if ($c > 0 && ++$this->context->frequency[$c] > $max) {
                        $maxAction = $c;
                        $max = $this->context->frequency[$c];
                    } elseif (self::VACANT === $c) {
                        $countVacant++;
                    }
                }
            }

            $n = $max - 1 - $countVacant;

            if ($n > 0) {
                $aux->table[$i] = $maxAction;
                $gain += $n;
            } else {
                $aux->table[$i] = self::VACANT;
            }
        }
        $aux->gain = $gain;
    }

    private function authodoxTable()
    {
        // TODO
        $this->context->cTermIndex = \array_fill(0, $this->context->countTerminals, -1);
        $this->context->oTermIndex = \array_fill(0, $this->context->countTerminals, 0);

        $countCTerms = 0;
        for ($j = 0; $j < $this->context->countTerminals; $j++) {
            if ($j === $this->context->errorToken->code) {
                $this->context->cTermIndex[$j] = $countCTerms;
                $this->context->oTermIndex[$countCTerms++] = $j;
                continue;
            }
            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                if ($this->context->termAction[$i][$j] !== self::VACANT) {
                    $this->context->cTermIndex[$j] = $countCTerms;
                    $this->context->oTermIndex[$countCTerms++] = $j;
                    break;
                }
            }
        }

        $cTermAction = \array_fill(
            0, $this->context->countAux, \array_fill(0, $countCTerms, 0)
        );

        for ($i = 0; $i < $this->context->countClasses; $i++) {
            for ($j = 0; $j < $countCTerms; $j++) {
                $cTermAction[$i][$j] = $this->context->classAction[$i][$this->context->oTermIndex[$j]];
            }
        }

        //582

        for ($i = 0; $i < $this->context->countClasses; $i++) {
            if ($this->context->class2nd[$i] >= 0 && $this->context->class2nd[$i] != $i) {
                $table = $this->context->classAction[$this->context->class2nd[$i]];
                for ($j = 0; $j < $countCTerms; $j++) {
                    if (self::VACANT !== $table[$this->context->oTermIndex[$j]]) {
                        if ($cTermAction[$i][$j] === $table[$this->context->oTermIndex[$j]]) {
                            $cTermAction[$i][$j] = self::VACANT;
                        } elseif ($cTermAction[$i][$j] === self::VACANT) {
                            $cTermAction[$i][$j] = self::DEFAULT;
                        }
                    }
                }
            }
        }

        for ($i = $this->context->countClasses; $i < $this->context->countAux; $i++) {
            for ($j = 0; $j < $countCTerms; $j++) {
                $cTermAction[$i][$j] = $this->context->classAction[$i][$this->context->oTermIndex[$j]];
            }
        }
        $base = [];
        $this->packTable(
            $cTermAction, $this->context->countAux, $countCTerms, false, false,
            $this->result->yyaction, $this->result->yycheck, $base
        );
        $this->result->yydefault = $this->context->defaultAct;

        $this->result->yybase = \array_fill(0, $this->context->countNonLeafStates * 2, 0);
        $this->result->yybasesize = $this->context->countNonLeafStates;
        for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
            $cl = $this->context->classOf[$i];
            $this->result->yybase[$i] = $base[$cl];
            if ($this->context->class2nd[$cl] >= 0 && $this->context->class2nd[$cl] != $cl) {
                $this->result->yybase[$i + $this->context->countNonLeafStates] = $base[$this->context->class2nd[$cl]];
                if ($i + $this->context->countNonLeafStates + 1 > $this->result->yybasesize) {
                    $this->result->yybasesize = $i + $this->context->countNonLeafStates + 1;
                }
            }
        }

        $this->result->yybase = \array_slice($this->result->yybase, 0, $this->result->yybasesize);

        //642
        $nonTermTransposed = \array_fill(0, $this->context->countNonTerminals, array_fill(0, $this->context->countNonLeafStates, 0));
        foreach ($nonTermTransposed as $j => $_dump) {
            for ($i = 0; $i < $this->context->countNonLeafStates; $i++) {
                $nonTermTransposed[$j][$i] = $this->context->nonTermGoto[$i][$j];
                if ($this->context->nonTermGoto[$i][$j] === $this->context->defaultGoto[$j]) {
                    $nonTermTransposed[$j][$i] = self::VACANT;
                }
            }
        }

        $this->packTable(
            $nonTermTransposed, $this->context->countNonTerminals, $this->context->countNonLeafStates,
            false, true, $this->result->yygoto, $this->result->yygcheck, $this->result->yygbase
        );

        $this->result->yygdefault = $this->context->defaultGoto;

        $this->result->yylhs = [];
        $this->result->yylen = [];
        foreach ($this->context->grams as $gram) {
            $this->result->yylhs[] = $this->nb($gram->body[0]);
            $this->result->yylen[] = \count($gram->body) - 1;
        }

        $this->result->yytranslatesize = 0;

        foreach ($this->context->terminals as $term) {
            $value = $term->value;
            if ($value + 1 > $this->result->yytranslatesize) {
                $this->result->yytranslatesize = $value + 1;
            }
        }

        $this->result->yytranslate = \array_fill(0, $this->result->yytranslatesize, $countCTerms);
        $this->result->yyncterms = $countCTerms;

        for ($i = 0; $i < $this->context->countTerminals; $i++) {
            if ($this->context->cTermIndex[$i] >= 0) {
                $symbol = $this->context->symbol($i);
                $this->result->yytranslate[$symbol->value] = $this->context->cTermIndex[$i];
            }
        }

        $this->result->yyaction = $this->encodeShiftReduce($this->result->yyaction, \count($this->result->yyaction));
        $this->result->yygoto = $this->encodeShiftReduce($this->result->yygoto, \count($this->result->yygoto));
        $this->result->yygdefault = $this->encodeShiftReduce($this->result->yygdefault, $this->context->countNonTerminals);
    }

    /**
     * @param array $transit
     * @param int   $nrows
     * @param int   $ncols
     * @param bool  $dontcare
     * @param bool  $checkrow
     * @param array $outtable
     * @param array $outcheck
     * @param array $outbase
     */
    private function packTable(array $transit, int $nrows, int $ncols, bool $dontcare, bool $checkrow, array &$outtable, array &$outcheck, array &$outbase)
    {
        $trow = [];
        for ($i = 0; $i < $nrows; $i++) {
            $trow[] = $p = new TRow($i);
            for ($j = 0; $j < $ncols; $j++) {
                if (self::VACANT !== $transit[$i][$j]) {
                    if ($p->mini < 0) {
                        $p->mini = $j;
                    }
                    $p->maxi = $j + 1;
                    $p->nent++;
                }
            }
            if ($p->mini < 0) {
                $p->mini = 0;
            }
        }

        Utils::stableSort($trow, [TRow::class, 'compare']);

        if ($this->context->debug) {
            $this->context->debug("Order:\n");
            for ($i = 0; $i < $nrows; $i++) {
                $this->context->debug(sprintf('%d,', $trow[$i]->index));
            }
            $this->context->debug("\n");
        }

        $poolsize = $nrows * $ncols;
        $actpool = \array_fill(0, $poolsize, 0);
        $check = \array_fill(0, $poolsize, -1);
        $base = \array_fill(0, $nrows, 0);
        $handledBases = [];
        $actpoolmax = 0;

        for ($ii = 0; $ii < $nrows; $ii++) {
            $i = $trow[$ii]->index;
            if (Utils::vacantRow($transit[$i], $ncols)) {
                $base[$i] = 0;
                goto ok;
            }
            for ($h = 0; $h < $ii; $h++) {
                if (Utils::eqRow($transit[$trow[$h]->index], $transit[$i], $ncols)) {
                    $base[$i] = $base[$trow[$h]->index];
                    goto ok;
                }
            }
            for ($j = 0; $j < $poolsize; $j++) {
                $jj = $j;
                $base[$i] = $j - $trow[$ii]->mini;
                if (!$dontcare) {
                    if ($base[$i] === 0) {
                        continue;
                    }
                    if (isset($handledBases[$base[$i]])) {
                        continue;
                    }
                }

                for ($k = $trow[$ii]->mini; $k < $trow[$ii]->maxi; $k++) {
                    if (self::VACANT !== $transit[$i][$k]) {
                        if ($jj >= $poolsize) {
                            die("Can't happen");
                        }
                        if ($check[$jj] >= 0 && !($dontcare && $actpool[$jj] === $transit[$i][$k])) {
                            goto next;
                        }
                    }
                    $jj++;
                }
                break;
                next:;
            }

            $handledBases[$base[$i]] = true;
            $jj = $j;
            for ($k = $trow[$ii]->mini; $k < $trow[$ii]->maxi; $k++) {
                if (self::VACANT !== $transit[$i][$k]) {
                    $actpool[$jj] = $transit[$i][$k];
                    $check[$jj] = $checkrow ? $i : $k;
                }
                $jj++;
            }
            if ($jj >= $actpoolmax) {
                $actpoolmax = $jj;
            }
            ok:;
        }

        $outtable = \array_slice($actpool, 0, $actpoolmax);
        $outcheck = \array_slice($check, 0, $actpoolmax);
        $outbase = $base;
    }

    /**
     * @param int $code
     *
     * @return int
     */
    public function encodeRederr(int $code): int
    {
        return $code < 0 ? self::UNEXPECTED : $code;
    }

    /**
     * @return void
     */
    public function resetFrequency()
    {
        $this->context->frequency = \array_fill(0, $this->context->countStates, 0);
    }

    /**
     * @param int $x
     * @param int $y
     *
     * @return int
     */
    public function cmpStates(int $x, int $y): int
    {
        for ($i = 0; $i < $this->context->countTerminals; $i++) {
            if ($this->context->termAction[$x][$i] != $this->context->termAction[$y][$i]) {
                return $this->context->termAction[$x][$i] - $this->context->termAction[$y][$i];
            }
        }

        return 0;
    }

    /**
     * @param Symbol $symbol
     *
     * @return int
     */
    private function nb(Symbol $symbol)
    {
        if ($symbol->isTerminal) {
            return $symbol->code;
        } else {
            return $symbol->code - $this->context->countTerminals;
        }
    }
}
