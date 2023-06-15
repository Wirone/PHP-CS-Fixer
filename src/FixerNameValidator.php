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

namespace PhpCsFixer;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 */
final class FixerNameValidator
{
    public function isValid(string $name, bool $isCustom): bool
    {
        if (!$isCustom) {
            return Preg::match('/^[a-z][a-z0-9_]*$/', $name);
        }

        // See: https://regex101.com/r/UngJUn/4
        return Preg::match('/^[a-zA-Z][a-zA-Z0-9]*(?!$)((\/[a-z][a-z0-9_]*)?|((?i)(?<!PhpCsFixer)(?-i)\\\\[a-zA-Z][a-zA-Z0-9_]*)*){1}$/', $name);
    }
}
