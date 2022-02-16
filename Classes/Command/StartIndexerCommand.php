<?php

namespace Tpwd\KeSearch\Command;

/***************************************************************
 *  Copyright notice
 *  (c) 2019 Andreas Kiefer
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tpwd\KeSearch\Indexer\IndexerBase;
use Tpwd\KeSearch\Indexer\IndexerRunner;

/**
 * Command for starting the index process of ke_search
 */
class StartIndexerCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var IndexerRunner
     */
    private $indexerRunner;

    public function __construct(IndexerRunner $indexerRunner)
    {
        $this->indexerRunner = $indexerRunner;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        // @todo Remove description when minimum compatibility is set to TYPO3 v11.
        $this->setDescription('Starts the indexing process for ke_search')
            ->setHelp(
                'Will process all active indexer configuration records that are present in the database. '
                . 'There is no possibility to start indexing for a single indexer configuration')
            ->setAliases([
                'kesearch:index',
                'ke_search:indexing',
                'kesearch:indexing'
            ]);

        $this->addOption(
            'indexingMode',
            'm',
            InputOption::VALUE_OPTIONAL,
            'Indexing mode, either "full" (default) or "incremental".',
            'full'
        );
    }

    /**
     * Runs the index process for ke_search
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Start ke_search indexer process');

        $indexingMode =
            (strtolower($input->getOption('indexingMode')) == 'incremental')
                ? IndexerBase::INDEXING_MODE_INCREMENTAL
                : IndexerBase::INDEXING_MODE_FULL;

        $this->logger->notice('Indexer process started by command.');

        $indexerResponse = $this->indexerRunner->startIndexing(true, [], 'CLI', $indexingMode);
        $indexerResponse = $this->indexerRunner->createPlaintextReport($indexerResponse);

        if (str_contains($indexerResponse, 'You can\'t start the indexer twice')) {
            $io->warning(
                'Indexing lock is set. You can\'t start the indexer process twice.' . chr(10) . chr(10)
                . 'If lock was not reset because of indexer errors you can use ke_search:removelock command.'
            );

            return 1;
        }

        // set custom style
        $titleStyle = new OutputFormatterStyle('blue', 'black', array('bold'));
        $output->getFormatter()->setStyle('title', $titleStyle);
        $warning = false;

        foreach (explode(chr(10), $indexerResponse) as $line) {

            // skip empty lines
            if (empty(strip_tags($line))) {
                continue;
            }

            // format lines and catch warnings
            if (str_contains($line, 'There were errors')) {
                $warning = strip_tags(str_replace('[DANGER] ', '', $line));
            } else if (str_contains($line, 'Index contains')) {
                $io->writeln('<info>' . strip_tags($line) . '</info>');
            } else if (str_contains($line, '<p><b>')) {
                $io->writeln(chr(10) . '<title>' . strip_tags($line) . '</>');
            } else {
                $io->writeln(strip_tags($line));
            }
        }

        if ($warning !== false) {
            $io->warning($warning);
        }

        $io->success('Indexer process completed.');

        $this->logger->notice('Indexer process ended by command.');

        return 0;
    }
}
