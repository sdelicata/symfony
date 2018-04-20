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
class TimeoutReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $timeout;
    private $logger;

    public function __construct(ReceiverInterface $decoratedReceiver, int $timeout, LoggerInterface $logger = null)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->timeout = $timeout;
        $this->logger = $logger;
    }

    public function receive(callable $handler): void
    {
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGALRM, function () {
                if (null !== $this->logger) {
                    $this->logger->info('Receiver killed due to timeout of {timeout} expired', array('timeout' => $this->timeout));
                }
                $this->kill();
            });
        }

        $this->decoratedReceiver->receive(function ($message) use ($handler) {
            if (\function_exists('pcntl_alarm')) {
                pcntl_alarm($this->timeout);
            }

            $handler($message);
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }

    public function kill()
    {
        if (\function_exists('posix_kill')) {
            posix_kill(getmypid(), SIGKILL);
        }
        exit(1);
    }
}
