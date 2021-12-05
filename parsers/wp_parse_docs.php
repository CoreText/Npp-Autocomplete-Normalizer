<?php

/**

php run.php
php index.php

*/

global $fileTemp;
global $fileSrc;
global $xmlTemplate;

$fileDist = BASE_DIR . DIRECTORY_SEPARATOR .'dist'. DIRECTORY_SEPARATOR .'wp'. DIRECTORY_SEPARATOR .'php.xml';


Logger::info('started');

$site = 'https://developer.wordpress.org/reference/functions/';
// $site = 'https://developer.wordpress.org/reference/functions/page/2/';

// $site = __DIR__ . '/templates/wp.html';
// $site = __DIR__ . '/templates/wp_page.html';
// $site = __DIR__ . '/templates/wp_page_1.html';

Logger::info("Request to $site");
$html = file_get_contents("$site");

$links = parseCurrentListingPage($html);
// $links = parseCurrentListingPage(file_get_contents(__DIR__ . '/templates/wp.html'));


function wpParseDocs(array $links, SimpleXMLElement $xmlTemplateObj) {
    if (!empty($links['function_links'])) {
        foreach ($links['function_links'] as $linkKey => $linkHref) {
            sleep(10);

            Logger::info('Goes to the function docs: '. $linkHref);
            $keyWordList = parseCurrentFunctionReferencePage(file_get_contents("$linkHref"));

            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents(__DIR__ . '/templates/wp_page.html'));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents(__DIR__ . '/templates/wp_page_1.html'));
            // $keyWordList = parseCurrentFunctionReferencePage(file_get_contents(__DIR__ . '/templates/wp_register_taxonomy.html'));

            importArrayToNppXml($keyWordList, $xmlTemplateObj);

            sleep(10);
        }

        if (!empty($links['next_page'])) {
            Logger::info('Next listing page request: '. $links['next_page'][0]);

            $links = parseCurrentListingPage(file_get_contents($links['next_page'][0]));

            if (!empty($links['next_page'])) {
                return wpParseDocs($links, $xmlTemplateObj);
            }
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
    Logger::error($e->getMessage() . ', ' . $e->getFile() . ': ' . $e->getLine());
}


///////////////////////////////////////////////////////////////////// FINISH
phpQuery::unloadDocuments();
Logger::info('Finished the DOM parsing! Processing The XML files!');

/*
php run.php
*/
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
    Logger::warn('Something went wrong with XML!!! ' . print_r($validator->getErrors(), true));
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

}
catch (Throwable $e) {
    Logger::error($e->getMessage() .' '. $e->getFile() .':'. $e->getLine());
}

Logger::info('FINISH!');

$keyWordsListBefore = 'Keywords count before: ' . count($keyWordsListSrc);
$keyWordsListAfter  = 'Keywords count after : ' . count($normalizedDist);

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
        $message = 'Can not load the DOM. '. $e->getMessage() . $e->getLine();
        Logger::error($message);
    }

    $pageLinks = [];

    foreach ($dom->find('article.wp-parser-function') as $key => $value) {
        $pq = pq($value);
        $pageLinks['function_links'][] = $pq->find('h1 > a')->attr('href');
    }

    $pageLinks['next_page'][] = $dom->find('.pagination.loop-pagination > .next.page-numbers')->attr('href');
    $dom->unloadDocument();
    Logger::info($pageLinks);

    return $pageLinks;
}

/**
 * Parse current function reference from the DOM.
 *
 * @param string $html
 * @return array
 */
function parseCurrentFunctionReferencePage(string $html): array {
    $dom = phpQuery::newDocumentHTML($html);
    $keyWord = [];
    $paramOptional = 0;

    foreach ($dom->find('article.wp-parser-function') as $key => $value) {
        $pq = pq($value);

        // name of the keyword
        $keyWord['KeyWord']['@attributes']['name'] = getTheKeyWordName(normalizeText(strip_tags($pq->find('h1')->text())));
        $keyWord['KeyWord']['@attributes']['func'] = 'yes';

        // return value
        $keyWord['KeyWord']['Overload']['@attributes']['retVal'] = normalizeText(replaceUnwantedChars($pq->find('.return .return-type')->text()));

        // WP: long description
        $keyWord['KeyWord']['Overload']['@attributes']['descr'] = formatStringLength('&#x0A;WP:&#x20;' . normalizeText($pq->find('.summary > p')->text()), '&#x09;');

        // type $var, multiple params
        $params = $pq->find('.parameters > dl dt');

        foreach ($params as $paramKey => $parmVal) {
            $nextSibling = pq($parmVal->nextElementSibling);

            $keyWord['KeyWord']['Overload']['@attributes']['descr'] .= '&#x0A;&#x0A;' .
                formatStringLength(normalizeText(normalizeNewLines(
                    trim($parmVal->nodeValue) . '&#x20;' .
                    normalizeText($nextSibling->find('.desc > .description')->text())
                )), '&#x09;&#x09;');

            $keyWord['KeyWord']['Overload']['Param'][$paramKey]['@attributes']['name'] = '';
            if (trim($nextSibling->find('.desc > .required')->text()) === '(Optional)') {
                $keyWord['KeyWord']['Overload']['Param'][$paramKey]['@attributes']['name'] .= '[ ';
                $paramOptional++;
            }

            $keyWord['KeyWord']['Overload']['Param'][$paramKey]['@attributes']['name'] .= normalizeText(normalizeNewLines(
                replaceUnwantedChars($nextSibling->find('.desc > .type > span')->text()) . ' ' .
                trim($parmVal->nodeValue) .
                ((normalizeText($nextSibling->find('.default')->text()))
                    ? '=' . normalizeText($nextSibling->find('.default')->text())
                    : '')
            ));

            if ($paramOptional > 0 && $params->count() === ($paramKey + 1)) {
                $keyWord['KeyWord']['Overload']['Param'][$paramKey]['@attributes']['name'] .= str_pad('', $paramOptional, ']');
            }
        }
        $returns = formatStringLength(normalizeText($pq->find('.return .return-type')->parent()->text()), '&#x09;&#x09;');
        $returnType = 'Returns:&#x20;&#x0A;' . ((empty($returns))? '(void)' : $returns);
        $keyWord['KeyWord']['Overload']['@attributes']['descr'] .= formatStringLength('&#x0A;&#x0A;' . $returnType);

        $keyWord['KeyWord']['Overload']['@attributes']['descr'] = normalizeSpaceEntities($keyWord['KeyWord']['Overload']['@attributes']['descr']);

        if (empty($keyWord['KeyWord']['Overload']['@attributes']['retVal']))
            $keyWord['KeyWord']['Overload']['@attributes']['retVal'] = 'void';
    }

    $paramOptional = 0;
    $dom->unloadDocument();

    return $keyWord;
}
