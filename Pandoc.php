<?php
/**
 * This is a Mediawiki Extension that translates other doc formats to mediawiki.
 * Pandoc must be installed to use this extension.
 * 
 * Much help from https://github.com/bharley/mw-markdown source code
 */


if (!defined('MEDIAWIKI')) die();

$wgExtensionCredits['parserhook'][] = array(
    'name'         => 'Pandoc',
    'description'  => 'Parse other doc formats to mediawiki format in runtime, using Pandoc',
    'version'      => '0.3',
    'author'       => 'Jang Ryeol',
    'url'          => 'https://github.com/bekker/pandoc-mediawiki-ext',
    'license-name' => 'MIT',
);

// Available config options and default values
$wgPandocDefaultFormat = 'mediawiki';
$wgPandocParseWordRegex = '/{{PARSEFROM:(\S*)}}\n?/';
$wgPandocParseWordAlias = array(
    '{{MARKDOWN}}' => 'gfm',
    '{{WIKI}}' => 'mediawiki'
);
$wgPandocDisableEditSection = true;
$wgPandocExecutablePath = 'pandoc';
$wgPandocExecutableOption = '--wrap=preserve';
$wgPandocReplaceImgTag = true;
$wgPandocEnableGuess = true;

// Register hook
$wgHooks['ParserBeforeInternalParse'][] = 'PandocExtension::onParserBeforeInternalParse';

class PandocExtension
{
    /**
     * If everything checks out, this hook will parse the given text for Markdown.
     *
     * @param Parser $parser MediaWiki's parser
     * @param string $text   The text to parse
     */
    public static function onParserBeforeInternalParse($parser, &$text)
    {
        $text = static::doParse($text);
        return true;
    }

    protected static function doParse($text) {
        global $wgPandocDisableEditSection;
    
        $parseSections = static::findParseSections($text);
        if (static::isTranslationNecessary($parseSections)) {
            $newText = "";

            if ($wgPandocDisableEditSection) {
                $newText .= "__NOEDITSECTION__\n";
            }

            foreach ($parseSections as $section) {
                $docType = $section['docType'];
                $content = substr($text, $section['start'], $section['length']);
                if ($docType != 'mediawiki') {
                    $output = static::translateUsingPandoc($docType, $content);
                    $output = static::doPostProcessing($output);
                } else {
                    $output = $content;
                }
                $newText .= $output;
            }

            return $newText;
        }
        return $text;
    }

    protected static function doPostProcessing($output) {
        return static::replaceHtmlTagIfNecessary($output);
    }

    protected static function replaceHtmlTagIfNecessary($input) {
        global $wgPandocReplaceImgTag;

        $htmlTags = array();

        if ($wgPandocReplaceImgTag) {
            preg_match_all("/<img [^>]*src\s*=\"([^\"]*)\"[^>]*\/?>(?:<\/img[^>]*>)?/", $input, $matches, PREG_OFFSET_CAPTURE);
            $count = count($matches[0]);
            
            for ($i = 0; $i < $count; $i++) {
                $wholeMatch = $matches[0][$i];
                $src = $matches[1][$i][0];

                $htmlTags[] = array(
                    'length' => strlen($wholeMatch[0]),
                    'offset' => $wholeMatch[1],
                    'tagName' => 'img',
                    'src' => $src,
                    'original' => $wholeMatch[0]
                );
            }
        }

        $count = count($htmlTags);

        if ($count == 0) {
            return $input;
        }

        $newText = substr($input, 0, $htmlTags[0]['offset']);

        for ($i = 0; $i < $count; $i++) {
            $htmlTag = $htmlTags[$i];

            $tagName = $htmlTag['tagName'];
            switch ($tagName) {
                case 'img':
                    $src = $htmlTag['src'];
                    $isExternalLink = strncmp($src, "http://", 7) === 0 || strncmp($src, "https://", 8) === 0;
                    if ($isExternalLink) {
                        // See https://www.mediawiki.org/wiki/Manual:$wgAllowExternalImages
                        $newText .= $src;
                    } else {
                        $converted = static::translateUsingPandoc('html', substr($input, $htmlTag['offset'], $htmlTag['length']));
                        $trimmed = trim($converted);
                        $newText .= $trimmed;
                    }
                    break;
                default:
                    $newText .= $original;
            }
            
            $cursor = $htmlTag['offset'] + $htmlTag['length'];
            if ($i < $count - 1) {
                $nextHtmlTag = $htmlTags[$i + 1];
                $newText .= substr($input, $cursor, $nextHtmlTag['offset'] - $cursor);
            } else {
                $newText .= substr($input, $cursor);
            }
        }

        return $newText;
    }

    protected static function findParseSections($input) {
        global $wgPandocDefaultFormat;
    
        $parseSections = array();
        $parseWords = static::findParseWords($input);
        $count = count($parseWords);

        // If no parse words found
        if ($count == 0) {
            $start = 0;
            $length = strlen($input);
            $parseSections[] = array(
                "docType" => static::guessDocType($input),
                "start" => $start,
                "length" => $length
            );
            return $parseSections;
        }
        
        // Default section
        if ($count > 0 && $parseWords[0]['offset'] > 0) {
            $start = 0;
            $length = $parseWords[0]['offset'];
            $content = substr($input, $start, $length);
            $parseSections[] = array(
                "docType" => static::guessDocType($content),
                "start" => $start,
                "length" => $length
            );
        }
        
        for ($i = 0; $i < $count; $i++) {
            $parseWord = $parseWords[$i];
            $docType = $parseWord['docType'];
            $start = $parseWord['offset'] + $parseWord['parseWordLength'];
            if ($i < $count - 1) {
                $length = $parseWords[$i + 1]['offset'] - $start;
            } else {
                $length = strlen($input) - $start;
            }
            
            $parseSections[] = array(
                "docType" => $docType,
                "start" => $start,
                "length" => $length
            );
        }
        
        return $parseSections;
    }

    /**
     * Find parse words
     * 
     * == example input ==
     * 
     * start of the input
     * {{MARKDOWN}}
     * alias
     * {{PARSEFROM:mediawiki}}* This is mediawiki text
     * {{PARSEFROM:gfm}}
     * - Hello, World!
     * 
     * == example output ==
     * 
     * Array
     * (
     *     [0] => Array
     *         (
     *             [parseWordLength] => 12
     *             [offset] => 19
     *             [docType] => gfm
     *         )
     *
     *     [1] => Array
     *         (
     *             [parseWordLength] => 23
     *             [offset] => 38
     *             [docType] => mediawiki
     *         )
     *
     *     [2] => Array
     *         (
     *             [parseWordLength] => 17
     *             [offset] => 86
     *             [docType] => gfm
     *         )
     *)
     * 
     */
    protected static function findParseWords($input)
    {
        $result = array_merge(static::findRegexParseWords($input), static::findAliasParseWords($input));
        usort($result, function ($a, $b) {
            if ($a['offset'] == $b['offset']) {
                return 0;
            }
            return ($a['offset'] < $b['offset']) ? -1 : 1;
        });
        return $result;
    }

    protected static function findRegexParseWords($input)
    {
        global $wgPandocParseWordRegex;
        
        $count = preg_match_all($wgPandocParseWordRegex, $input, $matches, PREG_OFFSET_CAPTURE);
        $wholeMatches = $matches[0];
        $groupMatches = $matches[1];
        
        
        $result = array();
        for ($i = 0; $i < $count; $i++) {
            $parseWordLength = strlen($wholeMatches[$i][0]);
            $offset = $wholeMatches[$i][1];

            $result[] = array(
                'parseWordLength' => $parseWordLength,
                'offset' => $offset,
                'docType' => $groupMatches[$i][0]
            );
        }
        
        return $result;
    }
    
    protected static function findAliasParseWords($input)
    {
        global $wgPandocParseWordAlias;
        
        $result = array();
        foreach ($wgPandocParseWordAlias as $parseWord => $docType) {
            preg_match_all("/{$parseWord}\n?/", $input, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[0] as $wholeMatch) {
                $parseWordLength = strlen($wholeMatch[0]);
                $offset = $wholeMatch[1];

                $result[] = array(
                    'parseWordLength' => $parseWordLength,
                    'offset' => $offset,
                    'docType' => $docType
                );
            }
        }
        return $result;
    }

    protected static function guessDocType($text) {
        global $wgPandocEnableGuess;
        global $wgPandocDefaultFormat;

        if (!$wgPandocEnableGuess) {
            return $wgPandocDefaultFormat;
        }

        if (preg_match("/^#redirect/i", $text)) {
            return 'mediawiki';
        }

        if (preg_match("/(^#)|(\n#)|(^```)|(\n```)/", $text)) {
            return 'gfm';
        }

        return $wgPandocDefaultFormat;
    }

    protected static function isTranslationNecessary($parseSections)
    {
        foreach ($parseSections as $section) {
            if ($section['docType'] != 'mediawiki') {
                return true;
            }
        }
        return false;
    }

    protected static function translateUsingPandoc($from, $input)
    {
        global $wgPandocExecutablePath;
        global $wgPandocExecutableOption;

        // mediawiki -> mediawiki conversion is not necessary
        if ($from == 'mediawiki') {
            return $input;
        }

        $cmd = "{$wgPandocExecutablePath} {$wgPandocExecutableOption} -f {$from} -t mediawiki";
        return static::runProcess($cmd, $input);
    }

    /**
     * Spawns a process with $command and returns stdout result
     * Reference:
     * http://omegadelta.net/2012/02/08/stdin-stdout-stderr-with-proc_open-in-php/
     * https://www.php.net/manual/en/function.proc-open.php#64116
     */
    protected static function runProcess($command, $stdin)
    {
        $descriptorSpec = array(0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w'));
        $process = proc_open($command, $descriptorSpec, $pipes);
        $txOff = 0; $txLen = strlen($stdin);
        $stdout = ''; $stdoutDone = FALSE;
        $stderr = ''; $stderrDone = FALSE;
        stream_set_blocking($pipes[0], 0); // Make stdin/stdout/stderr non-blocking
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        if ($txLen == 0) fclose($pipes[0]);
        while (TRUE) {
            $rx = array(); // The program's stdout/stderr
            if (!$stdoutDone) $rx[] = $pipes[1];
            if (!$stderrDone) $rx[] = $pipes[2];
            $tx = array(); // The program's stdin
            if ($txOff < $txLen) $tx[] = $pipes[0];
            $ex = NULL;
            stream_select($rx, $tx, $ex, NULL, NULL); // Block til r/w possible
            if (!empty($tx)) {
                $txRet = fwrite($pipes[0], substr($stdin, $txOff, 8192));
                if ($txRet !== FALSE) $txOff += $txRet;
                if ($txOff >= $txLen) fclose($pipes[0]);
            }
            foreach ($rx as $r) {
                if ($r == $pipes[1]) {
                    $stdout .= fread($pipes[1], 8192);
                    if (feof($pipes[1])) { fclose($pipes[1]); $stdoutDone = TRUE; }
                } else if ($r == $pipes[2]) {
                    $stderr .= fread($pipes[2], 8192);
                    if (feof($pipes[2])) { fclose($pipes[2]); $stderrDone = TRUE; }
                }
            }
            if (!is_resource($process)) break;
            if ($txOff >= $txLen && $stdoutDone && $stderrDone) break;
        }
        $returnValue = proc_close($process);

        if ($returnValue != 0) {
            throw new Exception("Pandoc executable exited with {$returnValue}. stderr:\n{$stderr}");
        }

        return $stdout;
    }
}
