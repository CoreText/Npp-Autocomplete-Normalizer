<?php

/*
php run.php
*/

require_once(BASE_DIR . '/lib/MySimpleXMLElement.php');

global $xmlTemplate;
global $fileDist;
global $fileSrc;
global $fileTemp;

$fileDist = BASE_DIR . DIRECTORY_SEPARATOR .'dist'. DIRECTORY_SEPARATOR .'wp'. DIRECTORY_SEPARATOR .'php_first-slide-fix.xml';


$xml = new MySimpleXMLElement($fileSrc);

$KeyWordList = $xml->xpath('/NotepadPlus/AutoComplete/KeyWord');
$newKeyWordStructure = wpAddFirstSlide($KeyWordList);

$normalizedList = normalize(xml2array($KeyWordList));
$normalizedTmp = normalize(xml2array($newKeyWordStructure));
$normalizedDist = normalize([...$normalizedList, ...$normalizedTmp]);

$xmlData = new SimpleXMLElement($xmlTemplate);
array2xml($normalizedDist, $xmlData->AutoComplete);

$xmlFormatted = formatXml($xmlData, $fileDist);

file_put_contents($fileDist, $xmlFormatted);


///////////////////////////////////////////////////////////////////// Functions

/**
 * Add first slide with empty description to avoid hint disturbing.
 *
 * @param array $KeyWordList
 * @return array<SimpleXMLElement>
 */
function wpAddFirstSlide(array $KeyWordList): array {
    $KeyWords = [];

    foreach ($KeyWordList as $KeyWordKey => $KeyWordValue) {
        if (!empty($KeyWord = $KeyWordValue->xpath('Overload[1][@descr]'))) {
            foreach ($KeyWord as $OverloadKey => $OverloadVal) {
                if (str_contains((string)$OverloadVal->attributes()->descr, 'WP:')) {
                    $element = clone $KeyWordValue->Overload;
                    unset($element->attributes()->descr);

                    $KeyWords[] = $KeyWordValue->prepend($element);
                }
            }
        }
    }

    return $KeyWords;
}
