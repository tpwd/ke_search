<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Utility;

use Tpwd\KeSearch\Lib\SearchHelper;

class AdditionalWordCharactersUtility
{
    /**
     * Returns the configured list of characters that should be treated as additional word separators.
     *
     * @return string[]
     */
    public static function getAdditionalWordCharacters(): array
    {
        $configured = SearchHelper::getExtConf()['additionalWordCharacters'] ?? '';

        return $configured === '' ? [] : str_split($configured);
    }

    /**
     * Creates additional hidden index content for words containing configured separator characters.
     *
     * This keeps backwards-compatible, partially encoded variants and adds the fully encoded form
     * needed for multi-character tokens such as email addresses.
     *
     * @param string $content
     * @return string
     */
    public static function getAdditionalContent(string $content): string
    {
        $characters = self::getAdditionalWordCharacters();
        if ($characters === []) {
            return '';
        }

        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $variants = [];

        // Create per-character variants (for compatibility with existing behavior).
        foreach ($characters as $character) {
            foreach ($words as $word) {
                if (str_contains($word, $character)) {
                    $variants[] = self::replaceSingleAdditionalWordCharacter($word, $character);
                }
            }
        }

        // Add the fully encoded variant for words containing multiple configured separators.
        foreach ($words as $word) {
            if (strpbrk($word, implode('', $characters)) !== false) {
                $variants[] = self::replaceAdditionalWordCharacters($word);
            }
        }

        // Keep only unique, non-empty values.
        $variants = array_values(array_unique(array_filter(
            $variants,
            static fn(string $value): bool => $value !== ''
        )));

        return implode(' ', $variants);
    }

    /**
     * Replaces all configured additional word characters in a string with their encoded representation.
     *
     * @param string $content
     * @return string
     */
    public static function replaceAdditionalWordCharacters(string $content): string
    {
        foreach (self::getAdditionalWordCharacters() as $character) {
            $content = self::replaceSingleAdditionalWordCharacter($content, $character);
        }

        return $content;
    }

    /**
     * Replaces only one configured additional word character in a string with its encoded representation.
     *
     * @param string $content
     * @param string $character
     * @return string
     */
    public static function replaceSingleAdditionalWordCharacter(string $content, string $character): string
    {
        return str_replace(
            $character,
            self::getReplacementForAdditionalWordCharacter($character),
            $content
        );
    }

    /**
     * Returns the replacement token used for a configured separator character.
     *
     * Example: '.' becomes '___46___'.
     *
     * @param string $character
     * @return string
     */
    public static function getReplacementForAdditionalWordCharacter(string $character): string
    {
        return '___' . ord($character) . '___';
    }
}
