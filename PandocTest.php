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

    function testEditSection() {
        $input = "abcd\n";
        $expected = "abcd\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function testImgTagReplace1() {
        $input = "{{MARKDOWN}}\n<img src=\"abcd.png\"/>\n\n- Blah Blah\n";
        $expected = "__NOEDITSECTION__\n[[File:abcd.png]]\n\n* Blah Blah\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function testImgTagReplace2() {
        $input = "{{MARKDOWN}}\n<img src=\"abcd.png\">\n\n- Blah Blah\n";
        $expected = "__NOEDITSECTION__\n[[File:abcd.png]]\n\n* Blah Blah\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function testImgTagReplace3() {
        $input = "{{MARKDOWN}}\n<img src=\"abcd.png\"></img>\n\n- Blah Blah\n";
        $expected = "__NOEDITSECTION__\n[[File:abcd.png]]\n\n* Blah Blah\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function testImgTagReplace4() {
        $input = "{{MARKDOWN}}\n<img src=\"abcd.png\">\n<img src=\"abcd2.png\"></img>\n\n- Blah Blah\n";
        $expected = "__NOEDITSECTION__\n[[File:abcd.png]]\n[[File:abcd2.png]]\n\n* Blah Blah\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function testImgTagReplace__externalImage() {
        $input = "{{MARKDOWN}}\n<img src=\"http://example.com/abcd.png\">\n<img src=\"abcd2.png\"></img>\n\n- Blah Blah\n";
        $expected = "__NOEDITSECTION__\nhttp://example.com/abcd.png\n[[File:abcd2.png]]\n\n* Blah Blah\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function testImgTagReplace__noConvertMediawiki() {
        $input = "<img src=\"abcd.png\">\n<img src=\"abcd2.png\"></img>\n\n{{MARKDOWN}}- Blah Blah\n";
        $expected = "__NOEDITSECTION__\n<img src=\"abcd.png\">\n<img src=\"abcd2.png\"></img>\n\n* Blah Blah\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function guessMarkdown() {
        $input = "ByeBye\n\n## Hello world\n\nThis is guess test\n";
        $expected = "__NOEDITSECTION__\nByeBye\n\n== Hello world ==\n\nThis is guess test\n";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function guessMediawiki() {
        $input = "== Hello ==\nasdfffds";
        $expected = "== Hello ==\nasdfffds";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function guessMediawiki__redirect() {
        $input = "#redirect [[another page]]";
        $expected = "#redirect [[another page]]";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function guessMediawiki__redirect2() {
        $input = "#Redirect [[another page]]";
        $expected = "#Redirect [[another page]]";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }

    function guessMarkdown__andWikiAfterwards() {
        $input = "## This is markdown\n{{WIKI}}\nByeBye\n## Hello world\nThis is guess test";
        $expected = "__NOEDITSECTION__\n== This is markdown ==\nByeBye\n## Hello world\nThis is guess test";
        $actual = PandocExtension::doParse($input);
        doAssert($actual, $expected);
    }
}

function doAssert($actual, $expected){
    // With recent version of pandoc, converting headers often result to additional span tags
    // So ignore them!
    if (is_string($actual)) {
        $actual_replaced = preg_replace('/\n?<span id="\S*"><\/span>/', '', $actual);
    } else {
        $actual_replaced = $actual;
    }
    
    if ($actual_replaced !== $expected) {
        echo "[expected]\n\n";
        print_r($expected);
        echo "\n\n[actual]\n\n";
        print_r($actual_replaced);
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
