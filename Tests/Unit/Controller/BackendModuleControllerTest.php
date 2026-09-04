<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Tpwd\KeSearch\Controller\BackendModuleController;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use Tpwd\KeSearch\Service\IndexerStatusService;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\PageTsConfig;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Cache\FluidTemplateCache;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolverFactoryInterface;
use TYPO3Fluid\Fluid\Core\Parser\TemplateProcessor\NamespaceDetectionTemplateProcessor;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentProcessorInterface;

class BackendModuleControllerTest extends TestCase
{
    private BackendModuleController $subject;

    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['preProcessors'] = [NamespaceDetectionTemplateProcessor::class];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['expressionNodeTypes'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['interceptors'] = [];

        $indexRepositoryMock = $this->createMock(IndexRepository::class);
        $moduleTemplateFactory = (new \ReflectionClass(ModuleTemplateFactory::class))->newInstanceWithoutConstructor();
        $pageRendererMock = $this->createMock(PageRenderer::class);
        $indexerStatusServiceMock = $this->createMock(IndexerStatusService::class);

        $packagePath = dirname(__DIR__, 3) . '/';
        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn($packagePath);

        $packageManagerMock = $this->createMock(PackageManager::class);
        $packageManagerMock->method('getPackage')->willReturnCallback(function (string $packageName) use ($packageMock) {
            return $packageMock;
        });

        $cacheMock = $this->createMock(FluidTemplateCache::class);
        $pageTsConfig = new PageTsConfig(new RootNode(), []);
        $runtimeCacheMock = $this->createMock(FrontendInterface::class);
        $runtimeCacheMock->method('get')->willReturnCallback(function (string $entryIdentifier) use ($pageTsConfig) {
            if (str_starts_with($entryIdentifier, 'pageTsConfig-pid-to-hash-')) {
                return 'dummy-hash';
            }
            if ($entryIdentifier === 'pageTsConfig-hash-to-object-dummy-hash') {
                return $pageTsConfig;
            }
            return false;
        });
        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturnCallback(function (string $identifier) use ($cacheMock, $runtimeCacheMock) {
            if ($identifier === 'fluid_template') {
                return $cacheMock;
            }
            return $runtimeCacheMock;
        });
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);

        $argumentProcessorMock = $this->createMock(ArgumentProcessorInterface::class);
        $argumentProcessorMock->method('process')->willReturnArgument(0);
        $argumentProcessorMock->method('isValid')->willReturn(true);
        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock->method('get')->willReturnCallback(function (string $id) {
            if ($id === NamespaceDetectionTemplateProcessor::class) {
                return new NamespaceDetectionTemplateProcessor();
            }
            return null;
        });

        $namespaces = [
            'f' => ['TYPO3Fluid\\Fluid\\ViewHelpers', 'TYPO3\\CMS\\Fluid\\ViewHelpers'],
        ];

        $viewHelperResolverFactoryMock = $this->createMock(ViewHelperResolverFactoryInterface::class);
        $viewHelperResolverFactoryMock->method('create')->willReturn(new ViewHelperResolver($containerMock, $namespaces));

        $renderingContextFactory = new RenderingContextFactory(
            $containerMock,
            $cacheManagerMock,
            $viewHelperResolverFactoryMock,
            $argumentProcessorMock
        );

        $backendViewFactory = new BackendViewFactory(
            $renderingContextFactory,
            $packageManagerMock
        );

        $this->subject = new BackendModuleController(
            $indexRepositoryMock,
            $moduleTemplateFactory,
            $pageRendererMock,
            $indexerStatusServiceMock,
            $backendViewFactory
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function printIndexerConfigurationsReturnsEmptyStringIfConfigurationsEmpty(): void
    {
        $result = $this->subject->printIndexerConfigurations([]);
        self::assertSame('', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function printIndexerConfigurationsRendersFluidTemplateWithDbAndYamlConfigurations(): void
    {
        $indexerConfigurations = [
            [
                'title' => 'Page Indexer & More',
                'type' => 'page',
                'source' => 'database',
                'uid' => 123,
                'pid' => 456,
            ],
            [
                'title' => 'YAML Indexer',
                'type' => 'custom',
                'source' => 'yaml',
                'sourceFile' => 'EXT:my_ext/Configuration/ke_search.yaml',
                'extensionKey' => 'my_ext',
                'identifier' => 'custom_indexer',
            ],
        ];

        $result = $this->subject->printIndexerConfigurations($indexerConfigurations);

        self::assertStringContainsString('id="kesearch-startindexing-indexers"', $result);
        self::assertStringContainsString('Page Indexer &amp; More', $result);
        self::assertStringContainsString('YAML Indexer', $result);
        self::assertStringContainsString('<th>UID</th>', $result);
        self::assertStringContainsString('<th>Extension</th>', $result);
        self::assertStringContainsString('123', $result);
        self::assertStringContainsString('456', $result);
        self::assertStringContainsString('my_ext', $result);
        self::assertStringContainsString('custom_indexer', $result);
        self::assertStringContainsString('title="EXT:my_ext/Configuration/ke_search.yaml"', $result);
        self::assertStringContainsString('class="badge badge-info"', $result);
        self::assertStringContainsString('class="badge badge-secondary"', $result);
    }
}
