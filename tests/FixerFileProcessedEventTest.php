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

use PhpCsFixer\FixerFileProcessedEvent;

/**
 * @internal
 *
 * @covers \PhpCsFixer\FixerFileProcessedEvent
 */
final class FixerFileProcessedEventTest extends TestCase
{
    public function testFixerFileProcessedEvent(): void
    {
        $status = FixerFileProcessedEvent::STATUS_NO_CHANGES;
        $appliedFixers = ['x', 'y', 'z'];
        $event = new FixerFileProcessedEvent($status, 'foo', $appliedFixers);

        self::assertSame('foo', $event->getFileRelativePath());
        self::assertSame($status, $event->getStatus());
        self::assertSame($appliedFixers, $event->getAppliedFixers());
    }
}
