<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Lib;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Domain\Search\SearchExecutionContext;
use Tpwd\KeSearch\Lib\Searchphrase;

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
    public function plainWordStillGetsTrailingWildcardAppended(): void
    {
        $searchphrase = $this->createSearchphrase();

        $result = $searchphrase->explodeSearchPhrase('hello');

        self::assertSame(['hello*'], $result);
    }

    #[Test]
    public function explicitAndIsStillAppliedToQuotedPhrases(): void
    {
        $searchphrase = $this->createSearchphrase(['enableExplicitAnd' => true]);

        $result = $searchphrase->explodeSearchPhrase('"hello world"');

        self::assertSame(['+"hello world"'], $result);
    }

    private function createSearchphrase(array $extConf = []): Searchphrase
    {
        $searchContext = new SearchExecutionContext();
        $searchContext->setExtConf($extConf + [
            'enablePartSearch' => true,
            'enableExplicitAnd' => false,
            'searchWordLength' => 1,
        ]);
        $searchContext->setExtConfPremium([]);

        $searchphrase = new Searchphrase();
        $searchphrase->initialize($searchContext);

        return $searchphrase;
    }
}
