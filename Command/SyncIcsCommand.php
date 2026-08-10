<?php

namespace KimaiPlugin\HolidayBundle\Command;

use KimaiPlugin\HolidayBundle\Service\HolidayImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'kimai:bundle:holiday:sync-ics',
    description: 'Re-sync public holidays from stored ICS subscriptions (adds newly published future dates)',
)]
class SyncIcsCommand extends Command
{
    public function __construct(private readonly HolidayImporter $importer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->importer->syncAll();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Synced %d holiday group(s), added %d new holiday(s).',
            $result['groups'],
            $result['holidays']
        ));

        return Command::SUCCESS;
    }
}
