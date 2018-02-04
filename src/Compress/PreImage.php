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
 * Class PreImage.
 */
class PreImage
{
    /**
     * @var int
     */
    public $index = 0;

    /**
     * @var array
     */
    public $classes = [];

    /**
     * @var int
     */
    public $length = 0;

    /**
     * PreImage constructor.
     *
     * @param int $index
     */
    public function __construct(int $index)
    {
        $this->index = $index;
    }

    /**
     * @param PreImage $x
     * @param PreImage $y
     *
     * @return int
     */
    public static function compare(self $x, self $y): int
    {
        if ($x->length !== $y->length) {
            return $x->length - $y->length;
        }

        foreach ($x->classes as $key => $value) {
            if ($value !== $y->classes[$key]) {
                return $value - $y->classes[$key];
            }
        }

        return 0;
    }
}
