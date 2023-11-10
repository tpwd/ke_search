<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Utility;

use Tpwd\KeSearch\Lib\SearchHelper;

class AdditionalWordCharactersUtility
{
    public static function getAdditionalWordCharacters(): array
    {
        $extConf = SearchHelper::getExtConf();
        $additionalWordCharacters = [];
        if (!empty($extConf['additionalWordCharacters'] ?? '')) {
            foreach (str_split($extConf['additionalWordCharacters']) as $char) {
                $additionalWordCharacters[] = $char;
            }
        }
        return $additionalWordCharacters;
    }

    public static function getAdditionalContent(string $content): string
    {
        $additionalWordCharacters = self::getAdditionalWordCharacters();
        if (empty($additionalWordCharacters)) {
            return '';
        }
        $additionalContent = '';
        foreach ($additionalWordCharacters as $additionalWordCharacter) {
            $matches = [];
            $pattern = '/(?=(?:[^\s]*[' . $additionalWordCharacter . ']){1,})\S+/';
            preg_match_all($pattern, $content, $matches);
            if ($matches) {
                foreach ($matches as $match) {
                    if (!empty($additionalContent)) {
                        $additionalContent .= ' ';
                    }
                    $additionalContent .= str_replace(
                        $additionalWordCharacter,
                        self::getReplacementForAdditionalWordCharacter($additionalWordCharacter),
                        implode(' ', $match)
                    );
                }
            }
        }
        return $additionalContent;
    }

    public static function replaceAdditionalWordCharacters(string $content): string
    {
        $additionalWordCharacters = self::getAdditionalWordCharacters();
        if (empty($additionalWordCharacters)) {
            return $content;
        }
        foreach ($additionalWordCharacters as $additionalWordCharacter) {
            $content = str_replace(
                $additionalWordCharacter,
                self::getReplacementForAdditionalWordCharacter($additionalWordCharacter),
                $content
            );
        }
        return $content;
    }

    public static function getReplacementForAdditionalWordCharacter(string $character): string
    {
        return '___' . ord($character) . '___';
    }
}
