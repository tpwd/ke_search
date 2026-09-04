<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Functional\IndexerConfiguration;

use Tpwd\KeSearch\Service\IndexerConfigurationService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class IndexerConfigurationServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/ke_search',
    ];

    #[\PHPUnit\Framework\Attributes\Test]
    public function serviceCanBeInstantiatedAndReturnsArray(): void
    {
        $service = GeneralUtility::makeInstance(IndexerConfigurationService::class);
        $configurations = $service->getConfigurations();

        self::assertCount(0, $configurations);
    }
}
