<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Enhancers;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Enhancers\MemoryLimitReceiver;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

class MemoryLimitReceiverTest extends TestCase
{
    /**
     * @dataProvider memoryProvider
     */
    public function testReceiverStopsWhenMemoryLimitExceeded($memoryUsage, $memoryLimit, $shouldStop)
    {
        $decoratedReceiver = $this->getMockBuilder(ReceiverToDecorate::class)
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $decoratedReceiver->expects($this->once())->method('receive');
        if (true === $shouldStop) {
            $decoratedReceiver->expects($this->once())->method('stop');
        } else {
            $decoratedReceiver->expects($this->never())->method('stop');
        }

        $memoryResolver = function () use ($memoryUsage) {
            return $memoryUsage;
        };

        $memoryLimitReceiver = new MemoryLimitReceiver($decoratedReceiver, $memoryLimit, $memoryResolver);
        $memoryLimitReceiver->receive(function () {});
    }

    public function memoryProvider()
    {
        return array(
            array(2048, 1024, true),
            array(1024, 1024, true),
            array(1024, 2048, false),
            array(129 * 1024, '128K', true),
            array(128 * 1024, '128K', true),
            array(127 * 1024, '128K', false),
            array(65 * 1024 * 1024, '64M', true),
            array(64 * 1024 * 1024, '64M', true),
            array(63 * 1024 * 1024, '64M', false),
            array(2 * 1024 * 1024 * 1024, '1G', true),
            array(1 * 1024 * 1024 * 1024, '1G', true),
            array(10 * 1024 * 1024, '1G', false),
            array(1 * 1024 * 1024 * 1024, '1M', true),
            array(1 * 1024 * 1024 * 1024, '1K', true),
        );
    }

    /**
     * @dataProvider invalidMemoryLimitProvider
     * @expectedException \InvalidArgumentException
     */
    public function testReceiverThrowsExceptionWithInvalidMemoryLimit($memoryLimit)
    {
        $decoratedReceiver = $this->createMock(ReceiverInterface::class);
        $memoryLimitReceiver = new MemoryLimitReceiver($decoratedReceiver, $memoryLimit);
    }

    public function invalidMemoryLimitProvider()
    {
        return array(
            array('without_digit'), // string without digit
            array('1024X'), // bad unit
            array('128m'), // lowercase unit
            array('128 M'), // string with space
        );
    }
}

class ReceiverToDecorate implements ReceiverInterface
{
    public function receive(callable $handler): void
    {
        $handler(new DummyMessage('API'));
    }

    public function stop(): void
    {
    }
}
