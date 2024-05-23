<?php

namespace SilverStripe\SupportedModules\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SilverStripe\SupportedModules\BranchLogic;
use SilverStripe\SupportedModules\MetaData;
use stdClass;

class BranchLogicTest extends TestCase
{
    public function provideGetCmsMajor(): array
    {
        return [
            'empty' => [
                'githubRepository' => 'some/module',
                'branch' => '',
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => '',
            ],
            'PR branch not used to find data' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => 'pulls/5/mybugfix',
                'composerJson' => null,
                'usePhpDepAsFallback' => true,
                'expected' => '',
            ],
            'lockstepped with matching major' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => '5',
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'lockstepped with matching major, use minor branch' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => '5.2',
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'lockstepped with different major' => [
                'githubRepository' => 'silverstripe/silverstripe-admin',
                'branch' => '3',
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => '6',
            ],
            'non-lockstepped' => [
                'githubRepository' => 'silverstripe/silverstripe-tagfield',
                'branch' => '3.9',
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'non-module repo' => [
                'githubRepository' => 'silverstripe/webpack-config',
                'branch' => '3',
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => '6',
            ],
            'n.x-dev constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => '5.x-dev'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'n.m.x-dev constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => '5.0.x-dev'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            '^n constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => '^5'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'x.y.z constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => '5.1.2'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'result is actual cms major, not just the dep major' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/admin' => '^2'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'If branch matches, composerjson is ignored' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => '5.2',
                'composerJson' => [
                    'require' => ['silverstripe/admin' => '1.x-dev'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'composerjson used even for known modules if needed' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => 'main',
                'composerJson' => [
                    'require' => ['silverstripe/admin' => '1.x-dev'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '4',
            ],
            'composer plugins are valid deps' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/vendor-plugin' => '^1'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '4',
            ],
            'branch is ignored when we lack metadata' => [
                'githubRepository' => 'some/module',
                'branch' => '3',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => '^5'],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '5',
            ],
            'framework takes presedence over composer plugins' => [
                'githubRepository' => 'some/module',
                'branch' => '3',
                'composerJson' => [
                    'require' => [
                        'silverstripe/vendor-plugin' => '^1',
                        'silverstripe/recipe-plugin' => '^1',
                        'silverstripe/framework' => '^6'
                    ],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '6',
            ],
            'PHP only used if explicitly asked for' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^8.1',
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '',
            ],
            'PHP matches minimum allowed cms4' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^7.4',
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => true,
                'expected' => '4',
            ],
            'PHP matches minimum allowed cms5' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^8.1',
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => true,
                'expected' => '5',
            ],
            'PHP doesnt have to be exactly the same as installer constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^8.0',
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => true,
                'expected' => '5',
            ],
            'tried everything, no match' => [
                'githubRepository' => 'some/module',
                'branch' => '3',
                'composerJson' => [
                    'require' => [
                        'php' => '^5.4',
                        'silverstripe/framework' => '^2',
                    ],
                ],
                'usePhpDepAsFallback' => true,
                'expected' => '',
            ],
        ];
    }

    /**
     * @dataProvider provideGetCmsMajor
     */
    public function testGetCmsMajor(
        string $githubRepository,
        string $branch,
        ?array $composerJson,
        bool $usePhpDepAsFallback,
        string $expected
    ): void {
        if (is_array($composerJson)) {
            // Convert array json into stdClass
            $composerJson = json_decode(json_encode($composerJson));
        }
        $repoMetaData = MetaData::getMetaDataForRepository($githubRepository);
        $cmsMajor = BranchLogic::getCmsMajor($repoMetaData, $branch, $composerJson, $usePhpDepAsFallback);
        $this->assertSame($expected, $cmsMajor);
    }

    public function provideGetBranchesForMergeUp(): array
    {
        return [
            'no branches' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5',
                'repoTags' => [
                    '5.1.0-beta1',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11',
                    '3.7.4'
                ],
                'repoBranches' => [],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^5.0'
                    ]
                ],
                'expected' => [],
            ],
            'no tags' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5',
                'repoTags' => [],
                'repoBranches' => [
                    '3',
                    '3.6',
                    '3.7',
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1',
                    '6'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^5.0'
                    ]
                ],
                // Note that this would result in an exception in the merge-ups action itself.
                'expected' => ['4.10', '4.11', '4.12', '4.13', '4', '5.0', '5.1', '5', '6'],
            ],
            '5.1.0-beta1, CMS 6 branch detected on silverstripe/framework' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5',
                'repoTags' => [
                    '5.1.0-beta1',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11',
                    '3.7.4'
                ],
                'repoBranches' => [
                    '3',
                    '3.6',
                    '3.7',
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1',
                    '6'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^5.0'
                    ]
                ],
                'expected' => ['4.13', '4', '5.0', '5.1', '5', '6'],
            ],
            '5.1.0 stable and match on silverstripe/cms' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5',
                'repoTags' => [
                    '5.1.0',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11',
                    '3.7.4'
                ],
                'repoBranches' => [
                    '3',
                    '3.6',
                    '3.7',
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/cms' => '^5.1'
                    ]
                ],
                'expected' => ['4.13', '4', '5.1', '5'],
            ],
            'match on silverstripe/assets' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5',
                'repoTags' => [
                    '5.1.0',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11'
                ],
                'repoBranches' => [
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/assets' => '^2.0'
                    ]
                ],
                'expected' => ['4.13', '4', '5.1', '5'],
            ],
            'match on silverstripe/mfa' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5',
                'repoTags' => [
                    '5.1.0',
                    '5.0.9',
                    '4.13.11'
                ],
                'repoBranches' => [
                    '4',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/mfa' => '^5.0'
                    ]
                ],
                'expected' => ['4.13', '4', '5.1', '5'],
            ],
            'Missing `1` branch and match on php version in composer.json' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '2',
                'repoTags' => [
                    '2.1.0-beta1',
                    '2.0.9',
                    '1.13.11',
                    '1.12.11',
                    '1.11.11',
                    '1.10.11'
                ],
                'repoBranches' => [
                    '1.10',
                    '1.11',
                    '1.12',
                    '1.13',
                    '2',
                    '2.0',
                    '2.1'
                ],
                'composerJson' => [
                    'require' => [
                        'php' => '^8.1'
                    ]
                ],
                'expected' => ['1.13', '2.0', '2.1', '2'],
            ],
            'Two minor branches without stable tags in composer.json' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '2',
                'repoTags' => [
                    '2.3.0-alpha1',
                    '2.2.0-beta1',
                    '2.1.0',
                    '2.0.9',
                    '1.13.11'
                ],
                'repoBranches' => [
                    '2',
                    '2.0',
                    '2.1',
                    '2.2',
                    '2.3',
                    '1',
                    '1.13'
                ],
                'composerJson' => [
                    'require' => [
                        'php' => '^8.1'
                    ]
                ],
                'expected' => ['1.13', '1', '2.1', '2.2', '2.3', '2'],
            ],
            'Module where default branch has not been changed from CMS 4 and there is a new CMS 6 branch' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5', // this repo has a '5' branch for CMS 4 and a '6' branch for CMS 5
                'repoTags' => [
                    '6.0.0',
                    '5.9.1',
                    '4.0.1'
                ],
                'repoBranches' => [
                    '7',
                    '6',
                    '6.0',
                    '5',
                    '5.9',
                    '5.8',
                    '5.7'
                ],
                'composerJson' => [
                    'require' => [
                        'php' => '^7.4',
                        'silverstripe/framework' => '^4.11'
                    ]
                ],
                'expected' => ['5.9', '5', '6.0', '6', '7'],
            ],
            'developer-docs' => [
                'githubRepository' => 'silverstripe/developer-docs',
                'defaultBranch' => '5',
                'repoTags' => [
                    '4.13.0',
                    '5.0.0'
                ],
                'repoBranches' => [
                    '5',
                    '5.0',
                    '4.13',
                    '4.12',
                    '4',
                    '3'
                ],
                'composerJson' => [
                    'no-require' => new stdClass(),
                ],
                'expected' => ['4.13', '4', '5.0', '5'],
            ],
            'More than 6 branches is fine' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '5',
                'repoTags' => [
                    '5.2.0-beta1',
                    '5.1.0-beta1',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11',
                    '3.7.4'
                ],
                'repoBranches' => [
                    '3',
                    '3.6',
                    '3.7',
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1',
                    '5.2',
                    '6'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^5.0'
                    ]
                ],
                'expected' => ['4.13', '4', '5.0', '5.1', '5.2', '5', '6'],
            ],
            'cwp-watea-theme' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => '4',
                'repoTags' => [
                    '4.0.0',
                    '5.0.9',
                    '3.2.0',
                    '3.1.0',
                    '3.0.0'
                ],
                'repoBranches' => [
                    '1',
                    '1.0',
                    '2',
                    '2.0',
                    '3',
                    '3.0',
                    '3.1',
                    '3.2',
                    '4',
                    '4.0'
                ],
                'composerJson' => [
                    'require' => [
                        'cwp/starter-theme' => '^4'
                    ]
                ],
                'expected' => ['3.2', '3', '4.0', '4'],
            ],
            'gha-ci' => [
                'githubRepository' => 'silverstripe/gha-ci',
                'defaultBranch' => '1',
                'repoTags' => [
                    '1.4.0',
                    '1.3.0',
                    '1.2.0',
                    '1.1.0',
                    '1.0.0'
                ],
                'repoBranches' => [
                    '1',
                    '1.0',
                    '1.1',
                    '1.2',
                    '1.3',
                    '1.4'
                ],
                'composerJson' => null,
                'expected' => ['1.4', '1'],
            ],
            'gha-generate-matrix with composerjson' => [
                'githubRepository' => 'silverstripe/gha-generate-matrix',
                'defaultBranch' => '1',
                'repoTags' => [
                    '1.4.0',
                    '1.3.0',
                    '1.2.0',
                    '1.1.0',
                    '1.0.0'
                ],
                'repoBranches' => [
                    '1',
                    '1.0',
                    '1.1',
                    '1.2',
                    '1.3',
                    '1.4'
                ],
                'composerJson' => [
                    'require' => [
                        'something/random' => '^4'
                    ]
                ],
                'expected' => ['1.4', '1'],
            ],
            'silverstripe-linkfield beta' => [
                'githubRepository' => 'silverstripe/silverstripe-linkfield',
                'defaultBranch' => '4',
                'repoTags' => [
                    '3.0.0-beta1',
                    '2.0.0',
                    '1.0.0'
                ],
                'repoBranches' => [
                    '1',
                    '2',
                    '3',
                    '4',
                    '4.0',
                    '5'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^5'
                    ]
                ],
                'expected' => ['4.0', '4', '5'],
            ],
            'silverstripe-linkfield stable' => [
                'githubRepository' => 'silverstripe/silverstripe-linkfield',
                'defaultBranch' => '4',
                'repoTags' => [
                    '4.0.0',
                    '3.0.0',
                    '2.0.0',
                    '1.0.0'
                ],
                'repoBranches' => [
                    '1',
                    '2',
                    '3',
                    '3.0',
                    '3.1',
                    '3.999',
                    '4',
                    '4.0',
                    '5'
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^5'
                    ]
                ],
                'expected' => ['4.0', '4', '5'],
            ],
            'Incorrect default branch for supported module' => [
                'githubRepository' => 'silverstripe/silverstripe-cms',
                'defaultBranch' => 'main',
                'repoTags' => [
                    '5.1.0',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11',
                    '3.7.4'
                ],
                'repoBranches' => [
                    '3',
                    '3.6',
                    '3.7',
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1'
                ],
                'composerJson' => null,
                'expected' => ['4.13', '4', '5.1', '5'],
            ],
            'Incorrect default branch for supported module' => [
                'githubRepository' => 'silverstripe/silverstripe-cms',
                'defaultBranch' => 'main',
                'repoTags' => [
                    '5.1.0',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11',
                    '3.7.4'
                ],
                'repoBranches' => [
                    '3',
                    '3.6',
                    '3.7',
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1'
                ],
                'composerJson' => null,
                'expected' => ['4.13', '4', '5.1', '5'],
            ],
            'Fluent, which had weird gaps in its support' => [
                'githubRepository' => 'tractorcow-farm/silverstripe-fluent',
                'defaultBranch' => '7',
                'repoTags' => [
                    '7.0.1',
                    '7.1.0',
                    '6.0.5',
                    '5.0.4',
                    '5.1.21',
                    '4.7.4',
                    '4.8.6',
                ],
                'repoBranches' => [
                    '8',
                    '7',
                    '7.0',
                    '7.1',
                    '6',
                    '6.0',
                    '5',
                    '5.0',
                    '5.1',
                    '4',
                    '4.7',
                    '4.8',
                ],
                'composerJson' => null,
                'expected' => ['6.0', '6', '7.1', '7', '8'],
            ],
            'Fluent with branches in the reverse order, which used to fail' => [
                'githubRepository' => 'tractorcow-farm/silverstripe-fluent',
                'defaultBranch' => '7',
                'repoTags' => [
                    '4.8.6',
                    '4.7.4',
                    '5.1.21',
                    '5.0.4',
                    '6.0.5',
                    '7.1.0',
                    '7.0.1',
                ],
                'repoBranches' => [
                    '4.8',
                    '4.7',
                    '4',
                    '5.1',
                    '5.0',
                    '5',
                    '6.0',
                    '6',
                    '7.1',
                    '7.0',
                    '7',
                    '8',
                ],
                'composerJson' => null,
                'expected' => ['6.0', '6', '7.1', '7', '8'],
            ],
        ];
    }

    /**
     * @dataProvider provideGetBranchesForMergeUp
     */
    public function testGetBranchesForMergeUp(
        string $githubRepository,
        string $defaultBranch,
        array $repoTags,
        array $repoBranches,
        ?array $composerJson,
        array $expected
    ): void {
        $repoMetaData = MetaData::getMetaDataForRepository($githubRepository);
        if (is_array($composerJson)) {
            // Convert array json into stdClass
            $composerJson = json_decode(json_encode($composerJson));
        }
        $branches = BranchLogic::getBranchesForMergeUp(
            $githubRepository,
            $repoMetaData,
            $defaultBranch,
            $repoTags,
            $repoBranches,
            $composerJson
        );
        $this->assertSame($expected, $branches);
    }

    public function provideGetBranchesForMergeUpExceptions(): array
    {
        return [
            'Incorrect default branch for random module' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => 'main',
                'repoTags' => [
                    '5.1.0',
                    '5.0.9',
                    '4.13.11',
                    '4.12.11',
                    '4.11.11',
                    '4.10.11',
                    '3.7.4'
                ],
                'repoBranches' => [
                    '3',
                    '3.6',
                    '3.7',
                    '4',
                    '4.10',
                    '4.11',
                    '4.12',
                    '4.13',
                    '5',
                    '5.0',
                    '5.1'
                ],
                // Even though we know what CMS major to use, because the default branch
                // is incorrect we can't get a good mapping for the rest of the branches
                'composerJson' => [
                    'require' => [
                        'silverstripe/cms' => '^5.1',
                    ]
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideGetBranchesForMergeUpExceptions
     */
    public function testGetBranchesForMergeUpExceptions(
        string $githubRepository,
        string $defaultBranch,
        array $repoTags,
        array $repoBranches,
        ?array $composerJson = null,
    ): void {
        $repoMetaData = MetaData::getMetaDataForRepository($githubRepository);
        if (is_array($composerJson)) {
            // Convert array json into stdClass
            $composerJson = json_decode(json_encode($composerJson));
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not work out what default CMS major version for this module");

        BranchLogic::getBranchesForMergeUp(
            $githubRepository,
            $repoMetaData,
            $defaultBranch,
            $repoTags,
            $repoBranches,
            $composerJson
        );
    }
}
