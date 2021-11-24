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
// echo renderDiff();


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

<?php echo isValidMessage($isValidXml) ?>
<center>
    <form action="#" onsubmit="return false;">
        <p>
            <input type="button" onclick="launch();" value="compute diff">
        </p>
    </form>
    <a class="other-diff underline" href="#other">Other Diff</a>
</center>

<script type="text/javascript">
const links = document.querySelectorAll(".other-diff");

for (const link of links) {
  link.addEventListener("click", clickHandler);
}

function clickHandler(e) {
  e.preventDefault();
  const href = this.getAttribute("href");
  console.log(href);
  const offsetTop = document.querySelector(href).offsetTop;

  scroll({
    top: offsetTop,
    behavior: "smooth"
  });
}


//////////////////////////////////////////////////////////////////////////


</script>

<!--
<pre>
<?php
echo $keyWordsListBefore . "\n";
echo $keyWordsListAfter  . "\n";
?>
</pre>
-->

<?php

$xmlFormatted = formatXml($xmlData);
$oldXml = escapeXmlForBrowser($xml->asXML());
$newXml = escapeXmlForBrowser($xmlFormatted);
echo renderDiff($oldXml, $newXml, true, $keyWordsListBefore, $keyWordsListAfter);

?>

<div class="outputdiv"></div>

<style type="text/css">
    <?php include_once('assets/css/style.css') ?>
</style>

<script type="text/javascript" src="assets/js/diff_match_patch.js"></script>
<script>
function launch() {
  var text1 = document.getElementById('left').value;
  var text2 = document.getElementById('right').value;

  var dmp = new diff_match_patch();
  dmp.Diff_Timeout = 0;

  // No warmup loop since it risks triggering an 'unresponsive script' dialog
  // in client-side JavaScript
  var ms_start = (new Date()).getTime();
  var d = dmp.diff_main(text1, text2, false);
  var ms_end = (new Date()).getTime();

  var ds = dmp.diff_prettyHtml(d);
  document.getElementById('outputdiv').innerHTML = ds + '<BR>Time: ' + (ms_end - ms_start) / 1000 + 's';
}
</script>

<section id="other"> 
<?php
echo renderDiff($oldXml, $newXml, false, $keyWordsListBefore, $keyWordsListAfter);
?>
</section>

<!--
<link rel="stylesheet" href="assets/css/idea.min.css">
<script src="assets/js/highlight.min.js"></script>
<script src="assets/js/languages/xml.min.js"></script>
-->
<script>
/*
hljs.initHighlightingOnLoad();
if ( typeof oldIE === 'undefined' && Object.keys && typeof hljs !== 'undefined') {
  hljs.initHighlighting();
}
document.querySelectorAll('.code').forEach(el => {
  hljs.highlightElement(el);
});
*/
</script>

<?php


