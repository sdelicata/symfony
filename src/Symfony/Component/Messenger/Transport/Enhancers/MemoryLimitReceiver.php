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

    public function __construct(ReceiverInterface $decoratedReceiver, string $memoryLimit)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->memoryLimit = $this->convertToOctets($memoryLimit);
    }

    public function receive(callable $handler): void
    {
        $this->decoratedReceiver->receive(function ($message) use ($handler) {
            $handler($message);

            if (\memory_get_usage() >= $this->memoryLimit) {
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
        if (\preg_match('/^(\d+)(.)$/', $size, $matches)) {
            if ($matches[2] == 'G') {
                $size = $matches[1] * 1024 * 1024 * 1024;
            } else if ($matches[2] == 'M') {
                $size = $matches[1] * 1024 * 1024;
            } else if ($matches[2] == 'K') {
                $size = $matches[1] * 1024;
            }
        }

        return (int) $size;
    }
}
