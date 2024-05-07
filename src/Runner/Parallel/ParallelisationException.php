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

namespace PhpCsFixer\Runner\Parallel;

/**
 * Common exception for all the errors related to parallelisation.
 *
 * @author Greg Korba <greg@codito.dev>
 *
 * @internal
 */
final class ParallelisationException extends \RuntimeException
{
    public static function forUnknownIdentifier(ProcessIdentifier $identifier): self
    {
        return new self('Unknown process identifier: '.(string) $identifier);
    }

    /**
     * @param array{message: string, code: int, file: string, line: int} $error
     */
    public static function forWorkerError(array $error): self
    {
        $exception = new self($error['message'], $error['code']);
        $exception->file = $error['file'];
        $exception->line = $error['line'];

        return $exception;
    }
}
