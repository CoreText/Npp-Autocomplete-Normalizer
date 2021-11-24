<?php

/**
 * function xml2array
 *
 * This function is part of the PHP manual.
 *
 * The PHP manual text and comments are covered by the Creative Commons
 * Attribution 3.0 License, copyright (c) the PHP Documentation Group
 *
 * @author  k dot antczak at livedata dot pl
 * @date    2011-04-22 06:08 UTC
 * @link    http://www.php.net/manual/en/ref.simplexml.php#103617
 * @license http://www.php.net/license/index.php#doc-lic
 * @license http://creativecommons.org/licenses/by/3.0/
 * @license CC-BY-3.0 <http://spdx.org/licenses/CC-BY-3.0>
 */
function xml2array($obj, $out = []) {
    foreach ((array)$obj as $index => $node) {
        $out[$index] = (is_object($node))? xml2array($node) : $node;
    }
    return $out;
}

function xmlToArray(array $arr): array {
    $json = json_encode($someArray, JSON_FORCE_OBJECT, 1024);
    return json_decode($json, JSON_OBJECT_AS_ARRAY, 1024);
}

/**
 * Array to XML.
 *
 * @param $data
 * @param $xml_data
 * @return void
 */
function array2xml($data, &$xml_data) {
    static $overload;

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (is_numeric($key)) {
                $key = 'KeyWord';
                $subnode = $xml_data->addChild($key);
            }

            if ($key === 'Overload') {
                if ($xml_data !== null) {
                    $overload = $subnode = $xml_data->addChild($key);
                }
            }

            if ($key === 'Param') {
                foreach ($value as $keyVal => $val) {
                    $subnode = $overload->addChild($key);

                    if (!is_array($val)) {
                        $subnode['name'] = (string)$val->attributes();
                    }
                }
            }

            if ($key === '@attributes') {
                foreach ($value as $keyVal => $val) {
                    if (!is_array($val) && $xml_data !== null) {
                        $xml_data->addAttribute($keyVal, $val);
                    }
                }
            }

            array2xml($value, $subnode);
        }
    }
}

function formatXml($simpleXml, string $newFile = 'dist/php.xml') {
    $domxml = new DOMDocument('1.0');
    $domxml->preserveWhiteSpace = false;
    $domxml->formatOutput = true;
    /* @var $xml SimpleXMLElement */
    $renderedXml = $simpleXml->asXML();
    $domxml->loadXML($renderedXml);
    // $domxml->normalizeDocument();
    $domxml->save($newFile);
    return str_replace('  ', "\t", $domxml->saveXML());
}

/**
 * Sort keywords by attribute.
 *
 * @param array $keyWords
 * @param string $attr  default is 'name'
 * @return array
 */
function sortKeyWordsByAttribute(array $keyWords, string $attr = 'name'): array {
    usort($keyWords, function ($item1, $item2) use ($attr) {
        if ($item1['@attributes'][$attr] === $item2['@attributes'][$attr]) {
            return 0;
        }

        return ($item1['@attributes'][$attr] < $item2['@attributes'][$attr])
            ? -1 : 1;
    });

    return $keyWords;
}

/**
 * Add mising attributes and make unique.
 *
 * @param array $keyWords
 * @return array
 */
function normilizeKeyWordElement(array $keyWords): array {
    foreach ($keyWords as $key => $word) {
        if (isset($word['@attributes']['name']) && !isset($word['@attributes']['func'])) {
            $keyWords[$key]['@attributes']['func'] = 'no';
        }
    }

    return arrayUnique($keyWords, static function (array $element) {
        return $element['@attributes']['name'];
    });
}

/**
 * Create Unique Arrays.
 *
 * @param array $array
 * @param callable $callback
 * @return array
 */
function arrayUnique(array $array, callable $callback): array {
    $unique_array = [];
    foreach($array as $element) {
        $hash = $callback($element);
        $unique_array[$hash] = $element;
    }

    //print_r(arrayMultiDiff($unique_array, $array));die;

    return array_values($unique_array);
}

/**
 * Will be used to check the difference.
 * 
 * @param $array1
 * @param $array2
 * @return string
 */
function arrayMultiDiff($array1, $array2) {
    $result = [];

    $main = serialize(xmlToArray($array1));
    $replace = serialize(xmlToArray($array2));

    //var_dump($main);die();
    //var_dump($replace);die();

    $diff = str_replace($replace, $main);

    return unserialize($diff);
}

/**
 * Other diff.
 *
 * @param string $old
 * @param string $new
 * @return array
 */
function otherDecoratedDiff(string $old, string $new): array {
    $fromStart = strspn($old ^ $new, "\0");
    $fromEnd = strspn(strrev($old) ^ strrev($new), "\0");

    $oldEnd = strlen($old) - $fromEnd;
    $newEnd = strlen($new) - $fromEnd;

    $start = substr($new, 0, $fromStart);
    $end = substr($new, $newEnd);

    $newDiff = substr($new, $fromStart, $newEnd - $fromStart);
    $oldDiff = substr($old, $fromStart, $oldEnd - $fromStart);

    return [
        'old' => "<code class='code language-xml left' spellcheck='false' contenteditable='true'>$start<span class='cb-red'>$oldDiff</span>$end</code>",
        'new' => "<code class='code language-xml right' spellcheck='false' contenteditable='true'>$start<span class='cb-green'>$newDiff</span>$end</div>",
    ];
}

/**
 * Decorate the diff.
 *
 * @param $old
 * @param $new
 * @return array
 */
function getDecoratedDiff(string $old, string $new): array {
    return [
        'old' => "<textarea class='left'  id='left' spellcheck='false' contenteditable='true'>$old</textarea>",
        'new' => "<textarea class='right' id='right' spellcheck='false' contenteditable='true'>$new</textarea>",
    ];
}

/**
 * Render the diff.
 *
 * @param string $stringOld
 * @param string $stringNew
 * @return string
 */
function renderDiff(
    string $stringOld,
    string $stringNew,
    $other,
    $keyWordsListBefore,
    $keyWordsListAfter
    ): string {
    $styles = css();

    if ($other)
        $diff = getDecoratedDiff($stringOld, $stringNew);
    else
        $diff = otherDecoratedDiff($stringOld, $stringNew);

    return <<<DIFF
<style type="text/css">
$styles
</style>
<div class="diff">
    <table class="diff-table">
        <tr>
            <th>OLD ($keyWordsListBefore)</th>
            <th>NEW ($keyWordsListAfter) Normalized Version</th>
        </tr>
        <tr>
            <td class="diff-table-cell">{$diff['old']}</td>
            <td class="diff-table-cell">{$diff['new']}</td>
        </tr>
    </table>
</div>
DIFF;
}

/**
 * Status message of the parsed XML.
 * 
 * @param $isValidXml
 * @return string
 */
function isValidMessage($isValidXml) {
    if ($isValidXml) {
        return '<h1 class="special-header noselect cb-green">The XML is valid: ' . print_r($isValidXml, true) . '!</h1>';
    }
    else {
        return '<h1 class="special-header cb-red">The XML is not valid!<br><pre>' . print_r($isValidXml, true) . '</pre></h1>';
    }
}

function escapeXmlForBrowser($xml) {
    return '<pre>' . htmlspecialchars($xml) . '</pre>';
}

/**
 * CSS styles.
 *
 * @return string
 */
function css() {
    return <<<CSS
    .cf-white { color: white; }
    .cb-red { background-color: tomato; }
    .cb-green { background-color: greenyellow; }
    .diff {
        width: 100%;
        padding: 1em 0;
        border: 1px solid lightgrey;
        margin: 1em 0;
        overflow-x: hidden;
    }
    .diff-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        word-wrap: break-word;
        overflow: scroll;
    }
    .diff-table-cell {
        vertical-align: baseline;
        overflow: scroll;
    }
    .right, .left,
    .overflow { overflow: scroll; }
    .right, .left {
        width: 100%;
    }
    #left, #right {
        height: 72vh;
    }
    .noselect {
        -webkit-touch-callout: none; /* iOS Safari */
          -webkit-user-select: none; /* Safari */
           -khtml-user-select: none; /* Konqueror HTML */
             -moz-user-select: none; /* Old versions of Firefox */
              -ms-user-select: none; /* Internet Explorer/Edge */
                  user-select: none; /* Non-prefixed version, currently
                                        supported by Chrome, Edge, Opera and Firefox */
    }
    input[type="button"],
    button,
    .btn {
      cursor: pointer;
    }

    .other-diff,
    .btn {
      font-weight: bold;
      text-decoration: none !important;
      padding: 0.4em 1.2em;
      border-radius: 3px;
      color: #000000;
    }

    .underline {
      position: relative;
    }

    .underline::before {
      content: '';
      position: absolute;
      bottom: 0;
      right: 0;
      width: 0;
      height: 4px;
      background-color: #000000;
      transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1);
    }

    @media (hover: hover) and (pointer: fine) {
      .underline:hover::before{
        left: 0;
        right: auto;
        width: 100%;
      }
    }
CSS;
}

function dd($var, $die=true) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';

    if ($die) die();
    else echo "============<br>\n";
}
