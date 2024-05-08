# Silverstripe CMS Supported Modules Metadata

Metadata and some supporting PHP logic for determining which branches of various GitHub repositories relate to which versions of Silverstripe CMS.

> [!IMPORTANT]
> Only the `main` branch of this repository is maintained.

You can fetch the JSON by simply fetching the raw copy of `repositories.json` file, e.g. <https://raw.githubusercontent.com/silverstripe/supported-modules/main/repositories.json>.

If you've included this module as a compser dependency then you can use `SilverStripe\SupportedModules\MetaData::getAllRepositoryMetaData()` which will fetch the latest version of the JSON file from raw.githubusercontent.com.

## Format

There are several sections in the `repositories.json` file, denoting different categories of repositories:

- `supportedModules`: Repositories representing supported modules. If cow cares about it, it should probably be in this category.
- `workflow`: Repositories which hold GitHub actions and workflows.
- `tooling`: Repositories used to help streamline Silverstripe CMS maintenance
- `misc`: All repositories we need to track which don't fit in one of the above categories.

Each of the above sections holds an array of JSON objects with the following data:

|key|type|description|
|---|---|---|
|`github`|_String_|Github repository name (incl. org)|
|`packagist`|_String_|Packagist name. Only relevant if the repo isn't registered in packagist - otherwise null.|
|`githubId`|_Number_|The [id](https://docs.github.com/en/rest/reference/repos#get-a-repository) in Github. Used as a unique identifier.|
|`isCore`|_Boolean_|Is this considered a direct dependency of `silverstripe/installer`, `silverstripe/recipe-cms` or `silverstripe/recipe-core`? (Only relevant for supported modules)|
|`lockstepped`|_Boolean_|Whether this is _always_ given a new minor release in "lock step" with Silverstripe CMS as a whole. (Only relevant for supported modules)|
|`type`|_String_|One of "module", "recipe", "theme", or "other". (Only relevant for supported modules)|
|`majorVersionMapping`|_Object_|A map of major versions, with the Silverstripe CMS major release lines as object keys and an array of all matching major release lines for the repository as values.<br>• The repository versions are branch names, but in most cases these will map to a major release line (e.g. "5" branch which represents the "5.x" release line)<br>• If a `"*"` key is present, it should be used for any CMS major release lines which do not have their own keys.<br>• If a CMS major release line is missing, and there is no `"*"` key, the repository should be ignored for that CMS major release line.<br>• If the value is an empty array, the default branch should be used.|

## Adding a repo

You can easily retrieve the `githubId` via the following API call:

```text
https://api.github.com/repos/my-org/my-repo
```
