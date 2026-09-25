<?php

namespace Tpwd\KeSearch\Service;

use Tpwd\KeSearch\Domain\Repository\CategoryRepository;
use Tpwd\KeSearch\Domain\Repository\FilterOptionRepository;
use Tpwd\KeSearch\Domain\Repository\GenericRepository;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Utility\FilterOptionUtility;

/**
 * Resolves the tags assigned to a search result to their "speaking" names, so that they
 * can be shown in the search result fluid template.
 *
 * A tag can derive from
 * - a filter option (the title of the filter option is used, multiple filter options with
 *   the same tag are all returned)
 * - a system category (the title of the category and its parent categories are used)
 * - a custom indexer which just sets a tag without connecting it to a filter option or a
 *   system category (in this case no speaking name can be resolved)
 */
class TagInfoService
{
    public function __construct(
        protected FilterOptionRepository $filterOptionRepository,
        protected CategoryRepository $categoryRepository,
        protected GenericRepository $genericRepository,
    ) {}

    /**
     * Returns an array with detailed information about the given tags (e.g. the titles of
     * assigned filter options and/or the title and parent categories of a system category)
     * to make the "speaking name" of a tag available in the fluid template.
     *
     * @param string $tags Comma separated list of tags as stored in tx_kesearch_index (e.g. "#colorblue#,#syscat123#")
     * @param int $languageId The uid of the current frontend language
     * @return array<int, array{tag: string, filterOptions: array<int, array{uid: int, title: string}>, category: array{uid: int, title: string, parents: array<int, array{uid: int, title: string}>}|null}>
     */
    public function getTagsInfo(string $tags, int $languageId): array
    {
        $tagsInfo = [];
        $plainTags = FilterOptionUtility::getPlainTagsFromIndexRecordTags($tags);

        foreach ($plainTags as $plainTag) {
            $tagsInfo[] = [
                'tag' => $plainTag,
                'filterOptions' => $this->getFilterOptionsForTag($plainTag, $languageId),
                'category' => $this->getCategoryForTag($plainTag, $languageId),
            ];
        }

        return $tagsInfo;
    }

    /**
     * Returns the titles of all filter options which are assigned to the given tag,
     * respecting the current frontend language. Every default language filter option
     * assigned to the tag is resolved on its own, falling back to its default language
     * title if no translation exists for that particular filter option (other filter
     * options sharing the same tag might well have a translation).
     *
     * @return array<int, array{uid: int, title: string}>
     */
    protected function getFilterOptionsForTag(string $tag, int $languageId): array
    {
        // always start from the default language filter options assigned to the tag,
        // so that every matching filter option is taken into account, even if only
        // some of them have a translation for the requested language
        $filterOptions = $this->filterOptionRepository->findByTagAndLanguage($tag, 0);

        $result = [];
        foreach ($filterOptions as $filterOption) {
            $title = $filterOption['title'];
            if ($languageId > 0) {
                $overlay = $this->genericRepository->findLangaugeOverlayByUidAndLanguage(
                    'tx_kesearch_filteroptions',
                    (int)$filterOption['uid'],
                    $languageId
                );
                if (is_array($overlay)) {
                    $title = $overlay['title'];
                }
            }
            $result[] = [
                'uid' => (int)$filterOption['uid'],
                'title' => $title,
            ];
        }
        return $result;
    }

    /**
     * If the given tag refers to a system category (e.g. "syscat123"), this returns the
     * title of the category as well as the titles of all its parent categories (ordered
     * from the direct parent up to the root category).
     *
     * @return array{uid: int, title: string, parents: array<int, array{uid: int, title: string}>}|null
     */
    protected function getCategoryForTag(string $tag, int $languageId): ?array
    {
        if (!str_starts_with($tag, SearchHelper::$systemCategoryPrefix)) {
            return null;
        }

        $categoryUid = (int)str_replace(SearchHelper::$systemCategoryPrefix, '', $tag);
        $category = $this->getCategoryWithLanguageOverlay($categoryUid, $languageId);
        if (!is_array($category)) {
            return null;
        }

        $parents = [];
        $parentUid = (int)($category['parent'] ?? 0);
        $visitedUids = [$categoryUid];
        // Nested categories: walk up the "parent" chain until we reach the root category.
        // The $visitedUids guard prevents infinite loops in case of misconfigured/circular categories.
        while ($parentUid > 0 && !in_array($parentUid, $visitedUids, true)) {
            $visitedUids[] = $parentUid;
            $parentCategory = $this->getCategoryWithLanguageOverlay($parentUid, $languageId);
            if (!is_array($parentCategory)) {
                break;
            }
            $parents[] = [
                'uid' => (int)$parentCategory['uid'],
                'title' => $parentCategory['title'],
            ];
            $parentUid = (int)($parentCategory['parent'] ?? 0);
        }

        return [
            'uid' => (int)$category['uid'],
            'title' => $category['title'],
            'parents' => $parents,
        ];
    }

    /**
     * Fetches a system category and applies the language overlay (respecting translations),
     * falling back to the default language record if no translation exists.
     */
    protected function getCategoryWithLanguageOverlay(int $uid, int $languageId): ?array
    {
        $category = $this->categoryRepository->findByUid($uid);
        if (!is_array($category)) {
            return null;
        }

        if ($languageId > 0) {
            $overlay = $this->genericRepository->findLangaugeOverlayByUidAndLanguage('sys_category', $uid, $languageId);
            if (is_array($overlay)) {
                $category['title'] = $overlay['title'];
            }
        }

        return $category;
    }
}
