<?php

// if you want to see warnings comment next line
error_reporting(E_ALL ^ E_WARNING);

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
 * @param SimpleXMLElement $simpleXml
 * @param string $newFile
 * @param bool $save
 * @param bool $format
 * @return string
 */
function formatXml(SimpleXMLElement $simpleXml, string $newFile = 'dist/php.xml', bool $save = true, bool $format = true) {
    $domXml = new DOMDocument('1.0', 'utf-8');

    $domXml->encoding = 'utf-8';

    // Preserve redundant spaces (`true` by default)
    $domXml->preserveWhiteSpace = false;

    // Disable automatic document indentation
    $domXml->formatOutput = false;

    $domXml->substituteEntities = false;

    $renderedXml = $simpleXml->asXML();
    $domXml->loadXML($renderedXml);
    // $domXml->normalizeDocument();

    $domXmlSaved = $domXml->saveXML();

    //$formattedString = $domXml->saveXML();
    $formattedString = ($format)? removeUnwanted(replaceXmlEntities(replaceAmp($domXmlSaved))) : $domXmlSaved;

    if ($save) {
        /** @TODO: Find the way to disable decimal entity substitution */
        $domXml->save($newFile);
        //file_put_contents($newFile, $formattedString);
    }

    return $formattedString;
}

/**
 * Insert XML into a SimpleXMLElement.
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
 * Import keywords list into XML.
 *
 * @TODO: Fix this method. Validation error, because of hex entities.
 *
 * @param array $keyWordList
 * @param SimpleXMLElement $xml
 * @return string of XML
 */
function importArrayToNppXml(array $keyWordList, SimpleXMLElement $xml): string {
    global $fileTemp;

    if (empty($keyWordList)) {
        Logger::error('No keyWord to import!');
    }
    if (empty($xml)) {
        Logger::error('Nowehere to import!');
    }

    $xmlValidator = new XmlValidator();

    if (file_exists($fileTemp)) {
        try {
            $xml = new SimpleXMLElement(file_get_contents($fileTemp));
            $isValidXml = $xmlValidator->isXMLContentValid($xml->asXML(), '1.0', 'UTF-8');

            if (!$isValidXml)
                throw new Exception('The temp file (tmp.xml) is not valid!');
        }
        catch (\Throwable $e) {
            $xml = setupEnvironmentAttributes(initXmlObject());
            //Logger::warning('Error initializing the XML object! ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ') ' . print_r($xmlValidator->getErrors(), true));
        }
    }

    $xmlData = $xml->AutoComplete;

    foreach ($keyWordList as $key => $keyWord) {
        if (is_numeric($key)) {
            $key = 'KeyWord';
        }

        if (!isset($keyWord['@attributes']) || empty($keyWord))
            continue;

        $subNode = $xmlData->addChild($key);
        $subNode->addAttribute('name', $keyWord['@attributes']['name']);
        $subNode->addAttribute('func', $keyWord['@attributes']['func']);

        if (!isset($keyWord['Overload']) || empty($keyWord['Overload']))
            continue;

        if (is_array($keyWord['Overload'])) {
            foreach ($keyWord['Overload'] as $overloadKey => $overloadVal) {
                $subNodeOverload = $subNode->addChild('Overload');
                $subNodeOverload->addAttribute('retVal', normalizeText($keyWord['Overload'][$overloadKey]['@attributes']['retVal']));

                if (isset($keyWord['Overload'][$overloadKey]['@attributes']['descr'])) {
                    $subNodeOverload->addAttribute('descr', normalizeSpaceEntities($keyWord['Overload'][$overloadKey]['@attributes']['descr']));
                }

                if (!empty($keyWord['Overload'][$overloadKey]['Param'])) {
                    foreach ($keyWord['Overload'][$overloadKey]['Param'] as $paramKey => $paramVal) {
                        /** @TODO: fix warning messages appropriately by using SimpleXMLElement API */
                        //if (isset($subNodeOverload[$paramKey])) {
                            $subNodeParam = $subNodeOverload[$paramKey]->addChild('Param');
                            $subNodeParam->addAttribute('name', normalizeText($paramVal['@attributes']['name'], ' '));
                        //}
                    }
                }
            }
        } else {
            $subNodeOverload = $subNode->addChild('Overload');
            $subNodeOverload->addAttribute('retVal', normalizeText($keyWord['Overload']['@attributes']['retVal']));
            $subNodeOverload->addAttribute('descr', normalizeSpaceEntities($keyWord['Overload']['@attributes']['descr']));

            if (!empty($keyWord['Overload']['Param'])) {
                foreach ($keyWord['Overload']['Param'] as $paramKey => $paramVal) {
                    /** @TODO: fix warning messages appropriately by using SimpleXMLElement API */
                    //if (isset($subNodeOverload[$paramKey])) {
                        $subNodeParam = $subNodeOverload[$paramKey]->addChild('Param');
                        $subNodeParam->addAttribute('name', normalizeText($paramVal['@attributes']['name'], ' '));
                    //}
                }
            }
        }
    }

    return formatXml($xml, $fileTemp, true, false);
}

/**
 * Replace XML entities to appropriate format.
 *
 *   Whitespace in XML Content (Not Component Names).
 *   Summary: Whitespace characters are, of course, permitted in XML content.
 *
 *   All of the above whitespace codepoints are permitted in XML content by the
 *   W3C XML BNF for Char:
 *
 *   Char ::= #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
 *
 *   any Unicode character, excluding the surrogate blocks, FFFE, and FFFF.
 *   Unicode code points can be inserted as character references.
 *   Both decimal &#decimal; and hexadecimal &#xhex; forms are supported.
 *
 *   Hexadecimal  Decimal  Unicode Name
 *   &#x09;   or  &#09;    CHARACTER TABULATION
 *   &#x0A;   or  &#10;    LINE FEED (LF)
 *   &#x0D;   or  &#13;    CARRIAGE RETURN (CR)
 *   &#x20;   or  &#32;    SPACE
 *   &#xA0;   or  &#160;   NO-BREAK SPACE
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

    // and normalize spaces in the end
    return preg_replace('/(&#x20;)+/', '&#x20;', $text);
}

/**
 * Fix entities generated by DOMDocument.
 *
 * @param string $text
 * @return string
 */
function replaceAmp(string $text): string {
    $unescapeGibberish = [
        '&amp;amp;'  => '&amp;',
        '&amp;quot;' => '&quot;',
        '&amp;lt;'   => '&lt;',
        '&amp;gt;'   => '&gt;',
        '&amp;#x'    => '&#x',
    ];

    return str_replace(array_keys($unescapeGibberish), $unescapeGibberish, $text);
}

/**
 * Escape ampersand symbol of param.
 *
 * @param $text
 * @return string
 */
function escapeParam(string $text): string {
    $escapes = [
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
        '"' => '&quot;',
        '“' => '&quot;',
        '”' => '&quot;',
    ];
    return str_replace(array_keys($escapes), $escapes, $text);
}

/**
 * Remove unwanted from the document to keep filesize smaller.
 *
 * @param string $text
 * @return string
 */
function removeUnwanted(string $text): string {
    return str_replace([
        ' descr=""',
        '<KeyWord name=""/>',
        '<KeyWord name="" func="yes"/>',
        '<KeyWord name="" func="no"/>',
    ], '', $text);
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

/**
 * Escape the XML.
 *
 * @param $xml
 * @param $htmlFormat
 * @return string
 */
function escapeXmlForBrowser(string $xml, bool $htmlFormat = false): string {
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

function getTheKeyWordName(string $text): string {
    return substr($text, 0, strpos($text, '('));
}

function getTheWord(string $text): string {
    if (($arr = explode('::', $text)) && ($next = next($arr))) {
        return $next;
    }
    return $text;
}

function normalizeText($text, $with = ' ') {
    $unwanted = [
        'Function:'      => $with,
        'Default value:' => $with,
        ' ,'             => ',',
        ' .'             => '.',
        ' = '            => '=',
    ];

    $text = trim(str_replace(array_keys($unwanted), $unwanted, $text));

    // non-breaking space entity, or it's char, or space
    $text = preg_replace("/(&nbsp;| |\s)+/", $with, $text);

    return normalizeNewLines($text);
}

function normalizeSpaceEntities($text, $with = '&#x20;') {
    return preg_replace("/(&#x20;)+/", $with, $text);
}

function normalizeNewLines($text) {
    return trim(preg_replace('/[\r\n]+/', "\n", $text));
}

function removeDuplicateNewLines($text) {
    return trim(preg_replace('/([\r\n][\r\n])+/', "\n", $text));
}

function replaceUnwantedChars($text, $with = '') {
    $unwantedChars = ['(', ')'];
    return trim(str_replace($unwantedChars, $with, $text));
}

function formatStringLength($text, $tabs = '', $length = 100) {
    return wordwrap($text, $length, "&#x0A;$tabs");
}

function trimTrailingSpaces(string $text, $with = ''): string {
    return preg_replace("/[ \t]+$/m", $with, $text);
}

function trimLeadingSpaces(string $text, $with = ''): string {
    return preg_replace("/^[ \t]+/m", $with, $text);
}

function trimAllSpaces(string $text, $with = ''): string {
    return trimTrailingSpaces(trimLeadingSpaces($text, $with), $with);
}

function removeAssignSpaces(string $text): string {
    return str_replace(' = ', '=', $text);
}

/**
 * Replace string with associative array, where
 * key is string to find => value is string replacement.
 *
 * @param array $replacements
 * @param string $subject
 * @return string
 */
function strReplaceAssoc(array $replacements, string $subject): string {
   return str_replace(array_keys($replacements), array_values($replacements), $subject);
}

/**
 * Replace first string occurrence.
 *
 * @param string $str
 * @param string $findStr
 * @param string $replaceWith
 * @param string|array $sanitizeStr  clean string after replacements
 * @param string $sanitizeStrWith    clean string after replacements with string
 * @return string
 */
function replaceFirstMatch(
    string $str,
    string $findStr,
    string $replaceWith = '',
    $sanitizeStr = '',
    string $sanitizeStrWith = '',
    string $sanitizeRegEx = '',
    int $sanitizeRegExLimit = -1
    ): string {
    $pos = strpos($str, $findStr);

    if ($pos !== false) {
        $str = substr_replace($str, $replaceWith, $pos, strlen($findStr));
        $str = str_replace($sanitizeStr, $sanitizeStrWith, $str);

        if ($sanitizeRegEx) {
            $str = preg_replace($sanitizeRegEx, $sanitizeStrWith, $str, $sanitizeRegExLimit);
        }
    }

    return $str;
}

/**
 * Check array values duplicates.
 *
 * @param array $inputArray
 * @return bool - true if has duplicates
 */
function arrayHasDuplicates(array $inputArray): bool {
    return count($inputArray) !== count(array_flip($inputArray));
}

/**
 * Array with range of numbers at random order.
 *
 * @param int $min = 5
 * @param int $max = 50
 * @param int $quantity = 5
 * @return array
 */
function uniqueRandomNumbersWithinRange(int $min = 5, int $max = 20, int $quantity = 5): array {
    $numbers = range($min, $max);
    shuffle($numbers);
    return array_slice($numbers, 0, $quantity);
}

/**
 * Unique numeric key.
 *
 * @param int $min
 * @param int $max
 * @param int $quantity
 * @return int
 */
function uniqueNumericKey(int $min = 5, int $max = 20, int $quantity = 5): int {
    return (int)implode('', uniqueRandomNumbersWithinRange($min, $max, $quantity));
}

////////////////////////////////////////////////////////////////////////// Misc

/**
 * Dump and die.
 *
 * @param $var
 * @param $html
 * @param $die
 * @return string
 */
function dd($var, $html = true, $die = true) {
    static $count = 1;

    if ($html)
        print("\n<pre>\n");
    else
        print(PHP_EOL);

    var_dump($var);

    if ($html)
        print("\n</pre>\n");
    else
        print(PHP_EOL);

    if (is_bool($die) && $die)
        die();

    if (is_numeric($die)) {
        if ($die === $count)
            die("\n\nDUMP TIMES: $count\n");

        $count++;
    }
}

/**
 * Print and die.
 *
 * @param $var
 * @param $html
 * @param $die
 * @return string
 */
function pd($var, $html = true, $die = true) {
    static $count = 1;

    if ($html)
        print("\n<pre>\n");
    else
        print(PHP_EOL);

    print_r($var);

    if ($html)
        print("\n</pre>\n");
    else
        print(PHP_EOL);

    if (is_bool($die) && $die)
        die();

    if (is_numeric($die)) {
        if ($die === $count)
            die("\n\nPRINT TIMES: $count\n");

        $count++;
    }
}

/**
 * Dump the data using dd()
 *
 * @param $var
 * @param $html
 * @param $die
 * @return string
 */
function dump($var, $html = true, $die = false) {
    return dd($var, $html, $die);
}

/**
 * Print the data using pd()
 *
 * @param $var
 * @param $html
 * @param $die
 * @return string
 */
function printer($var, $html = true, $die = false) {
    return dd($var, $html, $die);
}
