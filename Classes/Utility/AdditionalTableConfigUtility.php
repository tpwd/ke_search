<?php

namespace Tpwd\KeSearch\Utility;

use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdditionalTableConfigUtility
{
    public static function parseAndProcessAdditionalTablesConfiguration(string $iniString, array $indexerConfig): array
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $additionalTableConfig = false;
        // parse_ini_string will throw a warning if it could not parse the string.
        // If the system is configured to turn a warning into an exception we catch it here.
        try {
            $additionalTableConfig = parse_ini_string($iniString, true);
        } catch (\Exception $e) {
            $errorMessage =
                'Error while parsing additional table configuration for indexer "' . $indexerConfig['title']
                . '": ' . $e->getMessage();
            $logger->error($errorMessage);
        }
        if ($additionalTableConfig === false) {
            $errorMessage = 'Could not parse additional table configuration for indexer "' . $indexerConfig['title'] . '".';
            $logger->error($errorMessage);
            $additionalTableConfig = [];
        }
        return $additionalTableConfig;
    }
}
