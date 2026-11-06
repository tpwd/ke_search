<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Utility;

use Tpwd\KeSearch\Domain\Search\SearchContextInterface;

class SearchPhraseUtility
{
    public static function hasLeadingPlusOrMinus(string $word): bool
    {
        return (bool)preg_match('/^[+-]/', $word);
    }

    public static function isPhrase(string $word): bool
    {
        return (bool)preg_match('/^([+\-~<>])?\"/', $word);
    }

    public static function normalizeAndValidateSearchWord(string $word, SearchContextInterface $searchContext): ?string
    {
        $normalizedWord = preg_replace('/[+-]+(?=\*?$)/', '', $word) ?? $word;
        if ($normalizedWord !== $word) {
            $word = $normalizedWord;
            if (mb_strlen(trim($word, '+-~<>')) < $searchContext->getExtConf()['searchWordLength']) {
                $searchContext->setHasTooShortWords(true);
                return null;
            }
        }
        if ($word === '' || $word === '*') {
            return null;
        }

        return $word;
    }
}
