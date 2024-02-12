# Silverstripe CMS Supported Modules Metadata

Used to generate the
[supported modules list](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/) on silverstripe.org,
and is the starting point for tooling such as
our ["Elvis" bug tracker](https://github.com/silverstripe/github-issue-search-client).

Each branch of this repository represents a major release line of Silverstripe CMS. You can fetch the JSON for the relevant release line by simply fetching the raw copy of `modules.json` for a given release branch, e.g. https://raw.githubusercontent.com/silverstripe/supported-modules/5/modules.json

It's known to be used in the following repositories:

- [silverstripe/cow](https://github.com/silverstripe/cow)
- [silverstripe/tx-translator](https://github.com/silverstripe/silverstripe-tx-translator/)
- [bringyourownideas/silverstripe-maintainence](https://github.com/bringyourownideas/silverstripe-maintenance)
- [silverstripe/github-issue-search-client](https://github.com/silverstripe/github-issue-search-client)
- [silverstripe/module-standardiser](https://github.com/silverstripe/module-standardiser)

## Format

 * `github`: String. Github repository name (incl. org)
 * `gitlab`: String. Alternative gitlab repository name (incl. org)
 * `composer`: String. Packagist/composer name
 * `scrutinizer`: Boolean. Does this repo have Scrutinizer enabled?
 * `addons`: Boolean. Does this module exist on addons.silverstripe.org?
 * `type`: String. `supported-module` or `supported-dependency`
 * `githubId`: Number. The [id](https://docs.github.com/en/rest/reference/repos#get-a-repository) in Github. Used as a unique identifier.
 * `isCore`: Boolean. Is this considered a direct dependency of `silverstripe/installer`, `silverstripe/recipe-cms` or `silverstripe/recipe-core`?
 * `branches`: Array&lt;String&gt;. All major branches in lowest-to-heighest order (e.g. `["3", "4"]`, not `["4", "4.12"]`) of this module which are officially supported for this major release line of Silverstripe CMS. E.g. silverstripe/graphql was supported for `3` and `4` for the CMS 4 major release line.
   * Systems using the branches array need to be smart enough to check for last-minor branches if the branch in the list is missing from github (e.g. if `4` is missing, fetch the list of branches for that repository from the github API and use the latest `4.x` (e.g. `4.13`) branch).

## Adding a repo

You can easily retrieve the `githubId` via the following API call:

```
https://api.github.com/repos/my-org/my-repo
```
