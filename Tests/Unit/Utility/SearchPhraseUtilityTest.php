<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Domain\Search\SearchExecutionContext;
use Tpwd\KeSearch\Utility\SearchPhraseUtility;

class SearchPhraseUtilityTest extends TestCase
{
    #[Test]
    public function isPhraseRecognizesOpeningQuoteWithEachSupportedModifier(): void
    {
        foreach (['+', '-', '~', '<', '>'] as $modifier) {
            self::assertTrue(SearchPhraseUtility::isPhrase($modifier . '"phrase'));
        }

        self::assertTrue(SearchPhraseUtility::isPhrase('"unfinished phrase'));
    }

    #[Test]
    public function isPhraseRejectsWordsWithoutAnOpeningQuoteAfterAtMostOneModifier(): void
    {
        foreach (['word', 'word"phrase"', '++"phrase"', '+-"phrase"'] as $word) {
            self::assertFalse(SearchPhraseUtility::isPhrase($word));
        }
    }

    #[Test]
    public function normalizeAndValidateSearchWordRemovesTrailingBooleanOperatorsAndPreservesLeadingModifiers(): void
    {
        $searchContext = $this->createSearchContext();

        self::assertSame('+word', SearchPhraseUtility::normalizeAndValidateSearchWord('+word+', $searchContext));
        self::assertSame('-word', SearchPhraseUtility::normalizeAndValidateSearchWord('-word-', $searchContext));
    }

    #[Test]
    public function normalizeAndValidateSearchWordRemovesTrailingOperatorsBeforeExistingWildcards(): void
    {
        $searchContext = $this->createSearchContext();

        self::assertSame('word*', SearchPhraseUtility::normalizeAndValidateSearchWord('word+*', $searchContext));
        self::assertSame('word*', SearchPhraseUtility::normalizeAndValidateSearchWord('word*+', $searchContext));
    }

    #[Test]
    public function normalizeAndValidateSearchWordDiscardsOperatorOnlyAndWildcardOnlyWordsWhenMinimumLengthIsZero(): void
    {
        $searchContext = $this->createSearchContext(0);

        self::assertNull(SearchPhraseUtility::normalizeAndValidateSearchWord('+', $searchContext));
        self::assertNull(SearchPhraseUtility::normalizeAndValidateSearchWord('-', $searchContext));
        self::assertNull(SearchPhraseUtility::normalizeAndValidateSearchWord('*+', $searchContext));
    }

    #[Test]
    public function normalizeAndValidateSearchWordReportsWordsThatBecomeTooShort(): void
    {
        $searchContext = $this->createSearchContext(4);

        self::assertNull(SearchPhraseUtility::normalizeAndValidateSearchWord('abc+', $searchContext));
        self::assertTrue($searchContext->getHasTooShortWords());
    }

    private function createSearchContext(int $searchWordLength = 1): SearchExecutionContext
    {
        $searchContext = new SearchExecutionContext();
        $searchContext->setExtConf(['searchWordLength' => $searchWordLength]);

        return $searchContext;
    }
}
