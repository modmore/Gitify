# Gitify Changelog

Changes that may have an impact on backwards compatibility (i.e. they may break existing workflows) are marked with `[BC]`.

## Current development (master)

- Fix undefined method issue in build command
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
