<?php

/**
 * Class XmlValidator
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class XmlValidator
{
    private $errors = null;

    /**
     * @param string $xmlFilename Path to the XML file
     * @param string $version 1.0
     * @param string $encoding utf-8
     * @return bool
     */
    public function isXMLFileValid($xmlFilename, $version = '1.0', $encoding = 'utf-8')
    {
        $xmlContent = file_get_contents($xmlFilename);
        return $this->isXMLContentValid($xmlContent, $version, $encoding);
    }

    /**
     * @param string $xmlContent A well-formed XML string
     * @param string $version 1.0
     * @param string $encoding utf-8
     * @return bool
     */
    public function isXMLContentValid($xmlContent, $version = '1.0', $encoding = 'utf-8')
    {
        if (trim($xmlContent) == '') {
            return false;
        }

        libxml_use_internal_errors(true);

        $doc = new DOMDocument($version, $encoding);
        $doc->loadXML($xmlContent);

        $this->errors = $errors = libxml_get_errors();
        libxml_clear_errors();

        return empty($errors);
    }
    
    public function getErrors(): ?array {
        return $this->errors;
    }
}
