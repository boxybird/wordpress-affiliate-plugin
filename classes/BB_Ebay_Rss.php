<?php

namespace BoxyBird\Classes;

class BB_Ebay_Rss
{
    /**
     * @Reference: https://epn.ebay.com/new/tools/rss-generator
     */

    protected $keywords;

    protected $category_ids;

    protected $program_id;

    protected $campaign_id;

    public function __construct(array $keywords, string $campaign_id, array $category_ids = [], int $program_id = 1)
    {
        $this->keywords     = $keywords;
        $this->program_id   = $program_id;
        $this->category_ids = $category_ids;
        $this->campaign_id  = $campaign_id;
    }

    public function fetch($xml_options = [])
    {
        try {
            $raw_xml = file_get_contents($this->preparedUrl());

            return $this->xmlToArray(simplexml_load_string($raw_xml), $xml_options);
        } catch (Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    protected function preparedUrl()
    {
        $base = 'http://rest.ebay.com/epn/v1/find/item.rss?';

        $params = array_merge($this->buildCategoriesArray(), [
            'keyword'      => $this->buildKeywordsString(),
            'programid'    => $this->program_id,
            'campaignid'   => $this->campaign_id,
            'sortOrder'    => 'BestMatch',
            'toolid'       => '10039',
            'listingType1' => 'All',
            'feedType'     => 'rss',
            'lgeo'         => '1',
        ]);

        return $base . http_build_query($params);
    }

    protected function buildKeywordsString()
    {
        $keywords = array_unique($this->keywords);

        if (count($keywords) > 5) {
            throw new \Exception('Maximum of 5 keywords');
        }

        return '(' . implode(',', $this->keywords) . ')';
    }

    protected function buildCategoriesArray()
    {
        $category_ids = array_unique($this->category_ids);

        if (count($category_ids) > 3) {
            throw new \Exception('Maximum of 3 category ids');
        }

        $categories_array = [];

        foreach ($category_ids as $key => $id) {
            $categories_array['categoryId' . ($key + 1)] = $id;
        }

        return $categories_array;
    }

    protected function xmlToArray(\SimpleXMLElement $xml, $options = [])
    {
        $defaults = [
            'namespaceSeparator' => ':', //you may want this to be something other than a colon
            'attributePrefix'    => '@', //to distinguish between attributes and nodes with the same name
            'alwaysArray'        => [], //array of xml tag names which should always become arrays
            'autoArray'          => true, //only create arrays for tags which appear more than once
            'textContent'        => '$', //key used for the text content of elements
            'autoText'           => true, //skip textContent key if node has no attributes or child nodes
            'keySearch'          => false, //optional search and replace on tag and attribute names
            'keyReplace'         => false //replace values for above search values (as passed to str_replace())
        ];

        $options        = array_merge($defaults, $options);
        $namespaces     = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace
        
        // Get attributes from all namespaces
        $attributesArray = [];
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) {
                    $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                }
                $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }
        
        // Get child nodes from all namespaces
        $tagsArray = [];
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = self::xmlToArray($childXml, $options);

                foreach ($childArray as $key => $value) {
                    $childTagName    = $key;
                    $childProperties = $value;
                }

                //replace characters in tag name
                if ($options['keySearch']) {
                    $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                }
                //add namespace prefix, if any
                if ($prefix) {
                    $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
                }
                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                            ? [$childProperties] : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                    === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = [$tagsArray[$childTagName], $childProperties];
                }
            }
        }

        // Get text content of node
        $textContentArray = [];
        $plainText        = trim((string)$xml);

        if ($plainText !== '') {
            $textContentArray[$options['textContent']] = $plainText;
        }

        // Stick it all together
        $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
        
        // Return node as array
        return [
            $xml->getName() => $propertiesArray
        ];
    }
}
