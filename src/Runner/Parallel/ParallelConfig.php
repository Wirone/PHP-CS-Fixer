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

use Fidry\CpuCoreCounter\CpuCoreCounter;
use Fidry\CpuCoreCounter\Finder\DummyCpuCoreFinder;
use Fidry\CpuCoreCounter\Finder\FinderRegistry;

/**
 * @author Greg Korba <greg@codito.dev>
 */
final class ParallelConfig
{
    private const DEFAULT_FILES_PER_PROCESS = 10;
    private const DEFAULT_PROCESS_TIMEOUT = 120;

    private int $filesPerProcess;
    private int $maxProcesses;
    private int $processTimeout;

    public function __construct(
        int $maxProcesses = 2,
        int $filesPerProcess = self::DEFAULT_FILES_PER_PROCESS,
        int $processTimeout = self::DEFAULT_PROCESS_TIMEOUT
    ) {
        if ($maxProcesses <= 0 || $filesPerProcess <= 0 || $processTimeout <= 0) {
            throw new ParallelisationException('Invalid parallelisation configuration: only positive integers are allowed');
        }

        $this->maxProcesses = $maxProcesses;
        $this->filesPerProcess = $filesPerProcess;
        $this->processTimeout = $processTimeout;
    }

    public function getFilesPerProcess(): int
    {
        return $this->filesPerProcess;
    }

    public function getMaxProcesses(): int
    {
        return $this->maxProcesses;
    }

    public function getProcessTimeout(): int
    {
        return $this->processTimeout;
    }

    public static function sequential(): self
    {
        return new self(1);
    }

    public static function detect(
        int $filesPerProcess = self::DEFAULT_FILES_PER_PROCESS,
        int $processTimeout = self::DEFAULT_PROCESS_TIMEOUT
    ): self {
        $counter = new CpuCoreCounter([
            ...FinderRegistry::getDefaultLogicalFinders(),
            new DummyCpuCoreFinder(1),
        ]);

        return new self($counter->getCount(), $filesPerProcess, $processTimeout);
    }
}