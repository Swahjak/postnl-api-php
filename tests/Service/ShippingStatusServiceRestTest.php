<?php
/**
 * The MIT License (MIT).
 *
 * Copyright (c) 2017-2021 Michael Dekker
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
 * is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author    Michael Dekker <git@michaeldekker.nl>
 * @copyright 2017-2021 Michael Dekker
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

namespace ThirtyBees\PostNL\Tests\Service;

use Cache\Adapter\Void\VoidCachePool;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Message as PsrMessage;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use ThirtyBees\PostNL\Entity\Address;
use ThirtyBees\PostNL\Entity\Customer;
use ThirtyBees\PostNL\Entity\Message\Message;
use ThirtyBees\PostNL\Entity\Request\CompleteStatus;
use ThirtyBees\PostNL\Entity\Request\CurrentStatus;
use ThirtyBees\PostNL\Entity\Request\GetSignature;
use ThirtyBees\PostNL\Entity\Response\CompleteStatusResponse;
use ThirtyBees\PostNL\Entity\Response\CompleteStatusResponseEvent;
use ThirtyBees\PostNL\Entity\Response\CurrentStatusResponse;
use ThirtyBees\PostNL\Entity\Response\GetSignatureResponseSignature;
use ThirtyBees\PostNL\Entity\Shipment;
use ThirtyBees\PostNL\Entity\SOAP\UsernameToken;
use ThirtyBees\PostNL\HttpClient\MockClient;
use ThirtyBees\PostNL\PostNL;
use ThirtyBees\PostNL\Service\ShippingStatusServiceInterface;
use function file_get_contents;
use const _RESPONSES_DIR_;

/**
 * Class ShippingStatusRestTest.
 *
 * @testdox The ShippingStatusService (REST)
 */
class ShippingStatusServiceRestTest extends TestCase
{
    /** @var PostNL */
    protected $postnl;
    /** @var ShippingStatusServiceInterface */
    protected $service;
    /** @var */
    protected $lastRequest;

    /**
     * @before
     *
     * @throws \ThirtyBees\PostNL\Exception\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function setupPostNL()
    {
        $this->postnl = new PostNL(
            Customer::create()
                ->setCollectionLocation('123456')
                ->setCustomerCode('DEVC')
                ->setCustomerNumber('11223344')
                ->setContactPerson('Test')
                ->setAddress(Address::create([
                    'AddressType' => '02',
                    'City'        => 'Hoofddorp',
                    'CompanyName' => 'PostNL',
                    'Countrycode' => 'NL',
                    'HouseNr'     => '42',
                    'Street'      => 'Siriusdreef',
                    'Zipcode'     => '2132WT',
                ]))
                ->setGlobalPackBarcodeType('AB')
                ->setGlobalPackCustomerCode('1234'), new UsernameToken(null, 'test'),
            true,
            PostNL::MODE_REST
        );

        $this->service = $this->postnl->getShippingStatusService();
        $this->service->cache = new VoidCachePool();
        $this->service->ttl = 1;
    }

    /**
     * @after
     */
    public function logPendingRequest()
    {
        if (!$this->lastRequest instanceof Request) {
            return;
        }

        global $logger;
        if ($logger instanceof LoggerInterface) {
            $logger->debug($this->getName()." Request\n".\GuzzleHttp\Psr7\str($this->lastRequest));
        }
        $this->lastRequest = null;
    }

    /**
     * @testdox creates a valid CurrentStatus request
     */
    public function testGetCurrentStatusByBarcodeRequestRest()
    {
        $barcode = '3SDEVC201611210';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusRequestREST(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode($barcode)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEmpty($query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/barcode/$barcode", $request->getUri()->getPath());
    }

    /**
     * @testdox can get the current status
     * @dataProvider getCurrentStatusByBarcodeProvider
     */
    public function testGetCurrentStatusByBarcodeRest($response)
    {
        $mock = new MockHandler([$response]);
        $handler = HandlerStack::create($mock);
        $mockClient = new MockClient();
        $mockClient->setHandler($handler);
        $this->postnl->setHttpClient($mockClient);

        $currentStatusResponse = $this->postnl->getCurrentStatus(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode('3S8392302392342')
                )
        );

        $this->assertInstanceOf(CurrentStatusResponse::class, $currentStatusResponse);
    }

    /**
     * @testdox creates a valid CurrentStatusByReference request
     */
    public function testGetCurrentStatusByReferenceRequestRest()
    {
        $reference = '339820938';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusRequestREST(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setReference($reference)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'customerCode'   => $this->postnl->getCustomer()->getCustomerCode(),
            'customerNumber' => $this->postnl->getCustomer()->getCustomerNumber(),
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/reference/$reference", $request->getUri()->getPath());
    }

    /**
     * @testdox creates a valid CompleteStatus request
     */
    public function testGetCompleteStatusRequestRest()
    {
        $barcode = '3SDEVC201611210';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusRequestREST(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode($barcode)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'detail' => 'true',
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/barcode/$barcode", $request->getUri()->getPath());
    }

    /**
     * @testdox can retrieve the complete status
     * @dataProvider getCompleteStatusByBarcodeProvider
     *
     * @param ResponseInterface $response
     */
    public function testGetCompleteStatusByBarcodeRest($response)
    {
        $mock = new MockHandler([$response]);
        $handler = HandlerStack::create($mock);
        $mockClient = new MockClient();
        $mockClient->setHandler($handler);
        $this->postnl->setHttpClient($mockClient);

        $completeStatusResponse = $this->postnl->getCompleteStatus(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode('3SABCD6659149')
                )
        );

        $this->assertInstanceOf(CompleteStatusResponse::class, $completeStatusResponse);
        $this->assertInstanceOf(Address::class, $completeStatusResponse->getShipments()[0]->getAddresses()[0]);
        $this->assertNull($completeStatusResponse->getShipments()[0]->getAmounts());
        $this->assertInstanceOf(CompleteStatusResponseEvent::class, $completeStatusResponse->getShipments()[0]->getEvents()[0]);
        $this->assertEquals('01B', $completeStatusResponse->getShipments()[0]->getEvents()[0]->getCode());
        $this->assertNull($completeStatusResponse->getShipments()[0]->getGroups());
        $this->assertInstanceOf(Customer::class, $completeStatusResponse->getShipments()[0]->getCustomer());
        $this->assertEquals('07-03-2018 09:50:47', $completeStatusResponse->getShipments()[0]->getOldStatuses()[4]->getTimeStamp());
    }

    /**
     * @testdox creates a valid CompleteStatusByReference request
     */
    public function testGetCompleteStatusByReferenceRequestRest()
    {
        $reference = '339820938';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusRequestREST(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setReference($reference)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'customerCode'   => $this->postnl->getCustomer()->getCustomerCode(),
            'customerNumber' => $this->postnl->getCustomer()->getCustomerNumber(),
            'detail'         => 'true',
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/reference/$reference", $request->getUri()->getPath());
    }

    /**
     * @testdox creates a valid GetSignature request
     */
    public function testGetSignatureRequestRest()
    {
        $barcode = '3S9283920398234';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildGetSignatureRequestREST(
            (new GetSignature())
                ->setCustomer($this->postnl->getCustomer())
                ->setMessage($message)
                ->setShipment((new Shipment())
                    ->setBarcode($barcode)
                )
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEmpty($query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/signature/$barcode", $request->getUri()->getPath());
    }

    /**
     * @testdox can get the signature
     */
    public function testGetSignatureRest()
    {
        $mock = new MockHandler([
            PsrMessage::parseResponse(file_get_contents(_RESPONSES_DIR_.'/rest/shippingstatus/signature.http')),
        ]);
        $handler = HandlerStack::create($mock);
        $mockClient = new MockClient();
        $mockClient->setHandler($handler);
        $this->postnl->setHttpClient($mockClient);

        $signatureResponse = $this->postnl->getSignature(
            (new GetSignature())
                ->setShipment((new Shipment())
                    ->setBarcode('3SABCD6659149')
                )
        );

        $this->assertInstanceOf(GetSignatureResponseSignature::class, $signatureResponse);
        $this->assertEquals('2018-03-07T13:52:45.000+01:00', $signatureResponse->getSignatureDate());
        $this->assertNotNull($signatureResponse->getSignatureImage());
    }

    /**
     * @return array[]
     */
    public function getCurrentStatusByBarcodeProvider()
    {
        return [
            [PsrMessage::parseResponse(file_get_contents(_RESPONSES_DIR_.'/rest/shippingstatus/currentstatus.http'))],
            [PsrMessage::parseResponse(file_get_contents(_RESPONSES_DIR_.'/rest/shippingstatus/currentstatus2.http'))],
        ];
    }

    /**
     * @return array[]
     */
    public function getCompleteStatusByBarcodeProvider()
    {
        return [
            [PsrMessage::parseResponse(file_get_contents(_RESPONSES_DIR_.'/rest/shippingstatus/completestatus.http'))],
            [PsrMessage::parseResponse(file_get_contents(_RESPONSES_DIR_.'/rest/shippingstatus/completestatus2.http'))],
        ];
    }
}
