<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Enhancers;

use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Simon Delicata <simon.delicata@free.fr>
 */
class MemoryLimitReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $memoryLimit;
    private $memoryResolver;

    public function __construct(ReceiverInterface $decoratedReceiver, string $memoryLimit, callable $memoryResolver = null)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->memoryLimit = $this->convertToOctets($memoryLimit);
        $this->memoryResolver = $memoryResolver ?: function () {
            return \memory_get_usage();
        };
    }

    public function receive(callable $handler): void
    {
        $this->decoratedReceiver->receive(function ($message) use ($handler) {
            $handler($message);

            $memoryResolver = $this->memoryResolver;
            if ($memoryResolver() >= $this->memoryLimit) {
                $this->stop();
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }

    private function convertToOctets(string $size): int
    {
        if (\preg_match('/^(\d+)([G|M|K]*)$/', $size, $matches)) {
            if ('G' == $matches[2]) {
                $size = $matches[1] * 1024 * 1024 * 1024;
            } elseif ('M' == $matches[2]) {
                $size = $matches[1] * 1024 * 1024;
            } elseif ('K' == $matches[2]) {
                $size = $matches[1] * 1024;
            }
        } else {
            throw new \InvalidArgumentException('Invalid memory limit given.');
        }

        return $size;
    }
}
