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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;

/**
 * Command for completely clearing the ke_search index
 */
class ClearIndexCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var IndexRepository
     */
    private $indexRepository;

    public function __construct(IndexRepository $indexRepository)
    {
        $this->indexRepository = $indexRepository;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        // @todo Remove description when minimum compatibility is set to TYPO3 v11.
        $this->setDescription('Truncates the ke_search index table')
            ->setHelp(
                'Completely truncates the ke_search index table. Use with care!'
            )
            ->setAliases([
                'kesearch:clearindex',
                'ke_search:cleanindex',
                'kesearch:cleanindex'
            ]);
    }

    /**
     * Removes the lock for the ke_search index process
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Clear ke_search index table');

        $this->logger->notice('Clear index table started by command.');

        $countIndex = $this->indexRepository->getTotalNumberOfRecords();
        if ($countIndex > 0) {
            try {
                $io->text($countIndex . ' index records found');
                $this->indexRepository->truncate();
                $io->success('ke_search index table was truncated');
                $logMessage = 'Index table was cleared';
                $logMessage .= ' (' . $countIndex . ' records deleted)';
                $this->logger->notice($logMessage);
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                $this->logger->error($e->getMessage());

                return 1;
            }
        } else {
            $io->note('There are no entries in ke_search index table.');
        }

        return 0;
    }
}
