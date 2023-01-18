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

namespace Infection\Tests\Mutator\Boolean;

use Infection\Tests\Mutator\BaseMutatorTestCase;

final class LogicalOrTest extends BaseMutatorTestCase
{
    /**
     * @dataProvider mutationsProvider
     *
     * @param string|string[] $expected
     */
    public function test_it_can_mutate(string $input, $expected = []): void
    {
        $this->doTest($input, $expected);
    }

    public function mutationsProvider(): iterable
    {
        yield 'It mutates logical or' => [
            <<<'PHP'
<?php

true || false;
PHP
            ,
            <<<'PHP'
<?php

true && false;
PHP
            ,
        ];

        yield 'It mutates logical or if variables names are different' => [
            <<<'PHP'
<?php

$myVar === true || $myOtherVar === false;
PHP
            ,
            <<<'PHP'
<?php

$myVar === true && $myOtherVar === false;
PHP
            ,
        ];

        yield 'It does not mutate logical lower or' => [
            <<<'PHP'
<?php

true or false;
PHP
            ,
        ];

        yield 'It does not mutates logical or if same variable is tested against "Identical".' => [
            <<<'PHP'
<?php

$myVar === 'hello' || $myVar === 'world';
PHP
            ,
        ];

        yield 'It does not mutates logical or if same variable is tested against "Identical" (mirrored #1).' => [
            <<<'PHP'
<?php

$myVar === 'hello' || 'world' === $myVar;
PHP
            ,
        ];

        yield 'It does not mutates logical or if same variable is tested against "Identical" (mirrored #2).' => [
            <<<'PHP'
<?php

'world' === $myVar || $myVar === 'hello';
PHP
            ,
        ];

        yield 'It does not mutates logical or if same variable is tested against "Greater" and "Smaller" #1.' => [
            <<<'PHP'
<?php

$myVar < 5 || $myVar > 10;
PHP
            ,
        ];

        yield 'It does not mutates logical or if same variable is tested against "Greater" and "Smaller" #2.' => [
            <<<'PHP'
<?php

$myVar < 10 || $myVar > 5;
PHP
            ,
        ];
    }
}
