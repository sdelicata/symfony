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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Tests\Fixtures\CallbackReceiver;
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
        $callable = function ($handler) {
            $handler(new DummyMessage('API'));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
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

        $memoryLimitReceiver = new MemoryLimitReceiver($decoratedReceiver, $memoryLimit, null, $memoryResolver);
        $memoryLimitReceiver->receive(function () {});
    }

    public function memoryProvider()
    {
        yield array(2048, 1024, true);
        yield array(1024, 1024, true);
        yield array(1024, 2048, false);
        yield array(129 * 1024, '128K', true);
        yield array(128 * 1024, '128K', true);
        yield array(127 * 1024, '128K', false);
        yield array(65 * 1024 * 1024, '64M', true);
        yield array(64 * 1024 * 1024, '64M', true);
        yield array(63 * 1024 * 1024, '64M', false);
        yield array(2 * 1024 * 1024 * 1024, '1G', true);
        yield array(1 * 1024 * 1024 * 1024, '1G', true);
        yield array(10 * 1024 * 1024, '1G', false);
        yield array(1 * 1024 * 1024 * 1024, '1M', true);
        yield array(1 * 1024 * 1024 * 1024, '1K', true);
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
        yield array('without_digit'); // string without digit
        yield array('1024X'); // bad unit
        yield array('128m'); // lowercase unit
        yield array('128 M'); // string with space
    }

    public function testReceiverLogsMemoryExceededWhenLoggerIsGiven()
    {
        $callable = function ($handler) {
            $handler(new DummyMessage('API'));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $decoratedReceiver->expects($this->once())->method('receive');
        $decoratedReceiver->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with($this->equalTo('Receiver stopped due to memory limit exceeded.'));

        $memoryResolver = function () {
            return 70 * 1024 * 1024;
        };

        $memoryLimitReceiver = new MemoryLimitReceiver($decoratedReceiver, '64M', $logger, $memoryResolver);
        $memoryLimitReceiver->receive(function () {});
    }
}
