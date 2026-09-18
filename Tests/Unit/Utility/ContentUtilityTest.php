<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Utility\ContentUtility;

class ContentUtilityTest extends TestCase
{
    #[Test]
    public function addSpaceBeforeBlockLevelElementsAddsSpaceBeforeDiv(): void
    {
        $result = ContentUtility::addSpaceBeforeBlockLevelElements('<div>Hello</div><div>World</div>');

        self::assertSame('Hello World', trim(strip_tags($result)));
    }

    #[Test]
    public function addSpaceBeforeBlockLevelElementsAddsSpaceBeforeHeadings(): void
    {
        $result = ContentUtility::addSpaceBeforeBlockLevelElements('<h1>Hello</h1><h2>World</h2>');

        self::assertSame('Hello World', trim(strip_tags($result)));
    }

    #[Test]
    public function addSpaceBeforeBlockLevelElementsAddsSpaceBeforeTableElements(): void
    {
        $result = ContentUtility::addSpaceBeforeBlockLevelElements('<table><tr><td>Hello</td><td>World</td></tr></table>');

        self::assertSame('Hello World', trim(strip_tags($result)));
    }

    #[Test]
    public function addSpaceBeforeBlockLevelElementsDoesNotAffectInlineElements(): void
    {
        $result = ContentUtility::addSpaceBeforeBlockLevelElements('<span>Hello</span><strong>World</strong>');

        self::assertSame('HelloWorld', trim(strip_tags($result)));
    }

    #[Test]
    public function getPlainContentFromContentRowUsesAddSpaceBeforeBlockLevelElements(): void
    {
        $contentRow = [
            'bodytext' => '<div>Hello</div><div>World</div>',
        ];

        $result = ContentUtility::getPlainContentFromContentRow($contentRow, 'bodytext');

        self::assertSame('Hello World', trim($result));
    }
}
