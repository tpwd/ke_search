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

use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Utility\TimeUtility;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

class StatusWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private array $options = [];
    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly BackendViewFactory $backendViewFactory,
        private readonly Registry $registry
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $view = $this->backendViewFactory->create($this->request, ['typo3/cms-dashboard', 'tpwd/ke_search']);

        $indexerStartTime = SearchHelper::getIndexerStartTime();
        $indexerRunningTime = TimeUtility::getRunningTime($indexerStartTime);
        $indexerRunningTimeHMS = TimeUtility::getTimeHoursMinutesSeconds($indexerRunningTime);
        $view->assignMultiple([
            'configuration' => $this->configuration,
            'indexerStartTime' => $indexerStartTime,
            'indexerRunningTime' => $indexerRunningTime,
            'indexerRunningTimeHMS' => $indexerRunningTimeHMS,
        ]);

        $lastRun = $this->registry->get('tx_kesearch', 'lastRun');
        if (!empty($lastRun)) {
            $lastRunIndexingTimeHMS = TimeUtility::getTimeHoursMinutesSeconds($lastRun['indexingTime'] ?? 0);
            $view->assignMultiple([
                'lastRunStartTime' => $lastRun['startTime'],
                'lastRunEndTime' => $lastRun['endTime'],
                'lastRunIndexingTime' => $lastRun['indexingTime'],
                'lastRunIndexingTimeHMS' => $lastRunIndexingTimeHMS,
            ]);
        }

        return $view->render('Widget/StatusWidget');
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
