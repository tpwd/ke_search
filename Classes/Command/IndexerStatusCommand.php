<?php

namespace Tpwd\KeSearch\Command;

/***************************************************************
 *  Copyright notice
 *  (c) 2024 Christian Bülter
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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tpwd\KeSearch\Service\IndexerStatusService;

/**
 * Command for starting the index process of ke_search
 */
class IndexerStatusCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected IndexerStatusService $indexerStatusService;

    public function __construct(IndexerStatusService $indexerStatusService)
    {
        $this->indexerStatusService = $indexerStatusService;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setHelp(
            'Shows the current status of the indexer. If mode is "short", only the status is'
            . ' shown ("running" or "idle"), otherwise a detailed report is displayed.'
        );

        $this->addOption(
            'mode',
            'm',
            InputOption::VALUE_OPTIONAL,
            'Mode, either "full" (default) or "short".',
            'full'
        );
    }

    /**
     * Runs the index process for ke_search
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($input->getOption('mode') === 'short') {
            $output = $this->indexerStatusService->isRunning() ? 'running' : 'idle';
        } else {
            $output = $this->indexerStatusService->getStatusReport(IndexerStatusService::INDEXER_STATUS_REPORT_FORMAT_PLAIN);
        }
        $io->writeln($output);
        return self::SUCCESS;
    }
}
