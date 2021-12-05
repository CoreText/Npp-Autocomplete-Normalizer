<?php

require_once('env.php');
require_once('lib/functions.php');

/*
php index.php
php run.php
*/

global $autoCompleteEnvironmentAttributes;
global $fileDist;
global $xmlTemplate;

if (!file_exists($fileSrc)) {
    exit('Failed to open ' . $fileSrc);
}

$xml = simplexml_load_file($fileSrc);

$keyWordsList = $xml->xpath('/NotepadPlus/AutoComplete/KeyWord');
$autoCompleteEnvironment = $xml->xpath('/NotepadPlus/AutoComplete/Environment');

if (is_array($autoCompleteEnvironment) && !empty($autoCompleteEnvironment)) {
    foreach (($autoCompleteEnvironment[0])->attributes() as $attrKey => $attrVal) {
        $autoCompleteEnvironmentAttributes[$attrKey] = (string)$attrVal;
    }
}

$keyWordsListBefore = 'Keywords count before: ' . count($keyWordsList);
$normalized = normalize(xml2array($keyWordsList));
$keyWordsListAfter = 'Keywords count after: ' . count($normalized);

$xmlData = new SimpleXMLElement($xmlTemplate);
// $xmlData->AutoComplete->addAttribute('language', 'PHP');

if (!isset($xmlData->AutoComplete->Environment)) {
    $xmlData->AutoComplete->addChild('Environment');
}

foreach ($autoCompleteEnvironmentAttributes as $attrKey => $attrVal) {
    if ($xmlData->AutoComplete->Environment->attributes() === null)
        $xmlData->AutoComplete->Environment->addAttribute($attrKey, $attrVal);
    else
        $xmlData->AutoComplete->Environment->attributes()->{$attrKey} = $attrVal;
}

array2xml($normalized, $xmlData->AutoComplete);

$validator = new XmlValidator();
$isValidXml = $validator->isXMLContentValid($xmlData->asXML(), '1.0', 'UTF-8');

$xmlFormatted = formatXml($xmlData, $fileDist);
file_put_contents($fileDist, $xmlFormatted);
?>
<link rel="icon" type="image/png" href="/assets/img/site-logo.png">
<style type="text/css"><?php include_once('assets/css/style.css') ?></style>

<?php echo isValidMessage($isValidXml) ?>

<section class="nav-menu">
    <center>
        <form action="#" onsubmit="return false;">
            <p>
                <input type="button" onclick="launch();" value="compute diff">
            </p>
        </form>
        <a class="other-diff underline" href="#other">Other Diff</a>
    </center>
</section>

<section class="first" id="first">
    <div class="outputdiv" id="outputdiv"></div>
    <?php
    $oldXml = escapeXmlForBrowser($xml->asXML());
    $newXml = escapeXmlForBrowser($xmlFormatted);
    echo renderDiff($oldXml, $newXml, true, $keyWordsListBefore, $keyWordsListAfter);
    ?>
</section>

<section class="other-diff" id="other">
    <?php
    $oldXml = escapeXmlForBrowser($xml->asXML(), true);
    $newXml = escapeXmlForBrowser($xmlFormatted, true);
    echo renderDiff($oldXml, $newXml, false, $keyWordsListBefore, $keyWordsListAfter);
    ?>
    <!-- Button on fixed on bottom right corner of the page -->
    <a class="scroll-to scroll-to-top-btn" href="#special-header">☝️ TOP</a>
</section>


<!-- SCRIPTS -->
<!--
<link rel="stylesheet" href="assets/css/idea.min.css">
<script src="assets/js/highlight.min.js"></script>
<script src="assets/js/languages/xml.min.js"></script>
-->
<script type="text/javascript" src="assets/js/diff_match_patch.js"></script>
<script type="text/javascript"><?php include_once('assets/js/script.js') ?></script>
