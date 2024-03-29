<?php

declare(strict_types=1);

/***************************************************************
 *  Copyright notice
 *  (c) 2021 Christian Bülter
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

namespace Tpwd\KeSearch\Widgets\Provider;

use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\WidgetApi;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;

class IndexOverviewDataProvider implements ChartDataProviderInterface
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @inheritDoc
     */
    public function getChartData(): array
    {
        /** @var IndexRepository $indexRepository */
        $indexRepository = GeneralUtility::makeInstance(IndexRepository::class);
        $results_per_type = $indexRepository->getNumberOfRecordsInIndexPerType();

        $labels = [];
        $data = [];
        if (!empty($results_per_type)) {
            foreach ($results_per_type as $label => $value) {
                $labels[] = $label;
                $data[] = $value;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->getLanguageService()->sL('LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchIndexOverview.chart.dataSet.0'),
                    'backgroundColor' => WidgetApi::getDefaultChartColors()[0],
                    'border' => 0,
                    'data' => $data,
                ],
            ],
        ];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
