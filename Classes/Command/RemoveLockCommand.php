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
use TYPO3\CMS\Core\Registry;

/**
 * Command for removing the ke_search index lock
 */
class RemoveLockCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Registry
     */
    private $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        // @todo Remove description when minimum compatibility is set to TYPO3 v11.
        $this->setDescription('Removes the lock for the ke_search index process')
            ->setHelp(
                'Removing the lock for the ke_search index process can be useful when errors occured '
                . 'while indexing. In this case, the lock won\'t be removed automatically and can be done '
                . 'manually by this command.')
            ->setAliases(['kesearch:removelock']);
    }

    /**
     * Removes the lock for the ke_search index process
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Remove ke_search indexer lock');

        $this->logger->notice('Remove indexer lock started by command.');

        try {
            if (intval($this->registry->get('tx_kesearch', 'startTimeOfIndexer')) === 0) {
                $io->note('Indexer lock is not set.');
                $this->logger->notice('Indexer lock is not set');
            } else {
                $this->registry->removeAllByNamespace('tx_kesearch');
                $io->success('Indexer lock was successfully removed');
                $this->logger->notice('Indexer lock successfully removed');
            }
        } catch (\Exception $e) {
            $io->error('There was an error accessing the TYPO3 registry.');
            $this->logger->error('There was an error accessing the TYPO3 registry');

            return 1;
        }

        return 0;
    }
}
