<?php

/**
 * Few simple tests using assert...
 */
define('MEDIAWIKI', 'test');
require_once('./Pandoc.php');

class PandocTest extends PandocExtension {
    function testDoParse() {
        $input =
"start of the input
__TOC__
{{MARKDOWN}}
## This is markdown header

[[File:some_pictures.png]]

[[Some Internal Link | Interesting Things]]

```
Some code snippets
```

__this is markdown bold__{{PARSEFROM:mediawiki}}* This is mediawiki text

{{PARSEFROM:gfm}}
- Hello, World!
";

        $expected = 
"__NOEDITSECTION__
start of the input
__TOC__
== This is markdown header ==

[[File:some_pictures.png]]

[[Some Internal Link | Interesting Things]]

<pre>Some code snippets</pre>
'''this is markdown bold'''
* This is mediawiki text

* Hello, World!
";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function testFindParseSections() {
        $input = "start of the input\n{{MARKDOWN}}\nalias\n{{PARSEFROM:mediawiki}}* This is mediawiki text\n{{PARSEFROM:gfm}}\n- Hello, World!";
        $expected = array(
            0 => array(
                'docType' => 'mediawiki',
                'start' => 0,
                'length' => 19
            ),
            1 => array(
                'docType' => 'gfm',
                'start' => 32,
                'length' => 6
            ),
            2 => array(
                'docType' => 'mediawiki',
                'start' => 61,
                'length' => 25
            ),
            3 => array(
                'docType' => 'gfm',
                'start' => 104,
                'length' => 15
            )
        );
        $actual = PandocExtension::findParseSections($input);
        doAssert($actual, $expected);
    }

    function testFindParseSections__onlyMarkdown() {
        $input = "{{MARKDOWN}}\nabcd";
        $expected = array(
            0 => array(
                'docType' => 'gfm',
                'start' => 13,
                'length' => 4
            )
        );
        $actual = PandocExtension::findParseSections($input);
        doAssert($actual, $expected);
    }

    function testNoEditSection() {
        $input = "{{MARKDOWN}}\nabcd\n";
        $expected = "__NOEDITSECTION__\nabcd\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }
}

function doAssert($actual, $expected){
    if ($actual !== $expected) {
        echo "[expected]\n\n";
        print_r($expected);
        echo "\n\n[actual]\n\n";
        print_r($actual);
        echo "\n\n";
    }
}

$pandocTest = new PandocTest();
$testMethods = array_diff(get_class_methods($pandocTest), get_class_methods(get_parent_class($pandocTest)));
foreach ($testMethods as $method) {
    try {
        echo "---- Test {$method} ----\n";
        $pandocTest->{$method}();
    } catch (Throwable $err) {
        echo "Error: {$err->getMessage()}\n";
    }
}
