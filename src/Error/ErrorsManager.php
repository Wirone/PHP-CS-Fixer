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

namespace PhpCsFixer\Error;

/**
 * Manager of errors that occur during fixing.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 */
final class ErrorsManager
{
    /**
     * @var list<Error>
     */
    private array $errors = [];

    /**
     * @var list<WorkerError>
     */
    private array $workerErrors = [];

    /**
     * Returns worker errors reported during processing files in parallel.
     *
     * @return list<WorkerError>
     */
    public function getWorkerErrors(): array
    {
        return $this->workerErrors;
    }

    /**
     * Returns errors reported during linting before fixing.
     *
     * @return list<Error>
     */
    public function getInvalidErrors(): array
    {
        return array_filter($this->errors, static fn (Error $error): bool => Error::TYPE_INVALID === $error->getType());
    }

    /**
     * Returns errors reported during fixing.
     *
     * @return list<Error>
     */
    public function getExceptionErrors(): array
    {
        return array_filter($this->errors, static fn (Error $error): bool => Error::TYPE_EXCEPTION === $error->getType());
    }

    /**
     * Returns errors reported during linting after fixing.
     *
     * @return list<Error>
     */
    public function getLintErrors(): array
    {
        return array_filter($this->errors, static fn (Error $error): bool => Error::TYPE_LINT === $error->getType());
    }

    /**
     * @return list<Error>
     */
    public function popAllErrors(): array
    {
        $errors = $this->errors;
        $this->errors = [];

        return $errors;
    }

    /**
     * Returns true if no errors were reported.
     */
    public function isEmpty(): bool
    {
        return [] === $this->errors && [] === $this->workerErrors;
    }

    /**
     * @param Error|WorkerError $error
     */
    public function report($error): void
    {
        if ($error instanceof WorkerError) {
            $this->workerErrors[] = $error;

            return;
        }

        $this->errors[] = $error;
    }
}
