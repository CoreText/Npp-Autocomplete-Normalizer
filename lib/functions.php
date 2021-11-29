<?php

require_once(BASE_DIR . '/lib/XmlValidator.php');

global $autoCompleteEnvironmentAttributes;

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

/**
 * SimpleXMLElement to array.
 *
 * @param SimpleXMLElement $xml
 * @return array
 */
function xmlToArray($xml): array {
    $obj = simplexml_load_string($xml);
    $json = json_encode($obj, JSON_FORCE_OBJECT, 1024);
    return json_decode($json, JSON_OBJECT_AS_ARRAY, 1024);
}

/**
 * Attempts to traverse SimpleXMLElement to build XML structure using recursion.
 *
 * @param $data
 * @param $xmlData
 * @return void
 */
function array2xml($data, &$xmlData) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (is_numeric($key)) {
                $key = 'KeyWord';
                $subnode = $keyWord = $xmlData->addChild('KeyWord');

                if (is_array($value['@attributes'])) {
                    foreach ($value['@attributes'] as $keyWordKey => $keyWordVal) {
                        $keyWord->addAttribute($keyWordKey, $keyWordVal);
                    }
                }
            }

            if ($key === 'Overload') {
                if (is_array($value)) {
                    if (hasNumericKeys($data[$key])) {
                        foreach ($value as $overloadKey => $overloadVal) {
                            $subnode = $overload = $xmlData->addChild('Overload');
                            if (!is_array($overloadVal) && !is_string($overloadVal)) {
                                $overload['retVal'] = (string)$overloadVal->attributes()->retVal;
                                $overload['descr'] = (string)$overloadVal->attributes()->descr;
                            }

                            if (isset($overloadVal->Param)) {
                                foreach ($overloadVal->Param as $paramKey => $paramVal) {
                                    $subnode = $param = $overload->addChild('Param');
                                    $param->addAttribute('name', (string)$paramVal->attributes()->name);
                                }
                            }
                        }
                    }
                    else {
                        $subnode = $overload = $xmlData->addChild($key);
                        foreach ($value as $ok => $ov) {
                            if ($overload->attributes()->count() === 0) {
                                if (isset($value['@attributes']['retVal'])) {
                                    $overload->addAttribute('retVal', $value['@attributes']['retVal']);
                                }
                                if (isset($value['@attributes']['descr'])) {
                                   $overload->addAttribute('descr', $value['@attributes']['descr']);
                                }
                            }

                            if (hasNumericKeys($ov)) {
                                foreach ($ov as $paramKey => $paramVal) {
                                    $subnode = $param = $overload->addChild('Param');
                                    $param->addAttribute('name', $paramVal->attributes()->name);
                                }
                            }
                        }

                        if (isset($value['Param']['@attributes'])) {
                            $subnode = $param = $overload->addChild('Param');
                            $param->addAttribute('name', $value['Param']['@attributes']['name']);

                        }

                    }
                }
                else {
                    if ($overloadKey !== '@attributes' && $overloadKey !== 'Param') {
                        $overload = $xmlData->addChild('Overload');
                        foreach ($data['Overload'] as $overloadKey => $overloadVal) {
                            $overload->attributes()->retVal = (string)$overloadVal->attributes()->retVal;
                            $overload->attributes()->descr = (string)$overloadVal->attributes()->descr;
                            $overload->attributes()->wat = '';
                        }
                    }
                }
            }

            array2xml($value, $subnode);
        }
    }
}

/**
 * Format the XML document.
 * Saves XML.
 *
 * @param $simpleXml
 * @param $save
 * @return string
 */
function formatXml($simpleXml, string $newFile = 'dist/php.xml', bool $save = true) {

    $domXml = new DOMDocument('1.0', 'utf-8');

    $domXml->encoding = 'utf-8';
    $domXml->preserveWhiteSpace = false;
    $domXml->formatOutput = true;
    $domXml->substituteEntities = false;

    /* @var $xml SimpleXMLElement */
    $renderedXml = $simpleXml->asXML();
    $domXml->loadXML($renderedXml);
    // $domXml->normalizeDocument();

    //$formattedString = $domXml->saveXML();
    $formattedString = replaceXmlEntities(replaceAmp($domXml->saveXML()));

    if ($save) {
        /** @TODO: Find the way to disable decimal entity substitution */
        $domXml->save($newFile);
        //file_put_contents($newFile, $formattedString);
    }

    return $formattedString;
}

/**
 * Insert XML into a SimpleXMLElement
 *
 * @param SimpleXMLElement $parent
 * @param string $xml
 * @param bool $before
 * @return bool XML string added
 */
function simplexmlImportXml(SimpleXMLElement $parent, $xml, $before = false) {
    $xml = (string)$xml;

    // check if there is something to add
    if ($nodata = !strlen($xml) or $parent[0] == NULL) {
        return $nodata;
    }

    // add the XML
    $node     = dom_import_simplexml($parent);
    $fragment = $node->ownerDocument->createDocumentFragment();
    $fragment->appendXML($xml);

    if ($before) {
        return (bool)$node->parentNode->insertBefore($fragment, $node);
    }

    return (bool)$node->appendChild($fragment);
}

/**
 * Replace XML entities to appropriate format.
 *
 * @param string $str
 * @return string
 */
function replaceXmlEntities(string $str): string {

    $replacements = [
        /*Decimal => Hexadecimal that Npp understand*/
        '&#9;'    => '&#x09;', // CHARACTER TABULATION
        '&#10;'   => '&#x0A;', // LINE FEED (LF)
        '&#13;'   => '&#x0D;', // CARRIAGE RETURN (CR)
        '&#32;'   => '&#x20;', // SPACE
        '&#160;'  => '&#xA0;', // NO-BREAK SPACE
        '&#32;'   => '&#x20;', // SPACE
    ];

    // replace entities
    $text = str_replace(array_keys($replacements), $replacements, $str);

    // replace quotes
    $text = str_replace(['“', '”',], '&quot;', $text);

    // and normalize spaces in the end
    return preg_replace('/(&#x20;)+/', '&#x20;', $text);
}

function replaceAmp($text) {
    return str_replace('&amp;#x', '&#x', $text);
}

/**
 * Check if array has numerical keys.
 *
 * @param $arr
 * @return bool
 */
function hasNumericKeys($arr) {
    return array_values($arr) === $arr;
}

/**
 * Sort keywords by attribute.
 *
 * @param array $keyWords
 * @param string $attr  default is 'name'
 * @return array
 */
function sortKeyWordsByAttribute(array $keyWords, string $attr = 'name'): array {
    usort($keyWords, static function ($item1, $item2) use ($attr) {
        if ($item1['@attributes'][$attr] === $item2['@attributes'][$attr]) {
            return 0;
        }

        return ($item1['@attributes'][$attr] < $item2['@attributes'][$attr])
            ? -1 : 1;
    });

    return $keyWords;
}

/**
 * Add missing attributes and make unique.
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
 * Normalize the list of keywords.
 *
 * @param $list
 * @return array
 */
function normalize(array $list): array {
    return normilizeKeyWordElement(sortKeyWordsByAttribute($list));
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
 * @TODO: implement multi dimensional diff.
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
        'old' => "<code class='code language-xml left' spellcheck='false' autocomplete='off' contenteditable='true'>$start<span class='cb-red'>$oldDiff</span>$end</code>",
        'new' => "<code class='code language-xml right' spellcheck='false' autocomplete='off' contenteditable='true'>$start<span class='cb-green'>$newDiff</span>$end</div>",
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
        'old' => "<textarea class='left'  id='left' spellcheck='false' autocomplete='off' contenteditable='true'>$old</textarea>",
        'new' => "<textarea class='right' id='right' spellcheck='false' autocomplete='off' contenteditable='true'>$new</textarea>",
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

    if ($other)
        $diff = getDecoratedDiff($stringOld, $stringNew);
    else
        $diff = otherDecoratedDiff($stringOld, $stringNew);

    return <<<DIFF
<div class="diff">
    <table class="diff-table">
        <tr>
            <th class="sticky-header">OLD ($keyWordsListBefore)</th>
            <th class="sticky-header">NEW ($keyWordsListAfter) Normalized Version</th>
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
    if ($isValidXml)
        return '<h1 class="special-header noselect cb-green" id="special-header">The XML is valid: ' . print_r($isValidXml, true) . '!</h1>';
    else
        return '<h1 class="special-header cb-red" id="special-header">The XML is not valid!<br><pre>' . print_r($isValidXml, true) . '</pre></h1>';
}

function escapeXmlForBrowser($xml, $htmlFormat = false) {
    if ($htmlFormat)
        return '<pre>' . htmlspecialchars($xml) . '</pre>';

    return htmlspecialchars($xml);
}

/**
 * Pass vars to some php include.
 *
 * @param $filePath
 * @param $variables
 * @param $print
 * @return mixed
 */
function includeWithVariables($filePath, $variables = [], $print = true) {
    $output = NULL;

    if (file_exists($filePath)) {
        // Extract the variables to a local namespace
        extract($variables);

        // Start output buffering
        ob_start();

        // Include the template file
        include $filePath;

        // End buffering and return its contents
        $output = ob_get_clean();
    }
    else {
        exit('The requested file does not exist!');
    }

    if ($print) {
        print $output;
    }

    return $output;
}

//////////////////////////////////////////////////////////////////////////

/**
 * Init the XML document.
 *
 * @return SimpleXMLElement
 */
function initXmlObject(): SimpleXMLElement {
    global $fileTemp;
    global $xmlTemplate;

    //$fp = fopen($fileTemp, 'w+'); fclose($fp);

    if (file_exists($fileTemp)) {
        $fileContents = file_get_contents($fileTemp);
        $isValidXml = (new XmlValidator())->isXMLContentValid($fileContents, '1.0', 'UTF-8');

        if ($isValidXml)
            $xmlTemplate = $fileContents;
    }

    return parseEnvironmentAttributes(new SimpleXMLElement($xmlTemplate));
}

function parseEnvironmentAttributes(SimpleXMLElement $xml): SimpleXMLElement {
    global $autoCompleteEnvironmentAttributes;
    $autoCompleteEnvironment = $xml->xpath('/NotepadPlus/AutoComplete/Environment');

    if (is_array($autoCompleteEnvironment) && !empty($autoCompleteEnvironment)) {
        foreach (($autoCompleteEnvironment[0])->attributes() as $attrKey => $attrVal) {
            $autoCompleteEnvironmentAttributes[$attrKey] = (string)$attrVal;
        }
    }

    return $xml;
}

function setupEnvironmentAttributes(SimpleXMLElement $xmlData): SimpleXMLElement {
    global $autoCompleteEnvironmentAttributes;

    if (!isset($xmlData->AutoComplete->Environment)) {
        $xmlData->AutoComplete->addChild('Environment');
    }

    foreach ($autoCompleteEnvironmentAttributes as $attrKey => $attrVal) {
        if ($xmlData->AutoComplete->Environment->attributes() === null)
            $xmlData->AutoComplete->Environment->addAttribute($attrKey, $attrVal);
        else
            $xmlData->AutoComplete->Environment->attributes()->{$attrKey} = $attrVal;
    }
    return $xmlData;
}

function getTheKeyWordName($text) {
    return substr($text, 0, strpos($text, '('));
}

function normalizeText($text, $with = " ") {
    $text = trim(str_replace(['Function:', 'Default value:'], $with, $text));
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/(&nbsp;| )+/', ' ', $text);
    $text = str_replace(' ,', ',', $text);
    $text = str_replace(' .', '.', $text);

    return trim(normalizeNewLines($text));
}

function normalizeSpaceEntities($text, $with = '&#x20;') {
    return str_replace("(&#x20;)+", $with, $text);
}

function normalizeNewLines($text) {
    return trim(preg_replace('/[\r\n]+/', "\n", $text));
}

function replaceUnwantedChars($text, $with = '') {
    return trim(str_replace(['(', ')'], $with, $text));
}

function formatStringLength($text, $tabs = '', $length = 100) {
    return wordwrap($text, $length, "&#x0A;$tabs");
}


////////////////////////////////////////////////////////////////////////// Misc

/**
 * Dump and die.
 *
 * @param $var
 * @param $die
 * @return mixed
 */
function dd($var, $die = true) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';

    if ($die) die();
}
