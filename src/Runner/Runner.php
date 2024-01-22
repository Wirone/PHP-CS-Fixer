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

namespace PhpCsFixer\Runner;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Cache\CacheManagerInterface;
use PhpCsFixer\Cache\Directory;
use PhpCsFixer\Cache\DirectoryInterface;
use PhpCsFixer\Differ\DifferInterface;
use PhpCsFixer\Error\Error;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\FileReader;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerFileProcessedEvent;
use PhpCsFixer\Linter\LinterInterface;
use PhpCsFixer\Linter\LintingException;
use PhpCsFixer\Linter\LintingResultInterface;
use PhpCsFixer\Tokenizer\Tokens;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * @author Greg Korba <greg@codito.dev>
 *
 * @phpstan-type _RunResult array<string, array{appliedFixers: list<string>, diff: string}>
 */
final class Runner
{
    private RunnerConfig $runnerConfig;

    private DifferInterface $differ;

    private ?DirectoryInterface $directory;

    private ?EventDispatcherInterface $eventDispatcher;

    private ErrorsManager $errorsManager;

    private CacheManagerInterface $cacheManager;

    private LinterInterface $linter;

    /**
     * @var \Traversable<\SplFileInfo>
     */
    private $finder;

    /**
     * @var list<FixerInterface>
     */
    private array $fixers;

    /**
     * @param \Traversable<\SplFileInfo> $finder
     * @param list<FixerInterface>       $fixers
     */
    public function __construct(
        RunnerConfig $runnerConfig,
        \Traversable $finder,
        array $fixers,
        DifferInterface $differ,
        ?EventDispatcherInterface $eventDispatcher,
        ErrorsManager $errorsManager,
        LinterInterface $linter,
        CacheManagerInterface $cacheManager,
        ?DirectoryInterface $directory = null
    ) {
        $this->runnerConfig = $runnerConfig;
        $this->finder = $finder;
        $this->fixers = $fixers;
        $this->differ = $differ;
        $this->eventDispatcher = $eventDispatcher;
        $this->errorsManager = $errorsManager;
        $this->linter = $linter;
        $this->cacheManager = $cacheManager;
        $this->directory = $directory ?? new Directory('');
    }

    /**
     * @return _RunResult
     */
    public function fix(): array
    {
        return $this->runnerConfig->getParallelConfig()->getMaxProcesses() > 1
            ? $this->fixParallel()
            : $this->fixSequential();
    }

    /**
     * @return _RunResult
     */
    private function fixParallel(): array
    {
        throw new \RuntimeException('NOT IMPLEMENTED YET');
    }

    /**
     * @return _RunResult
     */
    private function fixSequential(): array
    {
        $changed = [];
        $collection = $this->getFileIterator();

        foreach ($collection as $file) {
            $fixInfo = $this->fixFile($file, $collection->currentLintingResult());

            // we do not need Tokens to still caching just fixed file - so clear the cache
            Tokens::clearCache();

            if (null !== $fixInfo) {
                $name = $this->directory->getRelativePathTo($file->__toString());
                $changed[$name] = $fixInfo;

                if ($this->runnerConfig->shouldStopOnViolation()) {
                    break;
                }
            }
        }

        return $changed;
    }

    /**
     * @return null|array{appliedFixers: list<string>, diff: string}
     */
    private function fixFile(\SplFileInfo $file, LintingResultInterface $lintingResult): ?array
    {
        $name = $file->getPathname();

        try {
            $lintingResult->check();
        } catch (LintingException $e) {
            $this->dispatchEvent(
                FixerFileProcessedEvent::NAME,
                new FixerFileProcessedEvent(FixerFileProcessedEvent::STATUS_INVALID)
            );

            $this->errorsManager->report(new Error(Error::TYPE_INVALID, $name, $e));

            return null;
        }

        $old = FileReader::createSingleton()->read($file->getRealPath());

        $tokens = Tokens::fromCode($old);
        $oldHash = $tokens->getCodeHash();

        $new = $old;
        $newHash = $oldHash;

        $appliedFixers = [];

        try {
            foreach ($this->fixers as $fixer) {
                // for custom fixers we don't know is it safe to run `->fix()` without checking `->supports()` and `->isCandidate()`,
                // thus we need to check it and conditionally skip fixing
                if (
                    !$fixer instanceof AbstractFixer
                    && (!$fixer->supports($file) || !$fixer->isCandidate($tokens))
                ) {
                    continue;
                }

                $fixer->fix($file, $tokens);

                if ($tokens->isChanged()) {
                    $tokens->clearEmptyTokens();
                    $tokens->clearChanged();
                    $appliedFixers[] = $fixer->getName();
                }
            }
        } catch (\ParseError $e) {
            $this->dispatchEvent(
                FixerFileProcessedEvent::NAME,
                new FixerFileProcessedEvent(FixerFileProcessedEvent::STATUS_LINT)
            );

            $this->errorsManager->report(new Error(Error::TYPE_LINT, $name, $e));

            return null;
        } catch (\Throwable $e) {
            $this->processException($name, $e);

            return null;
        }

        $fixInfo = null;

        if ([] !== $appliedFixers) {
            $new = $tokens->generateCode();
            $newHash = $tokens->getCodeHash();
        }

        // We need to check if content was changed and then applied changes.
        // But we can't simply check $appliedFixers, because one fixer may revert
        // work of other and both of them will mark collection as changed.
        // Therefore we need to check if code hashes changed.
        if ($oldHash !== $newHash) {
            $fixInfo = [
                'appliedFixers' => $appliedFixers,
                'diff' => $this->differ->diff($old, $new, $file),
            ];

            try {
                $this->linter->lintSource($new)->check();
            } catch (LintingException $e) {
                $this->dispatchEvent(
                    FixerFileProcessedEvent::NAME,
                    new FixerFileProcessedEvent(FixerFileProcessedEvent::STATUS_LINT)
                );

                $this->errorsManager->report(new Error(Error::TYPE_LINT, $name, $e, $fixInfo['appliedFixers'], $fixInfo['diff']));

                return null;
            }

            if (!$this->runnerConfig->isDryRun()) {
                $fileName = $file->getRealPath();

                if (!file_exists($fileName)) {
                    throw new IOException(
                        sprintf('Failed to write file "%s" (no longer) exists.', $file->getPathname()),
                        0,
                        null,
                        $file->getPathname()
                    );
                }

                if (is_dir($fileName)) {
                    throw new IOException(
                        sprintf('Cannot write file "%s" as the location exists as directory.', $fileName),
                        0,
                        null,
                        $fileName
                    );
                }

                if (!is_writable($fileName)) {
                    throw new IOException(
                        sprintf('Cannot write to file "%s" as it is not writable.', $fileName),
                        0,
                        null,
                        $fileName
                    );
                }

                if (false === @file_put_contents($fileName, $new)) {
                    $error = error_get_last();

                    throw new IOException(
                        sprintf('Failed to write file "%s", "%s".', $fileName, null !== $error ? $error['message'] : 'no reason available'),
                        0,
                        null,
                        $fileName
                    );
                }
            }
        }

        $this->cacheManager->setFileHash($name, $newHash);

        $this->dispatchEvent(
            FixerFileProcessedEvent::NAME,
            new FixerFileProcessedEvent(null !== $fixInfo ? FixerFileProcessedEvent::STATUS_FIXED : FixerFileProcessedEvent::STATUS_NO_CHANGES)
        );

        return $fixInfo;
    }

    /**
     * Process an exception that occurred.
     */
    private function processException(string $name, \Throwable $e): void
    {
        $this->dispatchEvent(
            FixerFileProcessedEvent::NAME,
            new FixerFileProcessedEvent(FixerFileProcessedEvent::STATUS_EXCEPTION)
        );

        $this->errorsManager->report(new Error(Error::TYPE_EXCEPTION, $name, $e));
    }

    private function dispatchEvent(string $name, Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event, $name);
    }

    private function getFileIterator(): LintingResultAwareFileIteratorInterface
    {
        $finder = $this->finder;
        $finderIterator = $finder instanceof \IteratorAggregate ? $finder->getIterator() : $finder;
        $fileFilteredFileIterator = new FileFilterIterator(
            $finderIterator,
            $this->eventDispatcher,
            $this->cacheManager
        );

        return $this->linter->isAsync()
            ? new FileCachingLintingFileIterator($fileFilteredFileIterator, $this->linter)
            : new LintingFileIterator($fileFilteredFileIterator, $this->linter);
    }
}
