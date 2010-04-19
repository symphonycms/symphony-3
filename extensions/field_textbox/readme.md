# Field: Text Box

An enhanced replacement for the default Text Input and Textarea fields.

- Version: 2.0.16
- Date: **unreleased**
- Requirements: Symphony 2.1
- Author: Rowan Lewis, me@rowanlewis.com
- GitHub Repository: <http://github.com/rowan-lewis/textboxfield>


## Installation

1. Upload the 'textboxfield' folder in this archive to your Symphony
   'extensions' folder.

2. Enable it by selecting the "Field: Text Box", choose Enable from the
   with-selected menu, then click Apply.

3. You can now add the "Text Box" field to your sections.


## Features

 - Boolean searching with the `boolean` filter.
 - Regular expression searching with the `regexp` filter.
 - Partial searching with `starts-with`, `ends-with` and `contains` filters.
 - The above filters can be negated by prefixing with `not-`.
 - Text formatter and validation rule support.
 - Output grouping on handle.
 - 'Raw' output mode for unformatted data.
 - Parameter output support.
 - Limit the number of characters that can be entered.
 - Limit the number of characters shown in publish table columns.
 - Handles are always unique.


## Changelog

*Version 2.0.14, 19 February 2010*

 - Improved upgrade process.
 - Added a setting that lets you display more than 75 characters in publish table columns.

*Version 2.0.11, 9 December 2009*

 - Really fixed the upgrade issues, *really!*
 - Changed the way handles are created to make sure they are always unique.

*Version 2.0.10, 1 December 2009*

 - Fixed issue where upgrades for 2.0.9 where not being applied due to a Symphony bug.

*Version 2.0.9, 1 December 2009*

 - Added a fancy interface to select filter modes when editing data sources.
 - Added ability to limit input to a specified length.
 - Fixed a few minor bugs.

*Version 2.0.6, 24 July 2009*

 - Added 'raw' output mode to get unformatted data.
 - Added delegates to modify the publish panel.

*Version 2.0.4, 29 June 2009*

 - Fixed a couple of minor issues with data sanitisation.
 - Added `starts-with`, `ends-with` and `contains` filter methods.

*Version 2.0.2, 11 May 2009*

 - This is a complete rewrite of the old extension, it isn't backwards compatible, so don't try to upgrade.