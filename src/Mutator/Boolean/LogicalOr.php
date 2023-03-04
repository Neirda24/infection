<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\Mutator\Boolean;

use function get_class;
use function in_array;
use Infection\Mutator\Definition;
use Infection\Mutator\GetMutatorName;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use PhpParser\Node;
use function array_intersect;
use function property_exists;

/**
 * @internal
 *
 * @implements Mutator<Node\Expr\BinaryOp\BooleanOr>
 */
final class LogicalOr implements Mutator
{
    use GetMutatorName;

    public static function getDefinition(): ?Definition
    {
        return new Definition(
            'Replaces an OR operator (`||`) with an AND operator (`&&`).',
            MutatorCategory::ORTHOGONAL_REPLACEMENT,
            null,
            <<<'DIFF'
- $a = $b || $c;
+ $a = $b && $c;
DIFF
        );
    }

    /**
     * @psalm-mutation-free
     *
     * Replaces "||" with "&&"
     *
     * @return iterable<Node\Expr\BinaryOp\BooleanAnd>
     */
    public function mutate(Node $node): iterable
    {
        yield new Node\Expr\BinaryOp\BooleanAnd($node->left, $node->right, $node->getAttributes());
    }

    public function canMutate(Node $node): bool
    {
        if (!$node instanceof Node\Expr\BinaryOp\BooleanOr) {
            return false;
        }

        $nodeLeft  = $node->left;
        $nodeRight = $node->right;

        if (
            !property_exists($nodeLeft, 'left')
            || !property_exists($nodeLeft, 'right')
            || !property_exists($nodeRight, 'left')
            || !property_exists($nodeRight, 'right')
        ) {
            return true;
        }

        $nodeLeftLeft  = $nodeLeft->left;
        $nodeLeftRight = $nodeLeft->right;

        $nodeRightLeft  = $nodeRight->left;
        $nodeRightRight = $nodeRight->right;

        $classNodeLeft  = get_class($nodeLeft);
        $classNodeRight = get_class($nodeRight);

        if (
            Node\Expr\BinaryOp\Identical::class === $classNodeLeft
            && Node\Expr\BinaryOp\Identical::class === $classNodeRight
        ) {
            $varNameLeft = [];

            if ($nodeLeftLeft instanceof Node\Expr\Variable) {
                $varNameLeft[] = $nodeLeftLeft->name;
            }

            if ($nodeLeftRight instanceof Node\Expr\Variable) {
                $varNameLeft[] = $nodeLeftRight->name;
            }

            $varNameRight = [];

            if ($nodeRightLeft instanceof Node\Expr\Variable) {
                $varNameRight[] = $nodeRightLeft->name;
            }

            if ($nodeRightRight instanceof Node\Expr\Variable) {
                $varNameRight[] = $nodeRightRight->name;
            }

            return array_intersect($varNameLeft, $varNameRight) === [];
        }

        $greaterOp = [
            Node\Expr\BinaryOp\Greater::class,
            Node\Expr\BinaryOp\GreaterOrEqual::class,
        ];

        $smallerOp = [
            Node\Expr\BinaryOp\Smaller::class,
            Node\Expr\BinaryOp\SmallerOrEqual::class,
        ];

        if (
            (
                in_array($classNodeLeft, $greaterOp, true)
                && in_array($classNodeRight, $smallerOp, true)
            ) || (
                in_array($classNodeLeft, $smallerOp, true)
                && in_array($classNodeRight, $greaterOp, true)
            )
        ) {
            $varNameLeft = null;
            $valueLeft   = null;

            $numberScalar = [
                Node\Scalar\LNumber::class,
                Node\Scalar\DNumber::class,
            ];

            if ($nodeLeftLeft instanceof Node\Expr\Variable) {
                $varNameLeft = $nodeLeftLeft->name;
            } elseif (in_array(get_class($nodeLeftLeft), $numberScalar, true)) {
                $valueLeft = $nodeLeftLeft->value;
            } else {
                return true;
            }

            if ($nodeLeftRight instanceof Node\Expr\Variable && null === $varNameLeft) {
                $varNameLeft = $nodeLeftRight->name;
            } elseif (in_array(get_class($nodeLeftRight), $numberScalar, true) && null === $valueLeft) {
                $valueLeft = $nodeLeftRight->value;
            } else {
                return true;
            }

            $varNameRight = null;
            $valueRight   = null;

            if ($nodeRightLeft instanceof Node\Expr\Variable) {
                $varNameRight = $nodeRightLeft->name;
            } elseif (in_array(get_class($nodeRightLeft), $numberScalar, true)) {
                $valueRight = $nodeRightLeft->value;
            } else {
                return true;
            }

            if ($nodeRightRight instanceof Node\Expr\Variable && null === $varNameRight) {
                $varNameRight = $nodeRightRight->name;
            } elseif (in_array(get_class($nodeRightRight), $numberScalar, true) && null === $valueRight) {
                $valueRight = $nodeRightRight->value;
            } else {
                return true;
            }

            if ($varNameLeft !== $varNameRight) {
                return true;
            }

            return (match ("{$nodeLeft->getOperatorSigil()}::{$nodeRight->getOperatorSigil()}") {
                '<::>'   => static fn() => $valueRight < $valueLeft, // a<5 && a>7; 7<a<5; 7<5;
                '<::>='  => static fn() => $valueRight < $valueLeft, // a<5 && a>=7; 7<=a<5; 7<5;
                '<=::>=' => static fn() => $valueRight <= $valueLeft, // a<=5 && a>=7; 7<=a<=5; 7<=5;
                '<=::>'  => static fn() => $valueRight < $valueLeft, // a<=5 && a>7; 7<a<=5; 7<5;
                '>::<'   => static fn() => $valueRight > $valueLeft, // a>5 && a<7; 7>a>5; 7>5;
                '>::<='  => static fn() => $valueRight > $valueLeft, // a>5 && a<=7; 7>=a>5; 7>5;
                '>=::<=' => static fn() => $valueRight >= $valueLeft, // a>=5 && a<=7; 7>=a>=5; 7>=5;
                '>=::<'  => static fn() => $valueRight > $valueLeft, // a>=5 && a<7; 7>a>=5; 7>5;
            })();
        }

        return true;
    }
}
