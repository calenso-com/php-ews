<?php

namespace garethp\ews\Test\API;

use garethp\ews\API\ClassMap;
use garethp\ews\API\Message\ResponseMessageType;
use garethp\ews\API\Message\ResponseMessageType\MessageXmlAType;
use garethp\ews\API\Type\ValueType;
use PHPUnit\Framework\TestCase;

class MessageXmlTest extends TestCase
{
    /**
     * The real shape SoapClient produces for a single <t:Value> child:
     * MessageXml's XSD type is an anonymous complexType wrapping xs:any, so
     * it decodes to stdClass{any: ...} while the Value children themselves
     * ARE classmapped to ValueType.
     */
    public function testConvertsSoapDecodedSingleValue()
    {
        $value = new ValueType();
        $value->name = 'BackOffMilliseconds';
        $value->_ = '297749';

        $message = new ResponseMessageType();

        // This triggers __set() which routes through TypeConverter
        $message->MessageXml = (object)['any' => ['Value' => $value]];

        $messageXml = $message->getMessageXml();
        $this->assertInstanceOf(MessageXmlAType::class, $messageXml);
        $this->assertSame(['Value' => $value], $messageXml->getValues(), 'Decoded values must be preserved as-is');
    }

    public function testConvertsSoapDecodedMultipleValues()
    {
        $backOff = new ValueType();
        $backOff->name = 'BackOffMilliseconds';
        $backOff->_ = '297749';
        $other = new ValueType();
        $other->name = 'InnerErrorMessageText';
        $other->_ = 'Resources are unavailable.';

        $message = new ResponseMessageType();
        $message->MessageXml = (object)['any' => ['Value' => [$backOff, $other]]];

        $messageXml = $message->getMessageXml();
        $this->assertInstanceOf(MessageXmlAType::class, $messageXml);
        $this->assertSame(['Value' => [$backOff, $other]], $messageXml->getValues(), 'No decoded value may be dropped');
    }

    public function testConvertsPlainArray()
    {
        $message = new ResponseMessageType();
        $message->MessageXml = ['Value' => '5000'];

        $messageXml = $message->getMessageXml();
        $this->assertInstanceOf(MessageXmlAType::class, $messageXml);
        $this->assertSame(['Value' => '5000'], $messageXml->getValues());
    }

    public function testConvertsEmptyValueToEmptyMessageXml()
    {
        $message = new ResponseMessageType();
        $message->MessageXml = [];

        $messageXml = $message->getMessageXml();
        $this->assertInstanceOf(MessageXmlAType::class, $messageXml);
        $this->assertSame([], $messageXml->getValues());
    }

    public function testExistingMessageXmlATypePassesThroughUnchanged()
    {
        $original = new MessageXmlAType();
        $message = new ResponseMessageType();
        $message->setMessageXml($original);

        $this->assertSame($original, $message->getMessageXml());
    }

    /**
     * End-to-end: a real SoapClient with the real WSDL + classmap decoding a
     * canned ErrorServerBusy CreateItemResponse. Before the fix this fataled
     * with a TypeError in setMessageXml during response deserialization.
     */
    public function testErrorServerBusyResponseDecodesWithoutTypeError()
    {
        $client = new MessageXmlCannedSoapClient(
            __DIR__ . '/../../../Resources/wsdl/services.wsdl',
            [
                'classmap' => ClassMap::getClassMap(),
                'trace' => 1,
                'exceptions' => true,
                'location' => 'https://example.invalid/EWS/Exchange.asmx',
            ]
        );

        $response = $client->CreateItem([
            'Items' => ['CalendarItem' => ['Subject' => 'probe']],
            'MessageDisposition' => 'SaveOnly',
            'SendMeetingInvitations' => 'SendToNone',
        ]);

        $responseMessage = $response->getResponseMessages()->getCreateItemResponseMessage();
        if (is_array($responseMessage)) {
            $responseMessage = current($responseMessage);
        }

        $this->assertEquals('Error', $responseMessage->getResponseClass());
        $this->assertEquals('ErrorServerBusy', $responseMessage->getResponseCode());
        $this->assertEquals(
            'The server cannot service this request right now. Try again later.',
            $responseMessage->getMessageText()
        );

        $messageXml = $responseMessage->getMessageXml();
        $this->assertInstanceOf(MessageXmlAType::class, $messageXml);
        $values = $messageXml->getValues();
        $this->assertArrayHasKey('Value', $values);
        $this->assertInstanceOf(ValueType::class, $values['Value']);
        $this->assertEquals('BackOffMilliseconds', $values['Value']->getName());
        $this->assertEquals('297749', $values['Value']->_);
    }
}

class MessageXmlCannedSoapClient extends \SoapClient
{
    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
    {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
  <s:Body>
    <m:CreateItemResponse xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages"
                          xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types">
      <m:ResponseMessages>
        <m:CreateItemResponseMessage ResponseClass="Error">
          <m:MessageText>The server cannot service this request right now. Try again later.</m:MessageText>
          <m:ResponseCode>ErrorServerBusy</m:ResponseCode>
          <m:DescriptiveLinkKey>0</m:DescriptiveLinkKey>
          <m:MessageXml><t:Value Name="BackOffMilliseconds">297749</t:Value></m:MessageXml>
          <m:Items/>
        </m:CreateItemResponseMessage>
      </m:ResponseMessages>
    </m:CreateItemResponse>
  </s:Body>
</s:Envelope>
XML;
    }
}
