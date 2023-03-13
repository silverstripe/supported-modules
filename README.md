# Silverstripe CMS Supported Modules Metadata

Used to generate the
[supported modules list](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/) on silverstripe.org,
and is the starting point for tooling such as
our ["Elvis" bug tracker](https://github.com/silverstripe/github-issue-search-client).

It's known to be used for the following modules:
- silverstripe/tx-translator
- bringyourownideas/silverstripe-maintainence

## Format

 * `github`: String. Github repository name (incl. org)
 * `gitlab`: String. Alternative gitlab repository name (incl. org)
 * `composer`: String. Packagist/composer name
 * `scrutinizer`: Boolean. Does this repo have Scrutinizer enabled?
 * `addons`: Boolean. Does this module exist on addons.silverstripe.org?
 * `type`: String. `supported-module` or `supported-dependency`
 * `githubId` Number. The [id](https://docs.github.com/en/rest/reference/repos#get-a-repository) in Github. Used as a unique identifier.
 * `isCore`. Boolean. Is this considered a direct dependency of `silverstripe/installer`, `silverstripe/recipe-cms` or `silverstripe/recipe-core`?

## Adding a repo

You can easily retrieve the `githubId` via the following API call:

```
https://api.github.com/repos/my-org/my-repo
```
