<?php

namespace Tpwd\KeSearch\Backend;

use Tpwd\KeSearch\Lib\Db;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Flexform
{
    public $lang;
    public $notAllowedFields;

    public function init()
    {
        $this->lang = GeneralUtility::makeInstance(LanguageService::class);
        $this->notAllowedFields = 'uid,pid,tstamp,crdate,cruser_id,starttime,endtime'
            . ',fe_group,targetpid,content,params,type,tags,abstract,language'
            . ',orig_uid,orig_pid,hash,lat,lon,externalurl,lastremotetransfer';
    }

    public function listAvailableOrderingsForFrontend(&$config)
    {
        $this->init();
        $this->lang->init($GLOBALS['BE_USER']->uc['lang']);

        // get orderings
        $fieldLabel = $this->lang->sL('LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.relevance');
        $config['items'][] = [$fieldLabel, 'score'];
        $res = Db::getDatabaseConnection('tx_kesearch_index')->fetchAll('SHOW COLUMNS FROM tx_kesearch_index');

        foreach ($res as $col) {
            $isInList = GeneralUtility::inList($this->notAllowedFields, $col['Field']);
            if (!$isInList) {
                $file = $GLOBALS['TCA']['tx_kesearch_index']['columns'][$col['Field']]['label'] ?? $col['Field'];
                $fieldLabel = $this->lang->sL($file);
                $config['items'][] = [$fieldLabel, $col['Field']];
            }
        }
    }

    public function listAvailableOrderingsForAdmin(&$config)
    {
        $this->init();
        $this->lang->init($GLOBALS['BE_USER']->uc['lang']);

        // get orderings
        $fieldLabel = $this->lang->sL('LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.relevance');
        if (!($config['config']['relevanceNotAllowed'] ?? false)) {
            $config['items'][] = [$fieldLabel . ' UP', 'score asc'];
            $config['items'][] = [$fieldLabel . ' DOWN', 'score desc'];
        }
        $res = Db::getDatabaseConnection('tx_kesearch_index')->fetchAll('SHOW COLUMNS FROM tx_kesearch_index');

        foreach ($res as $col) {
            $isInList = GeneralUtility::inList($this->notAllowedFields, $col['Field']);
            if (!$isInList) {
                $file = $GLOBALS['TCA']['tx_kesearch_index']['columns'][$col['Field']]['label'] ?? $col['Field'];
                $fieldLabel = $this->lang->sL($file);
                $config['items'][] = [$fieldLabel . ' UP', $col['Field'] . ' asc'];
                $config['items'][] = [$fieldLabel . ' DOWN', $col['Field'] . ' desc'];
            }
        }
    }
}
