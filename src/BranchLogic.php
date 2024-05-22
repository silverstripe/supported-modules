<?php

namespace SilverStripe\SupportedModules;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use RuntimeException;
use stdClass;

final class BranchLogic
{
    /**
     * Get the major release line of Silverstripe CMS this branch belongs to.
     * @param array $repoMetaData Data from the MetaData class for the given repository
     * @param string $branch The branch to check for
     * @param stdClass|null $composerJsonContent The decoded composer.json file for the branch we're checking.
     * Used to check against dependencies if there's no hardcoded references for the repository in question.
     * @param bool $usePhpDepAsFallback If a CMS major release line can't be found, use the PHP dependency to determine
     * a likely CMS major release line.
     */
    public static function getCmsMajor(array $repoMetaData, string $branch, ?stdClass $composerJsonContent = null, bool $usePhpDepAsFallback = false): string
    {
        $cmsMajor = static::getCmsMajorFromBranch($repoMetaData, $branch);
        if ($cmsMajor == '' && $composerJsonContent !== null) {
            $cmsMajor = static::getCmsMajorFromComposerJson($composerJsonContent, $usePhpDepAsFallback);
        }
        return $cmsMajor;
    }

    /**
     * Get the branches that will be used for merging up commits.
     *
     * @param array $repoMetaData Data from the MetaData class for the given repository
     * @param string $defaultBranch The default branch on GitHub.
     * Used as a fallback when we don't have metadata about a particular repository.
     * @param array $repoTags A flat array of tags on the GitHub repository.
     * @param array $repoBranches A flat array of branches on the GitHub repository.
     * @param stdClass|null $composerJson The decoded composer.json file from the default branch.
     * Used as a fallback when we don't have metadata about a particular repository.
     *
     * @throws RuntimeException if a connection can't be found between the branch and CMS major version
     */
    public static function getBranchesForMergeUp(
        string $githubRepository,
        array $repoMetaData,
        string $defaultBranch,
        array $repoTags,
        array $repoBranches,
        ?stdClass $composerJson = null
    ): array {
        if (in_array($githubRepository, MetaData::SKIP_FOR_MERGE_UP)) {
            return [];
        }

        // filter out non-standard branches
        $repoBranches = array_filter($repoBranches, fn ($branch) => preg_match('#^[0-9]+\.?[0-9]*$#', $branch));

        // If there are no relevant branches for a repository, there's nothing to merge up.
        if (empty($repoBranches)) {
            return [];
        }

        // Make sure the input is in a consistent order to ensure the following logic always gets the same results
        // The order should be highest to lowest, so getMajorDiff correctly checks the highest branch first.
        usort($repoBranches, 'version_compare');
        $repoBranches = array_reverse($repoBranches);
        usort($repoTags, 'version_compare');
        $repoTags = array_reverse($repoTags);

        $onlyMajorBranches = array_filter($repoBranches, fn ($branch) => ctype_digit((string) $branch));
        $majorDiff = static::getMajorDiff($repoMetaData, $onlyMajorBranches, $defaultBranch, $composerJson);

        $minorsWithStableTags = [];
        foreach ($repoTags as $tag) {
            if (!preg_match('#^([0-9]+)\.([0-9]+)\.([0-9]+)$#', $tag, $matches)) {
                continue;
            }
            $major = $matches[1];
            $minor = $major. '.' . $matches[2];
            $minorsWithStableTags[$major][$minor] = true;
        }

        $branches = [];
        foreach ($repoBranches as $branch) {
            // filter out majors that are too old - try getting the metadata CMS version first,
            // since some repos have multiple branches for a given CMS major release line.
            $cmsMajor = BranchLogic::getCmsMajor($repoMetaData, $branch);
            if (!$cmsMajor) {
                preg_match('#^([0-9]+)\.?[0-9]*$#', $branch, $matches);
                $cmsMajor = $matches[1] + $majorDiff;
            }
            if ($cmsMajor < MetaData::LOWEST_SUPPORTED_CMS_MAJOR) {
                continue;
            }
            // suffix a temporary .999 minor version to major branches so that it's sorted correctly later
            if (preg_match('#^[0-9]+$#', $branch)) {
                $branch .= '.999';
            }
            $branches[] = $branch;
        }

        // sort so that newest is first
        usort($branches, 'version_compare');
        $branches = array_reverse($branches);

        // remove the temporary .999
        array_walk($branches, function(&$branch) {
            $branch = preg_replace('#\.999$#', '', $branch);
        });

        // remove all branches except:
        // - the latest major branch in each release line
        // - the latest minor branch with a stable tag in each release line
        // - any minor branches without stable tags with a higher minor version than the latest minor with a stable tag
        $foundMinorInMajor = [];
        $foundMinorBranchWithStableTag = [];
        foreach ($branches as $i => $branch) {
            // only remove minor branches, leave major branches in
            if (!preg_match('#^([0-9]+)\.[0-9]+$#', $branch, $matches)) {
                continue;
            }
            $major = $matches[1];
            if (isset($foundMinorBranchWithStableTag[$major]) && isset($foundMinorInMajor[$major])) {
                unset($branches[$i]);
                continue;
            }
            if (isset($minorsWithStableTags[$major][$branch])) {
                $foundMinorBranchWithStableTag[$major] = true;
            }
            $foundMinorInMajor[$major] = true;
        }

        // remove any branches less than or equal to DO_NOT_MERGE_UP_FROM_MAJOR
        if (isset(MetaData::DO_NOT_MERGE_UP_FROM_MAJOR[$githubRepository])) {
            $doNotMergeUpFromMajor = MetaData::DO_NOT_MERGE_UP_FROM_MAJOR[$githubRepository];
            $branches = array_filter($branches, function($branch) use ($doNotMergeUpFromMajor) {
                return version_compare($branch, "$doNotMergeUpFromMajor.999999.999999", '>');
            });
        }

        // reverse the array so that oldest is first
        return array_reverse($branches);
    }

    private static function getCmsMajorFromBranch(array $repoMetaData, string $branch): string
    {
        $branchMajor = '';
        if (preg_match('#^[1-9]+$#', $branch)) {
            $branchMajor = $branch;
        } elseif (preg_match('#^([1-9]+)\.[0-9]+$#', $branch, $matches)) {
            $branchMajor = $matches[1];
        }
        foreach ($repoMetaData['majorVersionMapping'] ?? [] as $cmsMajor => $repoBranches) {
            if (is_numeric($cmsMajor) && in_array($branchMajor, $repoBranches)) {
                return $cmsMajor;
            }
        }
        return '';
    }

    private static function getCmsMajorFromComposerJson(stdClass $composerJsonContent, bool $usePhpDepAsFallback): string
    {
        foreach (MetaData::getAllRepositoryMetaData() as $categoryData) {
            foreach ($categoryData as $repoData) {
                $composerName = $repoData['packagist'] ?? null;
                if ($composerName === null || !isset($composerJsonContent->require->$composerName)) {
                    continue;
                }
                $parser = new VersionParser();
                $constraint = $parser->parseConstraints($composerJsonContent->require->$composerName);
                $boundedVersion = explode('.', $constraint->getLowerBound()->getVersion());
                $composerVersionMajor = $boundedVersion[0];
                // If it's a non-numeric branch constraint or something unstable, don't use it
                if ($composerVersionMajor === 0) {
                    continue;
                }
                foreach ($repoData['majorVersionMapping'] as $cmsMajor => $repoBranches) {
                    if (is_numeric($cmsMajor) && in_array($composerVersionMajor, $repoBranches)) {
                        return $cmsMajor;
                    }
                }
            }
        }
        // Fall back on PHP dependency if that's an option
        if ($usePhpDepAsFallback && isset($composerJsonContent->require->php)) {
            // Loop through in ascending order - the first result that matches is returned.
            foreach (MetaData::PHP_VERSIONS_FOR_CMS_RELEASES as $cmsRelease => $phpVersions) {
                // Ignore anything that's not a major release
                if (!ctype_digit((string) $cmsRelease)) {
                    continue;
                }
                // Only look at the lowest-compatible PHP version of each major release,
                // since there's some overlap between major releases
                if (Semver::satisfies($phpVersions[0], $composerJsonContent->require->php)) {
                    return $cmsRelease;
                }
            }
        }
        return '';
    }

    /**
     * Get the difference between the branch major and the CMS release major, e.g for silverstripe/admin CMS 5 => 5 - 2 = 3
     */
    private static function getMajorDiff(array $repoMetaData, array $onlyMajorBranches, string $defaultBranch, ?stdClass $composerJson): int
    {
        // work out default major
        if (preg_match('#^([0-9]+)+\.?[0-9]*$#', $defaultBranch, $matches)) {
            $defaultMajor = $matches[1];
            if (!in_array($defaultMajor, $onlyMajorBranches)) {
                // Add default major to the end of the list, so it's checked last
                $onlyMajorBranches[] = $defaultMajor;
            }
        }

        // Try to get diff from branch if we can
        foreach ($onlyMajorBranches as $branch) {
            $cmsMajor = (int) static::getCmsMajorFromBranch($repoMetaData, $branch);
            if ($cmsMajor) {
                return $cmsMajor - $branch;
            }
        }

        if ($composerJson !== null && isset($defaultMajor)) {
            $cmsMajor = (int) static::getCmsMajorFromComposerJson($composerJson, true);
            if ($cmsMajor) {
                return $cmsMajor - $defaultMajor;
            }
        }

        // This is likely a maintenance-based respository such as silverstripe/eslint-config or silverstripe/gha-auto-tag
        // Just treat them as though they're on the highest stable version.
        if (isset($defaultMajor) && ($composerJson === null || array_key_exists('*', $repoMetaData['majorVersionMapping'] ?? []))) {
            return MetaData::HIGHEST_STABLE_CMS_MAJOR - $defaultMajor;
        }

        $repoName = static::getModuleName($repoMetaData, $composerJson) ?: 'this module';
        throw new RuntimeException("Could not work out what default CMS major version for $repoName");
    }

    private static function getModuleName(array $repoMetaData, ?stdClass $composerJson): string
    {
        if ($composerJson !== null && isset($composerJson->name)) {
            return $composerJson->name;
        }
        if (isset($repoMetaData['packagist'])) {
            return $repoMetaData['packagist'];
        }
        if (isset($repoMetaData['github'])) {
            return $repoMetaData['github'];
        }
        return '';
    }
}
