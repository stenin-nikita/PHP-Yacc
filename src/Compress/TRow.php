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
 * Class TRow.
 */
class TRow
{
    /**
     * @var int
     */
    public $index;

    /**
     * @var int
     */
    public $mini;

    /**
     * @var int
     */
    public $maxi;

    /**
     * @var int
     */
    public $nent;

    /**
     * TRow constructor.
     *
     * @param int $index
     */
    public function __construct(int $index)
    {
        $this->index = $index;
        $this->mini = -1;
        $this->maxi = 0;
        $this->nent = 0;
    }

    /**
     * @return int
     */
    public function span(): int
    {
        return $this->maxi - $this->mini;
    }

    /**
     * @return int
     */
    public function nhole(): int
    {
        return $this->span() - $this->nent;
    }

    /**
     * @param TRow $a
     * @param TRow $b
     *
     * @return int
     */
    public static function compare(self $a, self $b): int
    {
        if ($a->nent !== $b->nent) {
            return $b->nent - $a->nent;
        }
        if ($a->span() !== $b->span()) {
            return $b->span() - $a->span();
        }

        return $a->mini - $b->mini;
    }
}
