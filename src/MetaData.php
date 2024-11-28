<?php

namespace SilverStripe\SupportedModules;

use RuntimeException;

final class MetaData
{
    public static $isRunningUnitTests = false;

    public const CATEGORY_SUPPORTED = 'supportedModules';

    public const CATEGORY_WORKFLOW = 'workflow';

    public const CATEGORY_TOOLING = 'tooling';

    public const CATEGORY_MISC = 'misc';

    /**
     * Lowest major release line of Silverstripe CMS which is currently supported.
     * Must be updated after a major release line goes EOL.
     */
    public const LOWEST_SUPPORTED_CMS_MAJOR = '4';

    /**
     * Highest major release line of Silverstripe CMS which is currently stable and supported.
     * Must be updated after a major release line gets its stable release.
     */
    public const HIGHEST_STABLE_CMS_MAJOR = '5';

    /**
     * PHP versions which are suported for each release of Silverstripe CMS.
     * Must be updated after each Silverstripe CMS beta release.
     */
    public const PHP_VERSIONS_FOR_CMS_RELEASES = [
        '4.9' => ['7.1', '7.2', '7.3', '7.4'],
        '4.10' => ['7.3', '7.4', '8.0'],
        '4.11' => ['7.4', '8.0', '8.1'],
        '4' => ['7.4', '8.0', '8.1'],
        '5.0' => ['8.1', '8.2'],
        '5.1' => ['8.1', '8.2'],
        '5.2' => ['8.1', '8.2', '8.3'],
        '5.3' => ['8.1', '8.2', '8.3'],
        '5' => ['8.1', '8.2', '8.3'],
        '6' => ['8.3', '8.4'],
    ];

    /**
     * List of major branches to not merge up from
     *
     * Add repos in here where the repo was previously unsupported, where the repo has
     * had gaps in its support history, or where we have had multiple supported modules
     * for a given major release and want to omit one of those for merge-up purposes.
     *
     * Note these are actual major branches, not CMS major versions
     */
    public const DO_NOT_MERGE_UP_FROM_MAJOR = [
        'bringyourownideas/silverstripe-composer-update-checker' => '2',
        'silverstripe/silverstripe-graphql' => '3',
        'silverstripe/silverstripe-linkfield' => '3',
        'tractorcow-farm/silverstripe-fluent' => '4',
    ];

    /**
     * List of repositories that should be outright skipped for merge-up purposes.
     * Only list them if they're causing errors in the existing logic.
     */
    public const SKIP_FOR_MERGE_UP = [
        // cow only uses a master branch
        'silverstripe/cow',
        // silverstripe/supported-modules only uses the main branch
        'silverstripe/supported-modules',
        // demo.silverstripe.org on uses the master branch
        'silverstripe/demo.silverstripe.org',
        // sortablegridfield has master branch as default branch which has dual support for CMS 4 and 5
        'undefinedoffset/sortablegridfield',
    ];

    private static array $repositoryMetaData = [];

    /**
     * Get metadata for a given repository, if we have any.
     *
     * @param string $gitHubReference The full GitHub reference for the repository
     * e.g. `silverstripe/silverstripe-framework`.
     * @param boolean $allowPartialMatch If no data is found for the full repository reference,
     * check for repositories with the same name but a different organisation.
     */
    public static function getMetaDataForRepository(
        string $gitHubReference,
        bool $allowPartialMatch = false
    ): array {
        $parts = explode('/', $gitHubReference);
        if (count($parts) !== 2) {
            throw new RuntimeException('$gitHubReference must be a valid org/repo reference.');
        }
        $candidate = null;
        foreach (self::getAllRepositoryMetaData() as $categoryData) {
            foreach ($categoryData as $repoData) {
                // Get data for the current repository
                if ($repoData['github'] === $gitHubReference) {
                    // Exact match of org and repo name
                    return $repoData;
                } elseif ($parts[1] === explode('/', $repoData['github'])[1]) {
                    // Partial match - repo name only
                    $candidate = $repoData;
                }
            }
        }
        if ($allowPartialMatch && $candidate !== null) {
            return $candidate;
        }
        return [];
    }

    /**
     * Get metadata for a given repository based on the packagist name, if we have any.
     *
     * @param string $packagistName The full packagist reference for the repository
     * e.g. `silverstripe/framework`.
     */
    public static function getMetaDataByPackagistName(string $packagistName): array
    {
        if (!str_contains($packagistName, '/')) {
            throw new RuntimeException('$packagistName must be a valid org/repo reference.');
        }
        foreach (self::getAllRepositoryMetaData() as $categoryData) {
            foreach ($categoryData as $repoData) {
                // Get data for the packagist item
                if (isset($repoData['packagist']) && $repoData['packagist'] === $packagistName) {
                    // Exact match of org and repo name
                    return $repoData;
                }
            }
        }
        return [];
    }

    /**
     * Get metadata for repositories that are released in lock-step with Silverstripe CMS minor releases.
     */
    public static function getMetaDataForLocksteppedRepos(): array
    {
        $repos = [];
        foreach (self::getAllRepositoryMetaData() as $category => $categoryData) {
            // Skip anything that can't be lockstepped
            if ($category !== self::CATEGORY_SUPPORTED) {
                continue;
            }
            // Find lockstepped repos
            foreach ($categoryData as $repoData) {
                if (isset($repoData['lockstepped']) && $repoData['lockstepped'] && !empty($repoData['packagist'])) {
                    $repos[$repoData['packagist']] = $repoData['majorVersionMapping'];
                }
            }
        }
        return $repos;
    }

    /**
     * Get metadata about all repositories we have information about,
     * but only if they're supported for the given CMS major version.
     *
     * @param array $metadata Flat array of repository metadata, e.g. from getAllRepositoryMetaData(false)
     * @param bool $keepWildcardMap If true, repositories with a "*" CMS major mapping are kept in the output
     */
    public static function removeReposNotInCmsMajor(array $metadata, string|int $cmsMajor, bool $keepWildcardMap = false): array
    {
        foreach ($metadata as $i => $repo) {
            if (
                !array_key_exists($cmsMajor, $repo['majorVersionMapping']) &&
                (!$keepWildcardMap || !array_key_exists('*', $repo['majorVersionMapping']))
            ) {
                unset($metadata[$i]);
            }
        }
        // Use array_values to reset array indices
        return array_values($metadata);
    }

    /**
     * Get all metadata about all repositories we have information about. This will make a newtwork
     * request to fetch the latest data from the supported-modules repository.
     * @param bool $categorised If true, output is grouped by category.
     * @param bool $fromRemote If true, fetch the data from the remote repository.
     *                         If false, use the local file.
     */
    public static function getAllRepositoryMetaData(bool $categorised = true, $fromRemote = true): array
    {
        if (empty(self::$repositoryMetaData)) {
            if (self::$isRunningUnitTests || !$fromRemote) {
                $rawJson = file_get_contents(__DIR__ . '/../repositories.json');
            } else {
                // Dynamicallly fetch the latest data from the supported-modules repository
                // rather than reading it locally. This is done do that the data is always up-to-date
                // and there's no need to run composer update and deploy the code to get the latest data.
                $rawJson = file_get_contents('https://raw.githubusercontent.com/silverstripe/supported-modules/main/repositories.json');
                if (!$rawJson) {
                    throw new RuntimeException('Could not fetch repositories.json data');
                }
            }
            $decodedJson = json_decode($rawJson, true);
            if ($decodedJson === null) {
                throw new RuntimeException('Could not parse repositories.json data: ' . json_last_error_msg());
            }
            self::$repositoryMetaData = $decodedJson;
        }
        if ($categorised) {
            return self::$repositoryMetaData;
        }
        return array_merge(...array_values(self::$repositoryMetaData));
    }
}
