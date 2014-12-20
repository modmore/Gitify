# Gitify Changelog

Changes that may have an impact on backwards compatibility (i.e. they may break existing workflows) are marked with `[BC]`.

## Current development (master)

- Add support for TVs on resources, automatically added on extract.
- Add ability to read composite primary keys, when `primary` is set to an array in the `.gitify` data object. (thanks @ahaller)
- Skip modStaticResource in the content extraction to prevent issues with binary file content (thanks @ahaller)
- `[BC]` Apply transliteration and other filters to file names to ensure the names are safe to be used.

## 0.2 - 2014-12-19

- Refactor based on Symfony's Console component and Composer
- `[BC]` Slight change in the content separator (an extra `\n`) so there is now an empty line before and after the 5 dashes.

## 0.1

- Early alpha, first prototype presented at the MODX Weekend
