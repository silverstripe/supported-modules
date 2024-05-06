<?php

namespace SilverStripe\SupportedModules\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SilverStripe\SupportedModules\MetaData;

class MetaDataTest extends TestCase
{
    public function provideGetMetaDataForRepository(): array
    {
        return [
            'missing repo' => [
                'repoName' => 'org/repo',
                'allowPartialMatch' => true,
                'resultEmpty' => true,
            ],
            'packagist ref doesnt match github ref no partial' => [
                'repoName' => 'silverstripe/framework',
                'allowPartialMatch' => false,
                'resultEmpty' => true,
            ],
            'packagist ref doesnt match github ref with partial' => [
                'repoName' => 'silverstripe/framework',
                'allowPartialMatch' => true,
                'resultEmpty' => true,
            ],
            'exact match' => [
                'repoName' => 'silverstripe/silverstripe-framework',
                'allowPartialMatch' => false,
                'resultEmpty' => false,
            ],
            'fork mismatch' => [
                'repoName' => 'creative-commoners/silverstripe-framework',
                'allowPartialMatch' => false,
                'resultEmpty' => true,
            ],
            'fork match' => [
                'repoName' => 'creative-commoners/silverstripe-framework',
                'allowPartialMatch' => true,
                'resultEmpty' => false,
            ],
            'gha match' => [
                'repoName' => 'silverstripe/gha-generate-matrix',
                'allowPartialMatch' => false,
                'resultEmpty' => false,
            ],
        ];
    }

    /**
     * @dataProvider provideGetMetaDataForRepository
     */
    public function testGetMetaDataForRepository(string $repoName, bool $allowPartialMatch, bool $resultEmpty): void
    {
        $repoData = MetaData::getMetaDataForRepository($repoName, $allowPartialMatch);
        $this->assertSame($resultEmpty, empty($repoData));
    }

    public function testGetMetaDataForRepositoryInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('$gitHubReference must be a valid org/repo reference.');
        MetaData::getMetaDataForRepository('');
    }

    public function provideGetMetaDataByPackagistName(): array
    {
        return [
            'missing repo' => [
                'repoName' => 'org/repo',
                'resultEmpty' => true,
            ],
            'packagist ref doesnt match github ref no partial' => [
                'repoName' => 'silverstripe/silverstripe-framework',
                'resultEmpty' => true,
            ],
            'packagist ref doesnt match github ref with partial' => [
                'repoName' => 'silverstripe/silverstripe-framework',
                'resultEmpty' => true,
            ],
            'exact match' => [
                'repoName' => 'silverstripe/framework',
                'resultEmpty' => false,
            ],
        ];
    }

    /**
     * @dataProvider provideGetMetaDataByPackagistName
     */
    public function testGetMetaDataByPackagistName(string $repoName, bool $resultEmpty): void
    {
        $repoData = MetaData::getMetaDataByPackagistName($repoName);
        $this->assertSame($resultEmpty, empty($repoData));
    }

    public function testGetMetaDataByPackagistNameInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('$packagistName must be a valid org/repo reference.');
        MetaData::getMetaDataByPackagistName('');
    }

    public function provideGetMetaDataForLocksteppedRepos(): array
    {
        return [
            'module skeleton not lockstepped' => [
                'packagistRef' => 'silverstripe-module/skeleton',
                'isLockstepped' => false,
            ],
            'config not lockstepped' => [
                'packagistRef' => 'silverstripe/config',
                'isLockstepped' => false,
            ],
            'framework lockstepped' => [
                'packagistRef' => 'silverstripe/framework',
                'isLockstepped' => true,
            ],
            'kitchen sink lockstepped' => [
                'packagistRef' => 'silverstripe/recipe-kitchen-sink',
                'isLockstepped' => true,
            ],
        ];
    }

    /**
     * @dataProvider provideGetMetaDataForLocksteppedRepos
     */
    public function testGetMetaDataForLocksteppedRepos(string $repoName, bool $isLockstepped): void
    {
        $lockstepped = MetaData::getMetaDataForLocksteppedRepos();

        if ($isLockstepped) {
            $this->assertArrayHasKey($repoName, $lockstepped);
            $this->validateVersionMap($lockstepped[$repoName]);
        } else {
            $this->assertArrayNotHasKey($repoName, $lockstepped);
        }
    }

    public function testGetAllRepositoryMetaData(): void
    {
        // Validate data has correct categories
        $validKeys = [
            MetaData::CATEGORY_SUPPORTED => [
                'github',
                'packagist',
                'githubId',
                'isCore',
                'lockstepped',
                'type',
                'majorVersionMapping',
            ],
            MetaData::CATEGORY_WORKFLOW => [
                'github',
                'githubId',
                'majorVersionMapping',
            ],
            MetaData::CATEGORY_TOOLING => [
                'github',
                'packagist',
                'githubId',
                'majorVersionMapping',
            ],
            MetaData::CATEGORY_MISC => [
                'github',
                'packagist',
                'githubId',
                'majorVersionMapping',
            ],
        ];
        $data = MetaData::getAllRepositoryMetaData();
        $this->assertSame(array_keys($validKeys), array_keys($data));

        $githubRefs = [];
        $packagistRefs = [];
        $githubIds = [];
        // Validate data schema
        foreach ($data as $category => $repos) {
            $this->assertIsArray($repos);
            foreach ($repos as $repo) {
                $this->validateSchema($repo, $validKeys[$category]);
                if (isset($repo['github'])) {
                    $githubRefs[] = $repo['github'];
                }
                if (isset($repo['packagist'])) {
                    $packagistRefs[] = $repo['packagist'];
                }
                if (isset($repo['githubId'])) {
                    $githubIds[] = $repo['githubId'];
                }
            }
        }
        // Validate references are unique (no duplicated repositories)
        $this->assertSame(array_unique($githubRefs), $githubRefs, 'GitHub references must be unique');
        $this->assertSame(array_unique($packagistRefs), $packagistRefs, 'Packagist references must be unique');
        $this->assertSame(array_unique($githubIds), $githubIds, 'GitHub IDs must be unique');
    }

    private function validateSchema(array $repo, array $validKeys): void
    {
        $this->assertSame($validKeys, array_keys($repo));
        in_array('github', $validKeys) && $this->assertStringContainsString('/', $repo['github']);
        if (in_array('packagist', $validKeys)) {
            if (is_string($repo['packagist'])) {
                $this->assertStringContainsString('/', $repo['packagist']);
            } else {
                $this->assertNull($repo['packagist']);
            }
        }
        in_array('githubId', $validKeys) && $this->assertIsInt($repo['githubId']);
        in_array('isCore', $validKeys) && $this->assertIsBool($repo['isCore']);
        in_array('lockstepped', $validKeys) && $this->assertIsBool($repo['lockstepped']);
        in_array('type', $validKeys) && $this->assertContains($repo['type'], ['module', 'recipe', 'theme', 'other']);
        in_array('majorVersionMapping', $validKeys) && $this->validateVersionMap($repo['majorVersionMapping']);
    }

    private function validateVersionMap(array $versionMap): void
    {
        $this->assertNotEmpty($versionMap);
        foreach ($versionMap as $cmsMajor => $branches) {
            $this->assertIsArray($branches);
            if ($cmsMajor !== '*') {
                $this->assertTrue(ctype_digit((string)$cmsMajor));
                $this->assertNotEmpty($branches);
            }
        }
    }
}
