# Pandoc Mediawiki Extension

This is a Mediawiki Extension that translates other doc formats to Mediawiki __on runtime__.

The main purpose of this extension is **to use Markdown semantics on Mediawiki pages!**

`pandoc` must be installed and provided by `$PATH` env var to use this extension.

## Requirements
- `pandoc` must be installed on the server.
- Output could be different by which `pandoc` version is installed. (i.e. `<syntaxhighlight>` vs `<source>`)
- Tested on Mediawiki 1.35.1, but should work on 1.6.0+

## Install
- Download from https://github.com/bekker/pandoc-mediawiki-ext/releases
- Extract this extension into `$mw/extensions/Pandoc` where $mw is that path to your MediaWiki installation 
- Add the following to $mw/LocalSettings.php:

```php
require_once("$IP/extensions/Pandoc/Pandoc.php");
```

## Usage

### Default behavior
- This extension tries to guess the format of a page.
- For example, if Atx-style headers are found, the Markdown parser is applied.
- If the guessed format is wrong (which is very likely at the current version) you can specify format by a magic word as described below.
  - Or disable guessing by setting `$wgPandocEnableGuess` to `false`.

### Declare a magic word at the start of the page

- You can specify which format you want to use.
- Add `{{PARSEFROM:<pandoc format name>}}` on the page.
- You can set default parsing format with `$wgPandocDefaultFormat` option. (See below)

#### Actual content
```
{{PARSEFROM:gfm}}
## This is Markdown header

- This is __Markdown__ format

```

#### What you see (expressed in Mediawiki format)
```
== This is Markdown header ==

* This is '''Markdown''' format

```

### Mix formats on the same page

- You can add `{{PARSEFROM:<pandoc format name>}}` in the middle of the page.
- You can add multiple `{{PARSEFROM:<pandoc format name>}}` on the page.
- Only content below the magic word would be parsed.

```
__TOC__

* Any Mediawiki grammar here would work!

{{PARSEFROM:gfm}}
## This is Markdown header

- This is __Markdown__ format

{{PARSEFROM:mediawiki}}

* Now Mediawiki again

```

### Aliases

- You can use aliases for specific formats.
- By default following alises are enabled, but you can add one by setting `$wgPandocParseWordAlias` (See below)
  - `{{MARKDOWN}}` for Github Flavored Markdown
  - `{{WIKI}}` for default Mediawiki format.

```
__TOC__

* Any Mediawiki grammar here would work!

{{MARKDOWN}}
## This is Markdown header

- This is __Markdown__ format

{{WIKI}}

* Now Mediawiki again

```

## Options

Name | Default | Description
---- | ------- | -----------
`$wgPandocDefaultFormat` | `'mediawiki'` | Default format. You can set any value `pandoc` allows.\nRegardless of this value, the extension tries to choose the right format if `$wgPandocEnableGuess` is enabled.
`$wgPandocEnableGuess` | `true` | Whether to enable guess document format
`$wgPandocParseWordRegex` | `'/{{PARSEFROM:(\S*)}}\n?/'` | You can change the magic word regex.
`$wgPandocParseWordAlias` | `array('{{MARKDOWN}}' => 'gfm', '{{WIKI}}' => 'mediawiki');` | You can add aliases for any specific format.
`$wgPandocDisableEditSection` | `true` | By default, using any other format than `mediawiki` disables edit section functionality on the page. (`__NOEDITSECTION__` is added)
`$wgPandocExecutablePath` | `'pandoc'` | You can set arbitrary `pandoc` executable path. Change this when you can't access `pandoc` from the `$PATH`.
`$wgPandocExecutableOption` | `'--wrap=preserve'` | You can set any options when executing `pandoc`.
`$wgPandocReplaceImgTag` | `true` | Whether to replace `<img>` HTML tags or not.

### Example
#### Use default
- Just add `require_once`.

```php
// LocalSettings.php
// ...

require_once("$IP/extensions/Pandoc/Pandoc.php");
```

#### Customize
```php
// LocalSettings.php
// ...

require_once("$IP/extensions/Pandoc/Pandoc.php");

// Change default format to Github Flavored Markdown
$wgPandocDefaultFormat = 'gfm';

// Do not preserve line breaks
$wgPandocExecutableOption = '';

// ...
```
