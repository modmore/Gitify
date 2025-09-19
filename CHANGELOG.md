# Gitify Changelog

Changes that may have an impact on backwards compatibility (i.e. they may break existing workflows) are marked with `[BC]`.

## 2.2.0 - 2025-09-19
- Bump symfony/console to 5.4.36
- Bump symfony/yaml to 5.4.35
- Optionally check and upgrade existing packages listed in the .gitify file when running package:install (thanks @halftrainedharry) [#458]
- Add --ignore-table option to the backup command (thanks @jenswittmann) [#455]

## 2.1.0 - 2023-12-11
- Bump symfony/console to 5.4.32
- Bump symfony/yaml to 5.4.31
- Fix non-English filename generation (thanks @livingroot) [#442]
- Add new config option 'category_ids_in_elements' to extract category ids instead of category names (thanks @wuuti) [#443]
- Add ability to duplicate contexts with resources (thanks @bezumkin) [#439]
- Add handling for packages using the new xPDO v3 model structure when extracting and building [#444]

## 2.0.1 - 2023-03-24
- Switch base 'config' option (introduced in 2.0.0) to 'dotfile', to prevent conflict with 'config' option in modx:install command. (26f6c3c)

## 2.0.0 - 2023-03-23
- Prevent E_WARN errors in build
- Fix fallback Gitify-Cache-Folder
- Bump symfony/console to 5.3.7
- Bump symfony/process to 5.3.7
- Bump symfony/yaml to 5.3.6
- Add --no-tablespaces option for "backup" command.
- Remove deprecated CLI install arguments
- Add "local" option to package:install (thanks @hugopeek) (#261)
- Check for unmet dependencies when installing local packages.
- Allow for custom core path and a renamed manager directory. (thanks @it-scripter) (#223)
- Add ability to install using a config xml file via the --config parameter. (thanks @hugopeek) (#219)
- Use --core-path param for all installs to ensure the correct core path is used in config.inc.php.
- Download the latest versions of packages more reliably. (thanks @hugopeek) (#327)
- Backup and restore commands can now handle compressed gzip files. (thanks @rchouinard) (#378)
- Fix fatal type error in ClearCacheCommand. (thanks @jgullege19) (#414)
- Trigger MODX into setup mode during build. (thanks @matdave) (#406)
- Fix package:install not working for MODX 3.x (#415)
- Add ability to specify a config file to use (thanks @rtripault) (#417)
- Add ability to limit number of extracted resources per parent (thanks @rtripault) (#418)
- Force refreshing namespace cache after build (thanks @rtripault) (#422)
- Prevent content attribute being added/removed intermittently, by unsetting content for static elements when extracting (thanks @rtripault) (#423)
- Fix undefined array key 'service' warning on PHP 8.x (thanks @hugopeek) (#427)
- Automatically update the list of packages with versions during extract + improve install (#430)

## 0.12.0 - 2015-12-17
- Add `exclude_tvs` option to the `content` data type to allow excluding certain TVs
- Add `credential_file` option to providers to contain the `username` and `api_key` (#155)
- Fix GITIFY_WORKING_DIR constant on windows (#149)

## 0.11.0 - 2015-11-04
- Fix E_STRICT error in loadConfig (#136)
- Fix file path comparisons to work properly across unix and windows (#99)
- Cache MODX packages so it doesn't have to download every time (#133)
- Change the way Git.php dependency is loaded and version number is managed (#135)
- Fix stupid bug in Gitify->getEnvironment causing www to not get stripped off properly
- Allow setting the path to a git binary in a `gitify.git_path` setting
- Add optional `--overwrite` flag to backup to overwrite a named backup file
- Fix broken error message in backup command if file already exists

## 0.10.0 - 2015-09-15
- Make sure `modTemplateVar` is set before content in the gitify file (#88)
- Add `modTemplateVarResource` to default `truncate_on_force` for content to make sure the DB is cleaned properly on force (#111) 
- Store installed packages with the full signature instead of just the package name (#110)
- Add `modx:upgrade` command to download a newer version and to run the upgrade (#116)
- Prevent `PHP Warning: mkdir(): File exists` errors during init if backup and data folders already exist (#128)
- Output result from command line install during `modx:install` (#127)
- Fix unzip in `modx:install` on certain systems (#126)
- Added support to get the git repository and environment specific options
- Added symfony2/process and git.php dependencies
- Implement/improve support for `where` attributes on content and other objects

## 0.9.0 - 2015-05-15
- Implement automatic ID Conflict resolution during build, which will fix duplicate ID errors automatically. (#86, related to #69, #53)
- [BC] Implement orphan handling during build, which removes any object that no longer exists in file automatically.
- Add `no-cleanup` flag to build to allow bypassing the orphan handling.
- Fix several issues using Gitify on Windows:
  \ - Due to inconsistent directory separators, certain files would be removed on extract.
  \ - Ensure line endings are normalized (LF \n) to prevent issues reading files/parsing yaml
- `Gitify backup` and `Gitify restore` now also pass the host to the command, so should now work with non-localhost databases (#80, #82)
- Extend `Gitify init` with more recommended options to include in the data, and automatically listing installed packages (#41)
- Improved formatting of command output somewhat in several commands

## 0.8.0 - 2015-04-14
- [BC] Rename `Gitify install:package` to `Gitify package:install`, and `Gitify install:modx` to `Gitify modx:install`. Aliases are in place so they will continue to work for now, but those will be removed in v1. 
- Extract instantiation logic out of Gitify file into application.php for easier integration with non-CLI PHP.
- Small speed optimization (~ 10%) to `Gitify build --force` (#78)
- Passing arguments to `Gitify build` and  `Gitify extract` now restricts building/extracting to those specific data partitions/folders (#51, #26)
- Escape password in backup/restore commands so they work with special characters in the password

## 0.7.0 - 2015-03-31
- Add new `Gitify backup` and `Gitify restore` commands (#39)
- Fix issue with installing packages where it proceeds to install FormIt2db if FormIt is already installed (same with MIGX)
- Make sure installing packages works properly when the package name isn't the same as the signature (#74)

## 0.6.0 - 2015-03-30
- Fix silly `<note>` in output from install:modx
- Add new `truncate_on_force` option that accepts an array of class names that need to be truncated before building (#73) - [see the wiki](https://github.com/modmore/Gitify/wiki/3.-The-.gitify-File#dealing-with-closures)
- Show a helpful warning if the user forgot to run composer install (#67)
- With install:package, prefer exact matches over provider order to ensure the right packages are installed (#58)

## 0.5.2 - 2015-03-05
- Add new --interactive (-i) flag to `install:package --all` command to interactively install all packages defined in .gitify file
- Fix issue where composite primary keys have the default value (e.g. sources.odMediaSourceElement) and it can't build those objects (#65)
- Fix issue with modCategory objects and force building (#66)
- Make sure TVs are inserted into the resource file alphabetically (#54)

## 0.5.1 - 2015-02-26
- Fix duplicate paths in file name if alias contains a folder structure
- Fix issue where in some cases, if the content was empty any field that can be compared to empty is removed from the file

## 0.5.0 - 2015-02-08

- Add package support with `install:package [packagename]` and `install:package --all` based off the .gitify file (#3)
- Apply the --force flag to all content types (#47)

## 0.4.1 - 2015-01-27

- Fix install:modx command being called in Gitify init (#42)
- Don't exclude `createdby`/`createdon` keys from resources by default; could result in lost data on `build --force`

## 0.4 - 2014-12-31

- Add time and memory usage statistics (#34)
- Fix PHP warning: Illegal offset type when using composite primary keys (#36)
- Fix issue where resources in different contexts don't get built because of conflicting URIs (#35)
- Make sure cases where saving a resource/object fails get logged to the terminal

## 0.3 - 2014-12-23

- `[BC]` Fix `build` issue with ContentBlocks (and probably other extras) caused by automatically expanding JSON
- Improve information provided during `build` and `extract`, including more verbose logging with `-v` or `--verbose` flag
- Fix undefined method issue in `build` command
- Catch YAML parse exceptions so it doesn't kill the entire process on an invalid file
- Prevent Gitify from trying to build dotfiles
- Ensure file path transliteration/filters also works pre MODX 2.3
- Simplify the number of options that are added by default to all commands.
- Add ability to specify the MODX version to download/install in `Gitify install:modx [modx_version]`
- Add support for TVs on resources, automatically added on extract.
- Add ability to read composite primary keys, when `primary` is set to an array in the `.gitify` data object. (thanks @ahaller)
- Skip modStaticResource in the content extraction to prevent issues with binary file content (thanks @ahaller)
- `[BC]` Apply transliteration and other filters to file names to ensure the names are safe to be used.

## 0.2 - 2014-12-19

- Refactor based on Symfony's Console component and Composer
- `[BC]` Slight change in the content separator (an extra `\n`) so there is now an empty line before and after the 5 dashes.

## 0.1

- Early alpha, first prototype presented at the MODX Weekend
