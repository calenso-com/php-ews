<?php

namespace garethp\ews\API\Message\ResponseMessageType;

use garethp\ews\API\Message\ResponseMessageType;

/**
 * Class representing MessageXmlAType
 */
class MessageXmlAType extends ResponseMessageType
{
    /**
     * Decoded children of the MessageXml element, kept exactly as SoapClient
     * produced them (e.g. Type\ValueType instances for <t:Value> entries).
     *
     * @var array
     */
    protected $values = [];

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param mixed $values
     * @return MessageXmlAType
     */
    public function setValues($values)
    {
        $this->values = is_array($values) ? $values : [$values];
        return $this;
    }

    /**
     * Build an instance from the raw structure SoapClient produces for the
     * MessageXml element. Its XSD type is an anonymous complexType wrapping
     * xs:any, so it has no classmap entry and arrives as a stdClass with a
     * single "any" member (or, defensively, a plain array / lone value)
     * instead of as a MessageXmlAType.
     *
     * @param mixed $value
     * @return self
     */
    public static function fromUnmappedXml($value)
    {
        $messageXml = new self();

        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            $value = array_key_exists('any', $properties) ? $properties['any'] : $properties;
        }

        if ($value === null || $value === '' || $value === []) {
            return $messageXml;
        }

        return $messageXml->setValues($value);
    }
}
