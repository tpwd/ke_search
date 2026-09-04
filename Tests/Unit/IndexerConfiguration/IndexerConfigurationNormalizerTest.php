<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\IndexerConfiguration;

use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationNormalizer;

class IndexerConfigurationNormalizerTest extends TestCase
{
    private IndexerConfigurationNormalizer $subject;

    protected function setUp(): void
    {
        parent::setUp();
        unset($GLOBALS['TCA']['tx_kesearch_indexerconfig']);
        IndexerConfigurationNormalizer::clearCategoryRegistry();
        $this->subject = new IndexerConfigurationNormalizer();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TCA']['tx_kesearch_indexerconfig']);
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function missingRequiredFieldsReturnsNull(): void
    {
        // Missing type
        $config = [
            'identifier' => 'test_page',
            'title' => 'Test Page Indexer',
        ];
        self::assertNull($this->subject->normalize($config, 'EXT:test/Config.yaml'));

        // Missing identifier
        $config = [
            'title' => 'Test Page Indexer',
            'type' => 'page',
        ];
        self::assertNull($this->subject->normalize($config, 'EXT:test/Config.yaml'));

        // Missing title
        $config = [
            'identifier' => 'test_page',
            'type' => 'page',
        ];
        self::assertNull($this->subject->normalize($config, 'EXT:test/Config.yaml'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validConfigurationIsNormalizedWithDefaultsAndNegativeUid(): void
    {
        $raw = [
            'identifier' => 'main_pages',
            'title' => 'Main Site Pages',
            'type' => 'page',
            'startingpoints_recursive' => [12, 34],
            'single_pages' => '56,78',
            'storagepid' => 100,
            'fileext' => ['pdf', 'docx'],
        ];

        $normalized = $this->subject->normalize($raw, 'EXT:site/Configuration/KeSearch/IndexerConfigurations/pages.yaml');

        self::assertNotNull($normalized);
        self::assertSame('main_pages', $normalized['identifier']);
        self::assertSame('Main Site Pages', $normalized['title']);
        self::assertSame('page', $normalized['type']);
        self::assertSame('12,34', $normalized['startingpoints_recursive']);
        self::assertSame('56,78', $normalized['single_pages']);
        self::assertSame('100', $normalized['storagepid']);
        self::assertSame(100, $normalized['pid']);
        self::assertSame('pdf,docx', $normalized['fileext']);
        self::assertSame('site', $normalized['extensionKey']);
        self::assertSame('yaml', $normalized['source']);
        self::assertSame('EXT:site/Configuration/KeSearch/IndexerConfigurations/pages.yaml', $normalized['sourceFile']);

        // UID must be negative and not -1
        self::assertLessThan(-1, $normalized['uid']);

        // Synthetic UID is deterministic
        $secondUid = $this->subject->generateSyntheticUid('main_pages');
        self::assertSame($normalized['uid'], $secondUid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multiConfigurationFormatsAreNotSupported(): void
    {
        // Under 'indexers' key
        $data1 = [
            'indexers' => [
                [
                    'identifier' => 'idx_1',
                    'title' => 'Index 1',
                    'type' => 'page',
                ],
                [
                    'identifier' => 'idx_2',
                    'title' => 'Index 2',
                    'type' => 'news',
                ],
            ],
        ];
        self::assertNull($this->subject->normalize($data1, 'file1.yaml'));

        // Under 'configurations' key
        $data2 = [
            'configurations' => [
                [
                    'identifier' => 'idx_3',
                    'title' => 'Index 3',
                    'type' => 'file',
                ],
            ],
        ];
        self::assertNull($this->subject->normalize($data2, 'file2.yaml'));

        // As top-level list
        $data3 = [
            [
                'identifier' => 'idx_4',
                'title' => 'Index 4',
                'type' => 'page',
            ],
        ];
        self::assertNull($this->subject->normalize($data3, 'file3.yaml'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function categoryResolutionAndRegistryRegistration(): void
    {
        $raw = [
            'identifier' => 'news_indexer',
            'title' => 'News Indexer',
            'type' => 'news',
            'index_extnews_category_selection' => [5, 10, 15],
        ];

        $normalized = $this->subject->normalize($raw, 'EXT:site/news.yaml');
        self::assertNotNull($normalized);
        self::assertSame('5,10,15', $normalized['index_extnews_category_selection']);
        self::assertSame(2, $normalized['index_news_category_mode']);

        $registeredCategories = IndexerConfigurationNormalizer::getSelectedCategories($normalized['uid']);
        self::assertSame([5, 10, 15], $registeredCategories);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function categorySelectionAliasIsAccepted(): void
    {
        $raw = [
            'identifier' => 'custom_indexer',
            'title' => 'Custom Indexer',
            'type' => 'my_custom_type',
            'category_selection' => [7, 8, 9],
        ];

        $normalized = $this->subject->normalize($raw, 'EXT:site/custom.yaml');
        self::assertNotNull($normalized);
        self::assertSame('7,8,9', $normalized['index_extnews_category_selection']);
        self::assertSame(2, $normalized['index_news_category_mode']);

        $registeredCategories = IndexerConfigurationNormalizer::getSelectedCategories($normalized['uid']);
        self::assertSame([7, 8, 9], $registeredCategories);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function extensionKeyIsExtractedFromSourceFileOrConfig(): void
    {
        $raw = [
            'identifier' => 'test_idx',
            'title' => 'Test Indexer',
            'type' => 'page',
        ];

        // Extracted from EXT: prefix
        $normalized1 = $this->subject->normalize($raw, 'EXT:my_extension/Configuration/KeSearch/IndexerConfigurations/indexer.yaml');
        self::assertNotNull($normalized1);
        self::assertSame('my_extension', $normalized1['extensionKey']);

        // Explicit extensionKey in config overrides
        $rawWithKey = $raw + ['extensionKey' => 'custom_ext'];
        $normalized2 = $this->subject->normalize($rawWithKey, 'EXT:my_extension/Configuration/KeSearch/IndexerConfigurations/indexer.yaml');
        self::assertNotNull($normalized2);
        self::assertSame('custom_ext', $normalized2['extensionKey']);

        // Fallback when no EXT: prefix and not in config
        $normalized4 = $this->subject->normalize($raw, '/var/www/site/config.yaml');
        self::assertNotNull($normalized4);
        self::assertSame('', $normalized4['extensionKey']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function nonExactConfigurationKeysAreNotAccepted(): void
    {
        $raw = [
            'identifier' => 'legacy_spellings',
            'title' => 'Legacy Spellings Test',
            'type' => 'news',
            'storage_pid' => 99,
            'target_pid' => 88,
            'startingpoints' => [1, 2],
            'file_extensions' => ['pdf', 'doc'],
            'filter_option' => 77,
            'filter_categories' => [12, 34],
        ];

        $normalized = $this->subject->normalize($raw, 'EXT:site/indexer.yaml');
        self::assertNotNull($normalized);

        // storage_pid is ignored, storagepid falls back to default '0'
        self::assertSame('0', $normalized['storagepid']);
        self::assertSame(0, $normalized['pid']);

        // target_pid is ignored, targetpid falls back to default '0'
        self::assertSame('0', $normalized['targetpid']);

        // startingpoints is ignored, startingpoints_recursive falls back to default ''
        self::assertSame('', $normalized['startingpoints_recursive']);

        // file_extensions is ignored, fileext falls back to default ''
        self::assertSame('', $normalized['fileext']);

        // filter_option is ignored, filteroption falls back to default 0
        self::assertSame(0, $normalized['filteroption']);

        // filter_categories is ignored, category selection is empty and mode remains 1
        self::assertSame('', $normalized['index_extnews_category_selection']);
        self::assertSame(1, $normalized['index_news_category_mode']);
        self::assertSame([], IndexerConfigurationNormalizer::getSelectedCategories($normalized['uid']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function booleanAndHiddenFieldsAreNormalized(): void
    {
        $base = [
            'identifier' => 'test_idx',
            'title' => 'Test Indexer',
            'type' => 'page',
        ];

        // Integer 1
        $config1 = $this->subject->normalize($base + ['hidden' => 1], 'file.yaml');
        self::assertSame(1, $config1['hidden']);

        // Boolean true
        $config2 = $this->subject->normalize($base + ['hidden' => true], 'file.yaml');
        self::assertSame(1, $config2['hidden']);

        // String "1"
        $config3 = $this->subject->normalize($base + ['hidden' => '1'], 'file.yaml');
        self::assertSame(1, $config3['hidden']);

        // String "true"
        $config4 = $this->subject->normalize($base + ['hidden' => 'true'], 'file.yaml');
        self::assertSame(1, $config4['hidden']);

        // Integer 0
        $config5 = $this->subject->normalize($base + ['hidden' => 0], 'file.yaml');
        self::assertSame(0, $config5['hidden']);

        // Boolean false
        $config6 = $this->subject->normalize($base + ['hidden' => false], 'file.yaml');
        self::assertSame(0, $config6['hidden']);

        // String "0"
        $config7 = $this->subject->normalize($base + ['hidden' => '0'], 'file.yaml');
        self::assertSame(0, $config7['hidden']);

        // String "false"
        $config8 = $this->subject->normalize($base + ['hidden' => 'false'], 'file.yaml');
        self::assertSame(0, $config8['hidden']);

        // Default (not specified)
        $config9 = $this->subject->normalize($base, 'file.yaml');
        self::assertSame(0, $config9['hidden']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function defaultValuesAreDerivedFromTca(): void
    {
        $GLOBALS['TCA']['tx_kesearch_indexerconfig'] = require __DIR__ . '/../../../Configuration/TCA/tx_kesearch_indexerconfig.php';

        $minimal = [
            'identifier' => 'minimal_idx',
            'title' => 'Minimal Indexer',
            'type' => 'page',
        ];

        $normalized = $this->subject->normalize($minimal, 'file.yaml');
        self::assertNotNull($normalized);

        self::assertSame('pdf,ppt,doc,xls,docx,xlsx,pptx', $normalized['fileext']);
        self::assertSame('bodytext,subheader,header_link', $normalized['content_fields']);
        self::assertSame('media', $normalized['file_reference_fields']);
        self::assertSame('text,textmedia,textpic,bullets,table,html,header,uploads,shortcut,accordion,tab,carousel,carousel_fullscreen,carousel_small,icon_group,card_group,timeline', $normalized['contenttypes']);
        self::assertSame('1', $normalized['index_page_doctypes']);
        self::assertSame('no', $normalized['index_content_with_restrictions']);
        self::assertSame(1, $normalized['index_news_category_mode']);
        self::assertSame(0, $normalized['index_use_page_tags']);
        self::assertSame(0, $normalized['index_use_page_tags_for_files']);
        self::assertSame(0, $normalized['fal_storage']);
        self::assertStringContainsString('tx_bootstrappackage_accordion_item', $normalized['additional_tables']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function overriddenTcaDefaultsAreRespected(): void
    {
        $GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns'] = [
            'fileext' => [
                'config' => [
                    'default' => 'custom_ext,custom_ext2',
                ],
            ],
            'content_fields' => [
                'config' => [
                    'default' => 'custom_body,custom_header',
                ],
            ],
            'fal_storage' => [
                'config' => [
                    'default' => 42,
                ],
            ],
        ];

        $minimal = [
            'identifier' => 'minimal_idx',
            'title' => 'Minimal Indexer',
            'type' => 'page',
        ];

        $normalized = $this->subject->normalize($minimal, 'file.yaml');
        self::assertNotNull($normalized);

        self::assertSame('custom_ext,custom_ext2', $normalized['fileext']);
        self::assertSame('custom_body,custom_header', $normalized['content_fields']);
        self::assertSame(42, $normalized['fal_storage']);
    }
}
