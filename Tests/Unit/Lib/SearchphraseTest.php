<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Lib;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Domain\Search\SearchExecutionContext;
use Tpwd\KeSearch\Lib\Searchphrase;
use Tpwd\KeSearch\Utility\AdditionalWordCharactersUtility;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class SearchphraseTest extends TestCase
{
    #[Test]
    public function quotedPhraseDoesNotGetTrailingWildcardAppended(): void
    {
        $searchphrase = $this->createSearchphrase();

        $result = $searchphrase->explodeSearchPhrase('"hello world"');

        self::assertSame(['"hello world"'], $result);
    }

    #[Test]
    public function modifiedQuotedPhraseIsNotCleanedOrWildcardedBelowMinimumWordLength(): void
    {
        $searchphrase = $this->createSearchphrase(['searchWordLength' => 10]);

        $result = $searchphrase->explodeSearchPhrase('+"x+"');

        self::assertSame(['+"x+"'], $result);
        self::assertFalse($searchphrase->getSearchContext()->getHasTooShortWords());
    }

    #[Test]
    public function plainWordStillGetsTrailingWildcardAppended(): void
    {
        $searchphrase = $this->createSearchphrase();

        $result = $searchphrase->explodeSearchPhrase('hello');

        self::assertSame(['hello*'], $result);
    }

    #[Test]
    public function trailingBooleanOperatorsAreRemovedBeforePartialWildcardIsAdded(): void
    {
        $searchphrase = $this->createSearchphrase();

        $result = $searchphrase->explodeSearchPhrase('Univ+ Univ- Univ+* Univ*+');

        self::assertSame(['Univ*', 'Univ*', 'Univ*', 'Univ*'], $result);
    }

    #[Test]
    public function trailingBooleanOperatorsAreRemovedBeforeSphinxInWordWildcardsAreAdded(): void
    {
        $searchphrase = $this->createSearchphrase([], [
            'enableSphinxSearch' => true,
            'enableInWordSearch' => 1,
        ]);

        $result = $searchphrase->explodeSearchPhrase('Univ+');

        self::assertSame(['*Univ*'], $result);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function trailingBooleanOperatorsAreRemovedWhenPartialSearchIsDisabled(): void
    {
        $this->initializePackageManager();
        $searchphrase = $this->createSearchphrase(['enablePartSearch' => false]);

        $result = $searchphrase->explodeSearchPhrase('Univ+ Univ- Univ+* Univ*+');

        self::assertSame(['Univ', 'Univ', 'Univ*', 'Univ*'], $result);
    }

    #[Test]
    public function leadingBooleanOperatorsArePreserved(): void
    {
        $searchphrase = $this->createSearchphrase();

        $result = $searchphrase->explodeSearchPhrase('+Univ -Univ');

        self::assertSame(['+Univ*', '-Univ*'], $result);
    }

    #[Test]
    public function trailingBooleanOperatorsInsideQuotedPhrasesArePreserved(): void
    {
        $searchphrase = $this->createSearchphrase();

        $result = $searchphrase->explodeSearchPhrase('"Univ+" "Univ-"');

        self::assertSame(['"Univ+"', '"Univ-"'], $result);
    }

    #[Test]
    public function operatorOnlyWordsAreDiscardedWhenMinimumWordLengthIsZero(): void
    {
        $searchphrase = $this->createSearchphrase(['searchWordLength' => 0]);

        $result = $searchphrase->explodeSearchPhrase('+ -');

        self::assertSame([], $result);
    }

    #[Test]
    public function trailingOperatorDoesNotCountTowardMinimumWordLength(): void
    {
        $searchphrase = $this->createSearchphrase(['searchWordLength' => 4]);

        $result = $searchphrase->explodeSearchPhrase('Uni+');

        self::assertSame([], $result);
        self::assertTrue($searchphrase->getSearchContext()->getHasTooShortWords());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function configuredAdditionalWordCharactersAreReplacedBeforeTrailingOperatorsAreRemoved(): void
    {
        $this->initializePackageManager();
        $originalTypo3ConfVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ke_search'] = ['additionalWordCharacters' => '+'];

        try {
            $searchphrase = $this->createSearchphrase();
            $result = $searchphrase->explodeSearchPhrase('Univ+', true);
        } finally {
            if ($originalTypo3ConfVars === null) {
                unset($GLOBALS['TYPO3_CONF_VARS']);
            } else {
                $GLOBALS['TYPO3_CONF_VARS'] = $originalTypo3ConfVars;
            }
        }

        self::assertSame(['Univ___43___*'], $result);
    }

    #[Test]
    public function explicitAndIsStillAppliedToQuotedPhrases(): void
    {
        $searchphrase = $this->createSearchphrase(['enableExplicitAnd' => true]);

        $result = $searchphrase->explodeSearchPhrase('"hello world"');

        self::assertSame(['+"hello world"'], $result);
    }

    #[Test]
    public function explicitAndDoesNotOverrideLeadingBooleanOperators(): void
    {
        $searchphrase = $this->createSearchphrase(['enableExplicitAnd' => true]);

        $result = $searchphrase->explodeSearchPhrase('+hello -world');

        self::assertSame(['+hello*', '-world*'], $result);
    }

    #[Test]
    public function configuredAdditionalWordCharactersProduceFullyEncodedVariantForMultipleSeparators(): void
    {
        $this->initializePackageManager();
        $originalTypo3ConfVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ke_search'] = ['additionalWordCharacters' => '.@'];

        try {
            $additionalContent = AdditionalWordCharactersUtility::getAdditionalContent('info@example.com');

            self::assertStringContainsString('info___64___example___46___com', $additionalContent);
            self::assertStringContainsString('info@example___46___com', $additionalContent);
            self::assertStringContainsString('info___64___example.com', $additionalContent);
        } finally {
            if ($originalTypo3ConfVars === null) {
                unset($GLOBALS['TYPO3_CONF_VARS']);
            } else {
                $GLOBALS['TYPO3_CONF_VARS'] = $originalTypo3ConfVars;
            }
        }
    }

    #[Test]
    public function replaceSingleAdditionalWordCharacterEncodesOnlySelectedCharacter(): void
    {
        $this->initializePackageManager();
        $originalTypo3ConfVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ke_search'] = ['additionalWordCharacters' => '.@'];

        try {
            self::assertSame(
                'info___64___example.com',
                AdditionalWordCharactersUtility::replaceSingleAdditionalWordCharacter('info@example.com', '@')
            );
            self::assertSame(
                'info@example___46___com',
                AdditionalWordCharactersUtility::replaceSingleAdditionalWordCharacter('info@example.com', '.')
            );
        } finally {
            if ($originalTypo3ConfVars === null) {
                unset($GLOBALS['TYPO3_CONF_VARS']);
            } else {
                $GLOBALS['TYPO3_CONF_VARS'] = $originalTypo3ConfVars;
            }
        }
    }

    private function createSearchphrase(array $extConf = [], array $extConfPremium = []): Searchphrase
    {
        $searchContext = new SearchExecutionContext();
        $searchContext->setExtConf($extConf + [
            'enablePartSearch' => true,
            'enableExplicitAnd' => false,
            'searchWordLength' => 1,
        ]);
        $searchContext->setExtConfPremium($extConfPremium);

        $searchphrase = new Searchphrase();
        $searchphrase->initialize($searchContext);

        return $searchphrase;
    }

    private function initializePackageManager(): void
    {
        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn(false);
        ExtensionManagementUtility::setPackageManager($packageManager);
    }
}
