<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests;

use PhpCsFixer\FixerNameValidator;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\FixerNameValidator
 */
final class FixerNameValidatorTest extends TestCase
{
    /**
     * @dataProvider provideIsValidCases
     */
    public function testIsValid(string $name, bool $isCustom, bool $isValid): void
    {
        $validator = new FixerNameValidator();

        self::assertSame($isValid, $validator->isValid($name, $isCustom));
    }

    public static function provideIsValidCases(): iterable
    {
        yield ['', true, false];

        yield ['', false, false];

        yield ['foo', true, false];

        yield ['foo', false, true];

        yield ['foo_bar', false, true];

        yield ['foo_bar_4', false, true];

        yield ['Foo', false, false];

        yield ['fooBar', false, false];

        yield ['4foo', false, false];

        yield ['_foo', false, false];

        yield ['4_foo', false, false];

        yield ['vendor/foo', false, false];

        yield ['vendor/foo', true, true];

        yield ['Vendor/foo', true, true];

        yield ['Vendor4/foo', true, true];

        yield ['4vendor/foo', true, false];

        yield ['Vendor/foo', true, true];

        yield ['FooBar/foo', true, true];

        yield ['foo\\bar', true, true];

        yield ['Foo\\Bar', true, true];

        yield ['Foo\\Bar\\Baz', true, true];

        yield ['FooBar\\B4z\\MyFixer123', true, true];

        yield ['Foo-Bar/foo', true, false];

        yield ['Foo_Bar/foo', true, false];

        yield ['Foo/foo/bar', true, false];

        yield ['OneFoo\\2Bar', true, false];

        yield ['_Foo\\bar', true, false];

        yield ['Foo\\Bar-Bara', true, false];

        yield ['/foo', true, false];

        yield ['/foo', false, false];

        yield ['/foo/bar', true, false];
    }
}
