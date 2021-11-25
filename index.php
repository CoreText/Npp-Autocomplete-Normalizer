<?php

require('lib/functions.php');
require('lib/XmlValidator.php');

/*
SimpleXMLElement
DOMХpath
*/

$file = 'src/php.xml';
if (!file_exists($file)) {
    exit('Failed to open ' . $file);
}

$xml = simplexml_load_file($file);

$keyWordsList = $xml->xpath('/NotepadPlus/AutoComplete/KeyWord');

$keyWordsListBefore = 'Keywords count before: ' . count($keyWordsList);
$sortedKeyWordsList = sortKeyWordsByAttribute(xml2array($keyWordsList));
$normalized = normilizeKeyWordElement($sortedKeyWordsList);
$keyWordsListAfter = 'Keywords count after: ' . count($normalized);

// print_r(count($normalized));

$xmlTemplate = <<<XML_RENDER
<?xml version="1.0" encoding="UTF-8" ?>
<!--
WordPress AutoComplete for Notepad++
@author takien - http://takien.com
@author Texter CoreText
@version 1.0 (contains about 151 WordPress functions)
It's mixed with and based on PHP AutoComplete by Geoffray Warnants - http://www.geoffray.be (version 1.35.20100625)
-->
<NotepadPlus>
    <AutoComplete>
    <!--
    $keyWordsListBefore
    $keyWordsListAfter
    -->
    </AutoComplete>
</NotepadPlus>
XML_RENDER;

$xmlData = new SimpleXMLElement($xmlTemplate);
array2xml($normalized, $xmlData->AutoComplete);

$isValidXml = (new XmlValidator())->isXMLContentValid($xmlData->asXML(), '1.0', 'UTF-8');

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

<!--
<pre>
<?php
echo $keyWordsListBefore . "\n";
echo $keyWordsListAfter  . "\n";
?>
</pre>
-->

<section class="first" id="first">
    <div class="outputdiv" id="outputdiv"></div>
    <?php

    $xmlFormatted = formatXml($xmlData);
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
    <a class="scroll-to scrollToTopBtn" href="#special-header">☝️ TOP</a>
</section>


<!-- SCRIPTS -->
<!--
<link rel="stylesheet" href="assets/css/idea.min.css">
<script src="assets/js/highlight.min.js"></script>
<script src="assets/js/languages/xml.min.js"></script>
-->
<script type="text/javascript" src="assets/js/diff_match_patch.js"></script>
<script type="text/javascript"><?php include_once('assets/js/script.js') ?></script>
