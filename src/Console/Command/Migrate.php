<?php

namespace Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TripleI\Libraries\MigrationRunner;

class Migrate extends Command
{
    protected function configure()
    {
        $this
            ->setName('package:migrate')
            ->setDescription(t('Run any outstanding database migrations for a package'))
            ->addOption('pkg-handle', 'pkg', InputOption::VALUE_REQUIRED, 'the handle of the package to migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Running Migrations');
        $options = $input->getOptions();
        $runner = new MigrationRunner();
        if (isset($options['pkg-handle'])) {
            $runner->setPackage(\Concrete\Core\Package\Package::getByHandle($options['pkg-handle']));
            $pending = $runner->getPendingMigrations();
            foreach ($pending as $migration) {
                $output->writeln("Running " . $migration->getFilename());
                $migration->run();
                $output->writeln("Complete");
            }
            $output->writeln("Migrations Complete");
        } else {
            $output->writeln(t('the pkg-handle option is required'));
            exit;
        }

    }
}
