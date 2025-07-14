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
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
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
                'branch' => 'pulls/' . $highestMajor . '/mybugfix',
                'composerJson' => null,
                'usePhpDepAsFallback' => true,
                'expected' => '',
            ],
            'lockstepped with matching major' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => $highestMajor,
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'lockstepped with matching major, use minor branch' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => $this->getVersionWithOffset($highestMajor,  0, '2'),
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'lockstepped with different major' => [
                'githubRepository' => 'silverstripe/silverstripe-admin',
                'branch' => $this->getVersionWithOffset($highestMajor,  -3),
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'non-lockstepped' => [
                'githubRepository' => 'silverstripe/silverstripe-tagfield',
                'branch' => $this->getVersionWithOffset($highestMajor,  -2, '9'),
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'non-module repo' => [
                'githubRepository' => 'silverstripe/webpack-config',
                'branch' => $this->getVersionWithOffset($highestMajor,  -3),
                'composerJson' => null,
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'n.x-dev constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => $this->getVersionWithOffset($highestMajor,  0, 'x-dev')],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'n.m.x-dev constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => $this->getVersionWithOffset($highestMajor,  0, '0.x-dev')],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            '^n constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => '^' . $highestMajor],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'x.y.z constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/framework' => $this->getVersionWithOffset($highestMajor,  0, '1.2')],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'result is actual cms major, not just the dep major' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/admin' => '^' . $this->getVersionWithOffset($highestMajor,  -3)],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'If branch matches, composerjson is ignored' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => $this->getVersionWithOffset($highestMajor,  0, '2'),
                'composerJson' => [
                    'require' => ['silverstripe/admin' => $this->getVersionWithOffset($lowestMajor,  -3, 'x-dev')],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'composerjson used even for known modules if needed' => [
                'githubRepository' => 'silverstripe/silverstripe-framework',
                'branch' => 'main',
                'composerJson' => [
                    'require' => ['silverstripe/admin' => $this->getVersionWithOffset($lowestMajor,  -3, 'x-dev')],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $lowestMajor,
            ],
            'composer plugins are valid deps' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => ['silverstripe/vendor-plugin' => '^' . $this->getVersionWithOffset($lowestMajor,  -3)],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $lowestMajor,
            ],
            'branch is ignored when we lack metadata' => [
                'githubRepository' => 'some/module',
                'branch' => $this->getVersionWithOffset($lowestMajor,  -2),
                'composerJson' => [
                    'require' => ['silverstripe/framework' => '^' . $highestMajor],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'framework takes presedence over composer plugins' => [
                'githubRepository' => 'some/module',
                'branch' => '3',
                'composerJson' => [
                    'require' => [
                        'silverstripe/vendor-plugin' => '^1' . $this->getVersionWithOffset($lowestMajor,  0),
                        'silverstripe/recipe-plugin' => '^' . $this->getVersionWithOffset($lowestMajor,  0),
                        'silverstripe/framework' => '^' . $highestMajor,
                    ],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => $highestMajor,
            ],
            'PHP only used if explicitly asked for' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^' . MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor . '.0'][0],
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => false,
                'expected' => '',
            ],
            'PHP matches minimum allowed lowest major' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^' . MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor . '.0'][0],
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => true,
                'expected' => $lowestMajor,
            ],
            'PHP matches minimum allowed ihghest major' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^' . MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor . '.0'][0],
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => true,
                'expected' => $highestMajor,
            ],
            'PHP doesnt have to be exactly the same as installer constraint' => [
                'githubRepository' => 'some/module',
                'branch' => 'mybranch',
                'composerJson' => [
                    'require' => [
                        'php' => '^8',
                        'unknown/dependency' => '^1'
                    ],
                ],
                'usePhpDepAsFallback' => true,
                'expected' => $lowestMajor,
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

    private function getVersionWithOffset(string $baseMajor, int $offset, string|int $minor = ''): string
    {
        $version = (string)($baseMajor + $offset);
        if ($minor !== '') {
            $version .= ".$minor";
        }
        return $version;
    }

    public function provideGetBranchesForMergeUp(): array
    {
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestPHPVersion = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor][0];
        $lowestPHPVersion = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[array_key_first(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)][0];
        return [
            'no branches' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '1.0-beta1'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '11.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '10.11'),
                    $this->getVersionWithOffset($highestMajor, -2, '7.4'),
                ],
                'repoBranches' => [],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^' . $this->getVersionWithOffset($highestMajor, 0, '0'),
                    ]
                ],
                'expected' => [],
            ],
            'no tags' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $highestMajor,
                'repoTags' => [],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -2),
                    $this->getVersionWithOffset($highestMajor, -2, '6'),
                    $this->getVersionWithOffset($highestMajor, -2, '7'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^' . $this->getVersionWithOffset($highestMajor, 0, '0'),
                    ]
                ],
                // Note that this would result in an exception in the merge-ups action itself.
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
            ],
            '5.1.0-beta1, CMS 6 branch detected on silverstripe/framework' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '1.0-beta1'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '11.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '10.11'),
                    $this->getVersionWithOffset($highestMajor, -2, '7.4'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -2),
                    $this->getVersionWithOffset($highestMajor, -2, '6'),
                    $this->getVersionWithOffset($highestMajor, -2, '7'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^' . $this->getVersionWithOffset($highestMajor, 0, '0'),
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
            ],
            '5.1.0 stable and match on silverstripe/cms' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '1.0'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '11.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '10.11'),
                    $this->getVersionWithOffset($highestMajor, -2, '7.4'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -2),
                    $this->getVersionWithOffset($highestMajor, -2, '6'),
                    $this->getVersionWithOffset($highestMajor, -2, '7'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/cms' => '^' . $this->getVersionWithOffset($highestMajor, 0, '1'),
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0),
                ],
            ],
            'match on silverstripe/assets' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '1.0'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '11.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '10.11'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/assets' => '^' . $this->getVersionWithOffset($highestMajor, -3, '0'),
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0),
                ],
            ],
            'match on silverstripe/mfa' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '1.0'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/mfa' => '^' . $this->getVersionWithOffset($highestMajor, 0, '0'),
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0),
                ],
            ],
            'Missing a major branch and match on php version in composer.json' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $this->getVersionWithOffset($lowestMajor, -2),
                'repoTags' => [
                    $this->getVersionWithOffset($lowestMajor, -2, '1.0-beta1'),
                    $this->getVersionWithOffset($lowestMajor, -2, '0.9'),
                    $this->getVersionWithOffset($lowestMajor, -3, '13.11'),
                    $this->getVersionWithOffset($lowestMajor, -3, '12.11'),
                    $this->getVersionWithOffset($lowestMajor, -3, '11.11'),
                    $this->getVersionWithOffset($lowestMajor, -3, '10.11'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($lowestMajor, -3, '10'),
                    $this->getVersionWithOffset($lowestMajor, -3, '11'),
                    $this->getVersionWithOffset($lowestMajor, -3, '12'),
                    $this->getVersionWithOffset($lowestMajor, -3, '13'),
                    $this->getVersionWithOffset($lowestMajor, -2),
                    $this->getVersionWithOffset($lowestMajor, -2, '0'),
                    $this->getVersionWithOffset($lowestMajor, -2, '1'),
                ],
                'composerJson' => [
                    'require' => [
                        'php' => '^' . $highestPHPVersion,
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($lowestMajor, -3, '13'),
                    $this->getVersionWithOffset($lowestMajor, -2, '0'),
                    $this->getVersionWithOffset($lowestMajor, -2, '1'),
                    $this->getVersionWithOffset($lowestMajor, -2),
                ],
            ],
            'Two minor branches without stable tags in composer.json' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $this->getVersionWithOffset($lowestMajor, -2),
                'repoTags' => [
                    $this->getVersionWithOffset($lowestMajor, -2, '3.0-alpha1'),
                    $this->getVersionWithOffset($lowestMajor, -2, '2.0-beta1'),
                    $this->getVersionWithOffset($lowestMajor, -2, '1.0'),
                    $this->getVersionWithOffset($lowestMajor, -2, '0.9'),
                    $this->getVersionWithOffset($lowestMajor, -3, '13.11'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($lowestMajor, -2),
                    $this->getVersionWithOffset($lowestMajor, -2, '0'),
                    $this->getVersionWithOffset($lowestMajor, -2, '1'),
                    $this->getVersionWithOffset($lowestMajor, -2, '2'),
                    $this->getVersionWithOffset($lowestMajor, -2, '3'),
                    $this->getVersionWithOffset($lowestMajor, -3),
                    $this->getVersionWithOffset($lowestMajor, -3, '13'),
                ],
                'composerJson' => [
                    'require' => [
                        'php' => '^' . $highestPHPVersion,
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($lowestMajor, -3, '13'),
                    $this->getVersionWithOffset($lowestMajor, -3),
                    $this->getVersionWithOffset($lowestMajor, -2, '1'),
                    $this->getVersionWithOffset($lowestMajor, -2, '2'),
                    $this->getVersionWithOffset($lowestMajor, -2, '3'),
                    $this->getVersionWithOffset($lowestMajor, -2),
                ],
            ],
            'Module where default branch has not been changed from previous major and there is a new major branch' => [
                'githubRepository' => 'lorem/ipsum',
                // e.g. this repo has a '5' branch for CMS 4 and a '6' branch for CMS 5
                'defaultBranch' => $this->getVersionWithOffset($lowestMajor, 1),
                'repoTags' => [
                    $this->getVersionWithOffset($lowestMajor, 2, '0.0'),
                    $this->getVersionWithOffset($lowestMajor, 1, '9.1'),
                    $this->getVersionWithOffset($lowestMajor, 0, '0.1'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($lowestMajor, 3),
                    $this->getVersionWithOffset($lowestMajor, 2),
                    $this->getVersionWithOffset($lowestMajor, 2, '0'),
                    $this->getVersionWithOffset($lowestMajor, 1),
                    $this->getVersionWithOffset($lowestMajor, 1, '9'),
                    $this->getVersionWithOffset($lowestMajor, 1, '8'),
                    $this->getVersionWithOffset($lowestMajor, 1, '7'),
                ],
                'composerJson' => [
                    'require' => [
                        'php' => '^' . $lowestPHPVersion,
                        'silverstripe/framework' => '^' . $this->getVersionWithOffset($lowestMajor, 0, '11'),
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($lowestMajor, 1, '9'),
                    $this->getVersionWithOffset($lowestMajor, 1),
                    $this->getVersionWithOffset($lowestMajor, 2, '0'),
                    $this->getVersionWithOffset($lowestMajor, 2),
                    $this->getVersionWithOffset($lowestMajor, 3),
                ],
            ],
            'developer-docs' => [
                'githubRepository' => 'silverstripe/developer-docs',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13.0'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.0'),
                ],
                'repoBranches' => [
                    $highestMajor,
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -2),
                ],
                'composerJson' => [
                    'no-require' => new stdClass(),
                ],
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $highestMajor,
                ],
            ],
            'More than 6 branches is fine' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '2.0-beta1'),
                    $this->getVersionWithOffset($highestMajor, 0, '1.0-beta1'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '11.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '10.11'),
                    $this->getVersionWithOffset($highestMajor, -2, '7.4'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -2),
                    $this->getVersionWithOffset($highestMajor, -2, '6'),
                    $this->getVersionWithOffset($highestMajor, -2, '7'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0, '2'),
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
                'composerJson' => [
                    'require' => [
                        'silverstripe/framework' => '^' . $this->getVersionWithOffset($highestMajor, 0),
                    ]
                ],
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0, '2'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
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
            'Incorrect default branch for supported module' => [
                'githubRepository' => 'silverstripe/silverstripe-cms',
                'defaultBranch' => 'main',
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '1.0'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '11.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '10.11'),
                    $this->getVersionWithOffset($highestMajor, -2, '7.4'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -2),
                    $this->getVersionWithOffset($highestMajor, -2, '6'),
                    $this->getVersionWithOffset($highestMajor, -2, '7'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                ],
                'composerJson' => null,
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0),
                ],
            ],
            'Branches in the reverse order, which used to fail' => [
                'githubRepository' => 'silverstripe/framework',
                'defaultBranch' => $highestMajor,
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, -3, '8.6'),
                    $this->getVersionWithOffset($highestMajor, -3, '7.4'),
                    $this->getVersionWithOffset($highestMajor, -2, '1.21'),
                    $this->getVersionWithOffset($highestMajor, -2, '0.4'),
                    $this->getVersionWithOffset($highestMajor, -1, '0.5'),
                    $this->getVersionWithOffset($highestMajor, 0, '1.0'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.1'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -3, '8'),
                    $this->getVersionWithOffset($highestMajor, -3, '7'),
                    $this->getVersionWithOffset($highestMajor, -3),
                    $this->getVersionWithOffset($highestMajor, -2, '1'),
                    $this->getVersionWithOffset($highestMajor, -2),
                    $this->getVersionWithOffset($highestMajor, -1, '0'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $highestMajor,
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
                'composerJson' => null,
                'expected' => [
                    $this->getVersionWithOffset($highestMajor, -1, '0'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                    $highestMajor,
                    $this->getVersionWithOffset($highestMajor, 1),
                ],
            ],
            'Branch created to release unsupported version of new major' => [
                'githubRepository' => 'silverstripe/silverstripe-subsites',
                'defaultBranch' => '4',
                'repoTags' => [
                    '2.0.0',
                    '2.1.0',
                    '3.0.0',
                    '3.1.0',
                    '3.2.0',
                    '3.3.0',
                    '3.4.0',
                    '4.0.0',
                ],
                'repoBranches' => [
                    '2.0',
                    '2.1',
                    '3.0',
                    '3.1',
                    '3.2',
                    '3.3',
                    '3.4',
                    '4.0',
                    '4',
                ],
                'composerJson' => null,
                'expected' => [
                    '3.4',
                ],
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
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        return [
            'Incorrect default branch for random module' => [
                'githubRepository' => 'lorem/ipsum',
                'defaultBranch' => 'main',
                'repoTags' => [
                    $this->getVersionWithOffset($highestMajor, 0, '1.0'),
                    $this->getVersionWithOffset($highestMajor, 0, '0.9'),
                    $this->getVersionWithOffset($highestMajor, -1, '13.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '11.11'),
                    $this->getVersionWithOffset($highestMajor, -1, '10.11'),
                    $this->getVersionWithOffset($highestMajor, -2, '7.4'),
                ],
                'repoBranches' => [
                    $this->getVersionWithOffset($highestMajor, -2),
                    $this->getVersionWithOffset($highestMajor, -2, '6'),
                    $this->getVersionWithOffset($highestMajor, -2, '7'),
                    $this->getVersionWithOffset($highestMajor, -1),
                    $this->getVersionWithOffset($highestMajor, -1, '10'),
                    $this->getVersionWithOffset($highestMajor, -1, '11'),
                    $this->getVersionWithOffset($highestMajor, -1, '12'),
                    $this->getVersionWithOffset($highestMajor, -1, '13'),
                    $this->getVersionWithOffset($highestMajor, 0),
                    $this->getVersionWithOffset($highestMajor, 0, '0'),
                    $this->getVersionWithOffset($highestMajor, 0, '1'),
                ],
                // Even though we know what CMS major to use, because the default branch
                // is incorrect we can't get a good mapping for the rest of the branches
                'composerJson' => [
                    'require' => [
                        'silverstripe/cms' => '^' . $this->getVersionWithOffset($highestMajor, 0, '1'),
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
