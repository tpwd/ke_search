<?php

declare(strict_types=1);

/***************************************************************
 *  Copyright notice
 *  (c) 2020 Christian Bülter
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

namespace Tpwd\KeSearch\Widgets;

use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Utility\TimeUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

class StatusWidget implements WidgetInterface
{
    public Registry $registry;
    private WidgetConfigurationInterface $configuration;
    // Todo: Use $backendViewFactory instead of $view here once support for TYPO3 v11 is dropped and adjust the registration in Services.php
    // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Breaking-96812-NoFrontendTypoScriptBasedTemplateOverridesInTheBackend.html
    private StandaloneView $view;
    private array $options;

    public function __construct(
        WidgetConfigurationInterface $configuration,
        StandaloneView $view
    ) {
        $this->configuration = $configuration;
        $this->view = $view;
        $this->registry = GeneralUtility::makeInstance(Registry::class);
        $this->options = [];
    }

    public function renderWidgetContent(): string
    {
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 11) {
            $this->view->setTemplate('Widget/StatusWidget');
        } else {
            $this->view->setTemplate('Default/Widget/StatusWidget');
        }

        $indexerStartTime = SearchHelper::getIndexerStartTime();
        $indexerRunningTime = TimeUtility::getRunningTime($indexerStartTime);
        $indexerRunningTimeHMS = TimeUtility::getTimeHoursMinutesSeconds($indexerRunningTime);
        $this->view->assignMultiple([
            'configuration' => $this->configuration,
            'indexerStartTime' => $indexerStartTime,
            'indexerRunningTime' => $indexerRunningTime,
            'indexerRunningTimeHMS' => $indexerRunningTimeHMS,
        ]);

        $lastRun = $this->registry->get('tx_kesearch', 'lastRun');
        if (!empty($lastRun)) {
            $lastRunIndexingTimeHMS = TimeUtility::getTimeHoursMinutesSeconds($lastRun['indexingTime'] ?? 0);
            $this->view->assignMultiple([
                'lastRunStartTime' => $lastRun['startTime'],
                'lastRunEndTime' => $lastRun['endTime'],
                'lastRunIndexingTime' => $lastRun['indexingTime'],
                'lastRunIndexingTimeHMS' => $lastRunIndexingTimeHMS,
            ]);
        }

        return $this->view->render();
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
