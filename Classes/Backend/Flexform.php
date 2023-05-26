<?php

namespace Tpwd\KeSearch\Backend;

use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Flexform
{
    public LanguageService $lang;
    private IndexRepository $indexRepository;

    public function __construct()
    {
        $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
        $this->indexRepository = GeneralUtility::makeInstance(IndexRepository::class);
        $this->lang = $languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    public function listAvailableOrderingsForFrontend(&$config)
    {
        // Add "relevance" as a sorting field
        $fieldLabel = $this->lang->sL('LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.relevance');
        $config['items'][] = [$fieldLabel, 'score'];

        // Add all the other fields by looking at the table columns
        $additionalColumns = $this->indexRepository->getColumnsRelevantForSorting();
        foreach ($additionalColumns as $col) {
            $field = $GLOBALS['TCA']['tx_kesearch_index']['columns'][$col['Field']]['label'] ?? $col['Field'];
            $fieldLabel = $this->lang->sL($field);
            $config['items'][] = [$fieldLabel, $col['Field']];
        }
    }

    public function listAvailableOrderingsForAdmin(&$config)
    {
        // Add "relevance" as a sorting field
        $fieldLabel = $this->lang->sL('LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.relevance');
        if (!($config['config']['relevanceNotAllowed'] ?? false)) {
            $config['items'][] = [$fieldLabel . ' UP', 'score asc'];
            $config['items'][] = [$fieldLabel . ' DOWN', 'score desc'];
        }

        // Add all the other fields by looking at the table columns
        $additionalColumns = $this->indexRepository->getColumnsRelevantForSorting();
        foreach ($additionalColumns as $col) {
            $field = $GLOBALS['TCA']['tx_kesearch_index']['columns'][$col['Field']]['label'] ?? $col['Field'];
            $fieldLabel = $this->lang->sL($field);
            $config['items'][] = [$fieldLabel . ' UP', $col['Field'] . ' asc'];
            $config['items'][] = [$fieldLabel . ' DOWN', $col['Field'] . ' desc'];
        }
    }
}
