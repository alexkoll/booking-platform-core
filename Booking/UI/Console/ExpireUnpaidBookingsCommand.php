<?php

namespace App\Booking\UI\Console;

use App\Booking\Application\Command\ExpireUnpaidBookings\ExpireUnpaidBookingsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:bookings:expire-unpaid')]
final class ExpireUnpaidBookingsCommand extends Command
{
    public function __construct(private readonly ExpireUnpaidBookingsHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max bookings to process', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $limit = $limit > 0 ? $limit : 200;

        $count = ($this->handler)($limit);

        $output->writeln(sprintf('Expired %d bookings.', $count));

        return Command::SUCCESS;
    }
}
