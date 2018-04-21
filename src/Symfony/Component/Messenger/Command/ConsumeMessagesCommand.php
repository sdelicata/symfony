<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Command;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Enhancers\MaximumCountReceiver;
use Symfony\Component\Messenger\Transport\Enhancers\MemoryLimitReceiver;
use Symfony\Component\Messenger\Transport\Enhancers\TimeoutReceiver;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
class ConsumeMessagesCommand extends Command
{
    protected static $defaultName = 'messenger:consume-messages';

    private $bus;
    private $receiverLocator;
    private $logger;

    public function __construct(MessageBusInterface $bus, ContainerInterface $receiverLocator, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->bus = $bus;
        $this->receiverLocator = $receiverLocator;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('receiver', InputArgument::REQUIRED, 'Name of the receiver'),
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of received messages'),
                new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume'),
                new InputOption('timeout', 't', InputOption::VALUE_REQUIRED, 'The worker timeout'),
            ))
            ->setDescription('Consumes messages')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command consumes messages and dispatches them to the message bus.

    <info>php %command.full_name% <receiver-name></info>

Use the --limit option to limit the number of messages received:

    <info>php %command.full_name% <receiver-name> --limit=10</info>

Use the --memory-limit option to stop the worker if it exceeds a given memory usage limit. You can use shorthand byte values [K, M or G]:

    <info>php %command.full_name% <receiver-name> --memory-limit=128M</info>

Use the --timeout option to stop the worker if it waits more than a given time (PCNTL extension is required):

    <info>php %command.full_name% <receiver-name> --timeout=60</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->receiverLocator->has($receiverName = $input->getArgument('receiver'))) {
            throw new \RuntimeException(sprintf('Receiver "%s" does not exist.', $receiverName));
        }

        if (!($receiver = $this->receiverLocator->get($receiverName)) instanceof ReceiverInterface) {
            throw new \RuntimeException(sprintf('Receiver "%s" is not a valid message consumer. It must implement the "%s" interface.', $receiverName, ReceiverInterface::class));
        }

        if ($limit = $input->getOption('limit')) {
            $receiver = new MaximumCountReceiver($receiver, $limit, $this->logger);
        }

        if ($memoryLimit = $input->getOption('memory-limit')) {
            $receiver = new MemoryLimitReceiver($receiver, $memoryLimit, $this->logger);
        }

        if ($timeout = $input->getOption('timeout')) {
            $receiver = new TimeoutReceiver($receiver, $timeout, $this->logger);
        }

        $worker = new Worker($receiver, $this->bus);
        $worker->run();
    }
}
