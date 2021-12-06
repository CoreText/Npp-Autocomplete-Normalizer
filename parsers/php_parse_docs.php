<?php

/**
 * Zeal docs could be downloaded here https://zealdocs.org/
 * Create symbolic link of `docsets/` folder and place it inside the `zeal.docs`
 * host folder that should be served.
 * Run the web server to serve the pages.
 * Remove file contents of `tmp/tmp.xml`
 * Run the parser `php run.php`
 * The result file you can find in here `dist/php.net/php.xml`
 * When the parser will finish it's work copy the result file to `src/php.xml`
 * and run `php index.php` to normalize it's content.
 * After that use normalized result in `dist/php.xml`.
 *
 * Used PHP 8.0.1 (cli) (built: Jan  5 2021 23:43:39) (ZTS Visual C++ 2019 x64)
 *

php run.php
php index.php
*/

global $fileTemp;
global $fileSrc;
global $xmlTemplate;

$fileDist = BASE_DIR . DIRECTORY_SEPARATOR .'dist'. DIRECTORY_SEPARATOR .'php.net'. DIRECTORY_SEPARATOR .'php.xml';

Logger::info('started');

$baseUrl = 'http://zeal.docs/docsets/PHP.docset/Contents/Resources/Documents/www.php.net/manual/en';
$site = "$baseUrl/indexes.functions.html";

Logger::info("Request to $site");
$html = file_get_contents("$site");
$links = parseCurrentListingPage($html);

function wpParseDocs(array $links, SimpleXMLElement $xmlTemplateObj) {
    global $baseUrl;

    if (!empty($links['function_links'])) {
        foreach ($links['function_links'] as $linkKey => $linkHref) {
            // sleep(2);

            Logger::info('Goes to page: '. $linkHref);
            $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/$linkHref"));

            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/function.apcu-add.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/function.abs.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/appenditerator.key.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/apcuiterator.construct.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/appenditerator.construct.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/function.array-slice.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/function.next.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/norewinditerator.next.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/mysqli.affected-rows.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/function.str-replace.html"));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$baseUrl/function.apcu-store.html"));

            importArrayToNppXml($keyWordList, $xmlTemplateObj);

            // sleep(2);
        }
    } else {
        Logger::error('The links not found!');
    }
}

try {
    $xmlObj = setupEnvironmentAttributes(initXmlObject());
    wpParseDocs($links, $xmlObj);
}
catch (Throwable $e) {
    Logger::error($e->getMessage() .', '. $e->getFile() .': '. $e->getLine());
}


///////////////////////////////////////////////////////////////////////// PARSED
phpQuery::unloadDocuments();
Logger::info('Finished the DOM parsing! Processing The XML files!');


if (!file_exists($fileTemp)) {
    exit('Failed to open temp file ' . $fileTemp);
}

if (!file_exists($fileSrc)) {
    exit('Failed to open source file ' . $fileSrc);
}

$validator = new XmlValidator();

try {
    $xmlTmp = simplexml_load_file($fileTemp);
}
catch (Throwable $e) {
    $isValidXml = $validator->isXMLContentValid(file_get_contents($fileTemp), '1.0', 'UTF-8');
    Logger::warn('Something went wrong with XML!!! '. print_r($validator->getErrors(), true));
}

try {
    $xmlSrc = parseEnvironmentAttributes(simplexml_load_file($fileSrc));

    $keyWordsListTmp = $xmlTmp->xpath('/NotepadPlus/AutoComplete/KeyWord');
    $keyWordsListSrc = $xmlSrc->xpath('/NotepadPlus/AutoComplete/KeyWord');

    $normalizedSrc = normalize(xml2array($keyWordsListSrc));
    $normalizedTmp = normalize(xml2array($keyWordsListTmp));

    $normalizedDist = normalize([...$normalizedSrc, ...$normalizedTmp]);

    $xmlData = new SimpleXMLElement($xmlTemplate);
    array2xml($normalizedDist, $xmlData->AutoComplete);

    $xmlFormatted = formatXml($xmlData, $fileDist);

    file_put_contents($fileDist, $xmlFormatted);

    echo 'Keywords count before: ' . count($keyWordsListSrc) . PHP_EOL;
    echo 'Keywords count after : ' . count($normalizedDist)  . PHP_EOL;
}
catch (Throwable $e) {
    Logger::error($e->getMessage() .' '. $e->getFile() .':'. $e->getLine());
}

Logger::info('FINISH!');
echo PHP_EOL;
echo implode(PHP_EOL, $links['function_links']);
//echo $xmlFormatted;



///////////////////////////////////////////////////////////////////// Functions

/**
 * Parse current listing of functions.
 *
 * @param string $html
 * @return array of links
 */
function parseCurrentListingPage(string $html): array {
    try {
        $dom = phpQuery::newDocumentHTML($html);
    } catch(Throwable $e) {
        Logger::error(
            'Can not load the DOM. '.
            $e->getMessage() .' '. $e->getFile() .':'. $e->getLine()
        );
    }

    $pageLinks = [];
    foreach ($dom->find('ul.gen-index.index-for-refentry > li.gen-index > ul') as $key => $value) {
        $pq = pq($value);

        foreach ($pq->find('li > a') as $listItem => $link) {
            $pageLinks['function_links'][] = pq($link)->attr('href');
        }
    }

    $dom->unloadDocument();
    Logger::info($pageLinks);

    return $pageLinks;
}

/**
 * Parse current function reference from the DOM.
 *
 * The docs should be shown on the second slide of the param hint tooltip,
 * because the hint tooltip could be too large and disturbing.
 *
 * Other slides representing function with given specific params to show
 * specific return value.
 *
 * @param string $html
 * @return array
 */
function parseCurrentFunctionReferencePage(string $html): array {
    $dom = phpQuery::newDocumentHTML($html);
    $keyWord = [];

    // name of the keyword
    if ($dom->find('h1.refname')->count() > 1) {
        foreach ($dom->find('h1.refname') as $kewordKey => $keywordVal) {
            $keywords[] = trim($keywordVal->nodeValue);
        }

        $keyWord['KeyWord']['@attributes']['name'] = $keywords[1];
        $keyWord = getKeyWordStructure($keyWord, $dom);

        // to avoid key collisions in $keyWordList when generating the XML
        $theKey = uniqueNumericKey();

        if (str_contains($keywords[0], '$')) {
            $keyWord[$theKey]['@attributes']['name'] = $keywords[0];
            $keyWord[$theKey]['@attributes']['func'] = 'no';

            $theKey = uniqueNumericKey();
            $keyWord[$theKey]['@attributes']['name'] = getTheWord(str_replace('$', '', $keywords[0]));
            $keyWord[$theKey]['@attributes']['func'] = 'no';
        } else {
            $keyWord[$theKey]['@attributes']['name'] = $keywords[0];
            $keyWord[$theKey]['@attributes']['func'] = 'yes';
        }

        if ($keyWord[$theKey]['@attributes']['func'] === 'yes' && isset($keyWord['KeyWord']['Overload'])) {
            $keyWord[$theKey]['Overload'] = $keyWord['KeyWord']['Overload'];
        }
    } else {
        $keyWord['KeyWord']['@attributes']['name'] = trim($dom->find('h1.refname')->text());
        $keyWord = getKeyWordStructure($keyWord, $dom);
    }

    $dom->unloadDocument();

    return $keyWord;
}

/**
 * Get the keyword structure.
 *
 * @param array $keyWord
 * @param phpQueryObject &$dom
 * @return array
 */
function getKeyWordStructure(array $keyWord, phpQueryObject &$dom): array {
    $retVal = [];
    $entries = [];
    $paramTypes = [];
    $paramOptionalCount = 0;
    $multiMethodSynopsis = false;
    $hasInterfaceMethod = false;

    // offset, to show the docs on the second slide of the tooltip.
    $offset = 1;

    if (!isset($keyWord['KeyWord']['@attributes']['name'])) {
        Logger::error('The name does not exist!'. print_r($keyWord, true));
        return $keyWord;
    }

    $name = $keyWord['KeyWord']['@attributes']['name'];

    if (strpos($name, '::') > 0) {
        $entries = explode('::', $name);

        if ($entries[1] !== '') {
            if ($entries[1] === '__construct') {
                $keyWord['KeyWord']['@attributes']['name'] = $entries[0];
                $keyWord['KeyWord']['Overload'][0]['@attributes']['retVal'] = 'self';
                $keyWord['KeyWord']['Overload'][1]['@attributes']['retVal'] = 'self';
            } else {
                //$keyWord['KeyWord']['@attributes']['name'] = $entries[1];
                //$offset = uniqueNumericKey();
                //$keyWord['KeyWord']['Overload'][$offset]['@attributes']['retVal'] = $entries[0] . ' ';
                //$hasInterfaceMethod = true;

                $keyWord['KeyWord']['@attributes']['name'] = $name;
                $keyWord['KeyWord']['Overload'][0]['@attributes']['retVal'] = '';
                $keyWord['KeyWord']['Overload'][1]['@attributes']['retVal'] = '';
            }
        }
    } else {
        $keyWord['KeyWord']['Overload'][0]['@attributes']['retVal'] = '';
        $keyWord['KeyWord']['Overload'][1]['@attributes']['retVal'] = '';
    }

    $keyWord['KeyWord']['@attributes']['func'] = 'yes';

    if ($dom->find('.methodsynopsis.dc-description')->count() > 1) {
        $multiMethodSynopsis = true;
    }

    foreach ($dom->find('.methodsynopsis.dc-description') as $key => $value) {
        $pq = pq($value);

        $currentRetVal = trim($pq->find(' > span.type')->text());

        // return value
        $retVal[] = $currentRetVal;
        if ($multiMethodSynopsis) {
            $keyWord['KeyWord']['Overload'][$key + $offset + 1]['@attributes']['retVal'] = $currentRetVal;
        }

        // type $var, multiple params
        $params = $pq->find('.methodparam');

        foreach ($params as $paramKey => $parmVal) {
            $paramText = trim($parmVal->textContent);
            $paramOptionalText = '';

            if ($parmVal->childElementCount > 2) {
                $paramOptionalText = '[ ' . $paramText;
                $paramOptionalCount++;
            }

            if ($paramOptionalCount > 0 && $params->count() === ($paramKey + 1)) {
                $paramOptionalText .= str_pad('', $paramOptionalCount, ']');
            }

            $pText = (($paramOptionalText === '')? $paramText : $paramOptionalText);

            if ($multiMethodSynopsis) {
                $keyWord['KeyWord']['Overload'][$key + $offset + 1]['Param'][$paramKey]['@attributes']['name'] = escapeParamAmps($pText);
            } else {
                $keyWord['KeyWord']['Overload'][0]['Param'][$paramKey]['@attributes']['name'] = escapeParamAmps($pText);
            }

            $paramTypes[$paramKey][] = $pText;
        }

        $paramOptionalCount = 0;
    }

    $returnVals = implode('|', $retVal);

    $keyWord['KeyWord']['Overload'][$offset]['@attributes']['descr'] = '';

    //if ($hasInterfaceMethod) {
    //    $keyWord['KeyWord']['Overload'][$offset]['@attributes']['retVal'] .= $returnVals;
    //
    //    // description
    //    $keyWord['KeyWord']['Overload'][$offset]['@attributes']['descr'] = formatStringLength('&#x0A;' . normalizeText($dom->find('.refpurpose .dc-title')->text()), '&#x09;');
    //
    //    foreach ($paramTypes as $paramTypesKey => $paramTypesVal) {
    //        $keyWord['KeyWord']['Overload'][$offset]['Param'][$paramTypesKey]['@attributes']['name'] = escapeParamAmps(normalizeParamEntries($paramTypesVal));
    //    }
    //} else {
        $keyWord['KeyWord']['Overload'][0]['@attributes']['retVal'] .= $returnVals;
        $keyWord['KeyWord']['Overload'][$offset]['@attributes']['retVal'] .= $returnVals;

        // description
        $keyWord['KeyWord']['Overload'][$offset]['@attributes']['descr'] = formatStringLength('&#x0A;' . normalizeText($dom->find('.refpurpose .dc-title')->text()), '&#x09;');

        foreach ($paramTypes as $paramTypesKey => $paramTypesVal) {
            $keyWord['KeyWord']['Overload'][$offset]['Param'][$paramTypesKey]['@attributes']['name'] = escapeParamAmps(normalizeParamEntries($paramTypesVal));
        }
    //}

    // fix the order for tooltip slides
    ksort($keyWord['KeyWord']['Overload']);

    // copy generated params of the native function to the first slide
    //if (!$hasInterfaceMethod && isset($keyWord['KeyWord']['Overload'][1]['Param'])) {
    //    $keyWord['KeyWord']['Overload'][0]['Param'] = $keyWord['KeyWord']['Overload'][1]['Param'];
    //}

    foreach ($dom->find('div.refsect1.parameters > dl > dt') as $paramKey => $paramVal) {
        $pqParam = pq($paramVal);
        $p = trim($pqParam->find('> .parameter')->text());
        $pDescr = formatStringLength(normalizeText($pqParam->next()->find('.para')->text()), '&#x09;');
        $keyWord['KeyWord']['Overload'][$offset]['@attributes']['descr'] .= '&#x0A;&#x0A;$'. $p . '&#x0A;&#x09;' . $pDescr;
    }

    $returns = trim($dom->find('.returnvalues > .para')->text());

    $returnType = 'Returns:&#x20;' . ((empty($returns))? '(void)' : '&#x0A;&#x09;' . formatStringLength(normalizeText($returns), '&#x09;'));

    // append string with return type to the second slide
    $keyWord['KeyWord']['Overload'][$offset]['@attributes']['descr'] .= '&#x0A;&#x0A;' . $returnType;

    //if ($hasInterfaceMethod && $returns === 'No value is returned.') {
    //    $keyWord['KeyWord']['Overload'][$offset]['@attributes']['retVal'] = 'void';
    //}

    if (/* !$hasInterfaceMethod && */ $returns === 'No value is returned.' || $keyWord['KeyWord']['Overload'][0]['@attributes']['retVal'] === '') {
        $keyWord['KeyWord']['Overload'][0]['@attributes']['retVal'] = 'void';
        $keyWord['KeyWord']['Overload'][1]['@attributes']['retVal'] = 'void';
    }

    // fix first slide of hint for multi-method synopsis page reference
    if (isset($keyWord['KeyWord']['Overload'][1]['Param']) && !isset($keyWord['KeyWord']['Overload'][0]['Param'])) {
        $keyWord['KeyWord']['Overload'][0]['Param'] = $keyWord['KeyWord']['Overload'][1]['Param'];
    }

    if (is_array($keyWord['KeyWord']['Overload']) && count($keyWord['KeyWord']['Overload']) > 2) {
        $keyWord['KeyWord']['Overload'] = array_values($keyWord['KeyWord']['Overload']);
    }

    return $keyWord;
}

/**
 * Fix optional params signature (square brackets).
 *
 * @param array $paramTypesValues
 * @return string
 */
function normalizeParamEntries(array $paramTypesValues): string {
    $currentParamName = implode('|', $paramTypesValues);
    $currentParamName = str_replace([
            ']|[ ',
            ']|'  ,
        ],
        '|',
        $currentParamName
    );

    if (str_contains($currentParamName, '|[ ')) {
        $currentParamName = '[ ' . str_replace('|[ ', '|', $currentParamName);
    }

    return uniqueParamType($currentParamName);
}

/**
 * Unique param types for multi-param string.
 *
 * @TODO: implement unique param.
 * @param string $paramTypes
 * @return string
 */
function uniqueParamType(string $paramType): string {
    $paramString = trim(preg_replace("/[\[\]]/", '', $paramType));
    $entries = explode('|', $paramString);

    if (isset($entries[0]) && arrayHasDuplicates($entries)) {
        $paramType = replaceFirstMatch($paramType, $entries[0], '', '[ |', '[ ');
    }

    return $paramType;
}
