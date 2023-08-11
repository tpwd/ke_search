<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tpwd\KeSearch\Widgets\Provider\IndexOverviewDataProvider;
use Tpwd\KeSearch\Widgets\Provider\TrendingSearchphrasesDataProvider;
use Tpwd\KeSearch\Widgets\StatusWidget;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\BarChartWidget;
use TYPO3\CMS\Dashboard\Widgets\ListWidget;

return function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder) {
    if ($containerBuilder->hasDefinition(BarChartWidget::class)) {
        $services = $configurator->services();

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 11) {
            $services->set('dashboard.widget.ke_search_indexer_status')
                ->class(StatusWidget::class)
                // Todo: Pass $backendViewFactory instead of $view here once support for TYPO3 v11 is dropped and adjust the signature of the class StatusWidget
                // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Breaking-96812-NoFrontendTypoScriptBasedTemplateOverridesInTheBackend.html
                ->arg('$view', new Reference('dashboard.views.widget'))
                ->tag(
                    'dashboard.widget',
                    [
                        'identifier' => 'keSearchStatus',
                        'groupNames' => 'ke_search',
                        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchStatus.title',
                        'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchStatus.description',
                        'iconIdentifier' => 'ext-kesearch-wizard-icon',
                        'height' => 'small',
                        'width' => 'small',
                    ]
                );

            $services->set('dashboard.widget.ke_search_index_overview')
                ->class(BarChartWidget::class)
                ->arg('$dataProvider', new Reference(IndexOverviewDataProvider::class))
                ->arg('$backendViewFactory', new Reference(BackendViewFactory::class))
                ->tag(
                    'dashboard.widget',
                    [
                        'identifier' => 'keSearchIndexOverview',
                        'groupNames' => 'ke_search',
                        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchIndexOverview.title',
                        'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchIndexOverview.description',
                        'iconIdentifier' => 'ext-kesearch-wizard-icon',
                        'height' => 'medium',
                        'width' => 'medium',
                    ]
                );

            $services->set('dashboard.widget.ke_search_trending_searchphrases')
                ->class(ListWidget::class)
                ->arg('$dataProvider', new Reference(TrendingSearchphrasesDataProvider::class))
                ->arg('$backendViewFactory', new Reference(BackendViewFactory::class))
                ->tag(
                    'dashboard.widget',
                    [
                        'identifier' => 'keSearchTrendingSearchphrases',
                        'groupNames' => 'ke_search',
                        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchTrendingSearchphrases.title',
                        'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchTrendingSearchphrases.description',
                        'iconIdentifier' => 'ext-kesearch-wizard-icon',
                        'height' => 'medium',
                        'width' => 'medium',
                    ]
                );
        } else {
            $services->set('dashboard.widget.ke_search_indexer_status')
                ->class(StatusWidget::class)
                ->arg('$view', new Reference('dashboard.views.widget'))
                ->tag(
                    'dashboard.widget',
                    [
                        'identifier' => 'keSearchStatus',
                        'groupNames' => 'ke_search',
                        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchStatus.title',
                        'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchStatus.description',
                        'iconIdentifier' => 'ext-kesearch-wizard-icon',
                        'height' => 'small',
                        'width' => 'small',
                    ]
                );

            $services->set('dashboard.widget.ke_search_index_overview')
                ->class(BarChartWidget::class)
                ->arg('$dataProvider', new Reference(IndexOverviewDataProvider::class))
                ->arg('$view', new Reference('dashboard.views.widget'))
                ->tag(
                    'dashboard.widget',
                    [
                        'identifier' => 'keSearchIndexOverview',
                        'groupNames' => 'ke_search',
                        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchIndexOverview.title',
                        'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchIndexOverview.description',
                        'iconIdentifier' => 'ext-kesearch-wizard-icon',
                        'height' => 'medium',
                        'width' => 'medium',
                    ]
                );

            $services->set('dashboard.widget.ke_search_trending_searchphrases')
                ->class(ListWidget::class)
                ->arg('$dataProvider', new Reference(TrendingSearchphrasesDataProvider::class))
                ->arg('$view', new Reference('dashboard.views.widget'))
                ->tag(
                    'dashboard.widget',
                    [
                        'identifier' => 'keSearchTrendingSearchphrases',
                        'groupNames' => 'ke_search',
                        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchTrendingSearchphrases.title',
                        'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:widgets.keSearchTrendingSearchphrases.description',
                        'iconIdentifier' => 'ext-kesearch-wizard-icon',
                        'height' => 'medium',
                        'width' => 'medium',
                    ]
                );
        }
    }
};
