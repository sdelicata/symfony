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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Simon Delicata <simon.delicata@free.fr>
 */
class MemoryLimitReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $memoryLimit;
    private $logger;
    private $memoryResolver;

    public function __construct(
        ReceiverInterface $decoratedReceiver,
        string $memoryLimit,
        LoggerInterface $logger = null,
        callable $memoryResolver = null
    ) {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->memoryLimit = $this->convertToOctets($memoryLimit);
        $this->logger = $logger;
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
                if ($this->logger) {
                    $this->logger->info('Receiver stopped due to memory limit exceeded.');
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }

    private function convertToOctets(string $size): int
    {
        if (!\preg_match('/^(\d+)([G|M|K]*)$/', $size, $matches)) {
            throw new \InvalidArgumentException('Invalid memory limit given.');
        } else {
            if ('G' == $matches[2]) {
                $size = $matches[1] * 1024 * 1024 * 1024;
            } elseif ('M' == $matches[2]) {
                $size = $matches[1] * 1024 * 1024;
            } elseif ('K' == $matches[2]) {
                $size = $matches[1] * 1024;
            }
        }

        return $size;
    }
}
