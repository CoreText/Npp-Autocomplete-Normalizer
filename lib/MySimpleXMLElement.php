<?php

class MySimpleXMLElement extends SimpleXMLElement
{
    public function __construct(
        string $data,
        $options = 0,
        bool $dataIsURL = false,
        string $namespaceOrPrefix = '',
        bool $isPrefix = false
    )
    {
        if (file_exists($data)) {
            $data = file_get_contents($data);
        }

        parent::__construct(
            $data,
            $options,
            $dataIsURL,
            $namespaceOrPrefix,
            $isPrefix
        );
    }

    /**
     * To use addChild in array_walk_recursive().
     *
     * array_walk_recursive($array, [$xml, 'addChildNode']);
     *
     * @param $value
     * @param $name
     * @return SimpleXMLElement
     */
    public function addChildNode($value, $name): SimpleXMLElement
    {
        parent::addChild($value, $name);
        return $this;
    }

    /**
     * Prepend element.
     *
     * @param string $name
     * @param string $value
     * @return ?SimpleXMLElement
     */
    public function prependChild(string $name, string $value): ?SimpleXMLElement
    {
        $dom = dom_import_simplexml($this);

        $new = $dom->insertBefore(
            $dom->ownerDocument->createElement($name, $value),
            $dom->firstChild
        );

        return simplexml_import_dom($new, get_class($this));
    }

    /**
     * Prepend element
     *
     * @return ?SimpleXMLElement
     */
    public function prepend(SimpleXMLElement $element): self
    {
        $parentName = $this->getName();
        $name = $element->getName();

        $newSimpleXMLElement = simplexml_load_string($this->asXML());

        $collection = $newSimpleXMLElement->xpath("$name");
        $collection[] = $element;

        sort($collection);

        $strXML = '';
        foreach ($collection as $item) {
            $strXML .= $item->asXML();
        }
        
        $new = new self("<$parentName>". $strXML . "</$parentName>");
        
        foreach ($this->attributes() as $attrKey => $arttrVal) {
            $new->addAttribute($attrKey, $arttrVal);
        }
        
        return $new;
    }

    /**
     * Wrap with DOMDocument.
     *
     * @param SimpleXMLElement $element
     * @return DOMDocument
     */
    public function wrapWithDomDocument(SimpleXMLElement $element): DOMDocument
    {
        $dom = new DOMDocument;
        $dom->loadXML($element->asXML());

        return $dom;
    }

    public function removeElementByName(string $name): self
    {
        unset($this->{$name});
        return $this;
    }

}
