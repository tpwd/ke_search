<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Indexer\Types;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Indexer\Types\News;

class NewsTest extends TestCase
{
    #[Test]
    public function contentElementHeadersAreIndexedOnlyWhenPresentAndVisible(): void
    {
        $indexer = (new \ReflectionClass(News::class))->newInstanceWithoutConstructor();

        $contentElements = [
            [
                'header' => null,
                'header_layout' => 0,
                'bodytext' => '<p>Body without header</p>',
            ],
            [
                'header_layout' => 0,
                'bodytext' => '<p>Body with missing header key</p>',
            ],
            [
                'header' => '<b>Visible header</b>',
                'header_layout' => 0,
                'bodytext' => '<p>Body with header</p>',
            ],
            [
                'header' => '',
                'header_layout' => 0,
                'bodytext' => '<p>Body with empty header</p>',
            ],
            [
                'header' => 'Hidden header',
                'header_layout' => 100,
                'bodytext' => '<p>Body with hidden header</p>',
            ],
        ];

        $content = $indexer->getContentFromContentElements($contentElements);

        self::assertStringContainsString('Body without header', $content);
        self::assertStringContainsString('Body with missing header key', $content);
        self::assertStringContainsString('Visible header', $content);
        self::assertStringNotContainsString('<b>', $content);
        self::assertStringContainsString('Body with empty header', $content);
        self::assertStringContainsString('Body with hidden header', $content);
        self::assertStringNotContainsString('Hidden header', $content);
        self::assertStringNotContainsString("\n\n\n", $content);
    }
}
