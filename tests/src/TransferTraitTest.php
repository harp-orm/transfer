<?php

namespace CL\Transfer\Test;

use SebastianBergmann\Money\Money;
use SebastianBergmann\Money\Currency;
use Guzzle\Http\Client as HttpClient;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Omnipay\Dummy\Message\AuthorizeRequest;
use Omnipay\Dummy\Message\Response;

use DateTime;

/**
 * @coversDefaultClass CL\Transfer\TransferTrait
 *
 * @author    Ivan Kerin <ikerin@gmail.com>
 * @copyright 2014, Clippings Ltd.
 * @license   http://spdx.org/licenses/BSD-3-Clause
 */
class TransferTraitTest extends AbstractTestCase
{
    /**
     * @covers ::initialize
     */
    public function testInitialize()
    {
        $repo = Basket::getRepo();

        $this->assertTrue($repo->getSoftDelete());
    }

    /**
     * @coversNothing
     */
    public function testSerialize()
    {
        $item = new Basket(['responseData' => '{"amount":"1000.00","reference":"53a376c2a174f","success":true,"message":"Success"}']);

        $expected = [
            'amount' => '1000.00',
            'reference' => '53a376c2a174f',
            'success' => true,
            'message' => 'Success',
        ];

        $this->assertEquals($expected, $item->responseData);
    }

    /**
     * @covers ::getCompletedAt
     * @covers ::setCompletedAt
     */
    public function testCompletedAt()
    {
        $basket = new Basket(['completedAt' => '2014-01-01 00:00:00']);

        $this->assertEquals(new DateTime('2014-01-01 00:00:00'), $basket->getCompletedAt());

        $basket = new Basket();

        $this->assertNull($basket->getCompletedAt());

        $basket->setCompletedAt(new DateTime('2014-02-01 00:00:00'));
        $this->assertEquals(new DateTime('2014-02-01 00:00:00'), $basket->getCompletedAt());
        $this->assertEquals('2014-02-01 00:00:00', $basket->completedAt);
    }

    /**
     * @covers ::isSent
     */
    public function testIsSent()
    {
        $basket = new Basket();

        $this->assertFalse($basket->isSent());

        $basket = new Basket(['responseData' => '{}']);

        $this->assertTrue($basket->isSent());
    }

    /**
     * @covers ::getTransferParameters
     * @covers ::getRequestParameters
     */
    public function testGetRequestParameters()
    {
        $basket = new Basket(['currency' => 'BGN', 'id' => 20]);
        $basket
            ->getItems()
                ->add(new ProductItem(['id' => 1, 'value' => 1000, 'isFrozen' => true, 'quantity' => 2]))
                ->add(new ProductItem(['id' => 2, 'value' => 2000, 'isFrozen' => true, 'quantity' => 3]));

        $expected = [
            'amount' => 80,
            'currency' => 'BGN',
            'transactionReference' => 20,
            'cart' => ['test'],
        ];

        $params = $basket->getRequestParameters(['cart' => ['test']]);

        $this->assertEquals($expected, $params);
    }

    /**
     * @covers ::sendRequest
     */
    public function testSendRequest()
    {
        $request = $this->getMock(
            'Omnipay\Dummy\Message\AuthorizeRequest',
            ['send'],
            [new HttpClient(), new HttpRequest()]
        );

        $response = $this->getMock(
            'Omnipay\Dummy\Message\Response',
            ['isRedirect', 'isSuccessful'],
            [$request, ['test']]
        );

        $request
            ->expects($this->exactly(3))
            ->method('send')
            ->will($this->returnValue($response));

        $response
            ->expects($this->exactly(3))
            ->method('isRedirect')
            ->will($this->onConsecutiveCalls(true, false, false));

        $response
            ->expects($this->exactly(2))
            ->method('isSuccessful')
            ->will($this->onConsecutiveCalls(false, true));

        $current = new DateTime();

        $basket = new Basket(['currency' => 'BGN', 'id' => 20]);

        $result = $basket->sendRequest($request);
        $this->assertSame($response, $result);
        $this->assertEquals($response->getData(), $basket->responseData);
        $this->assertFalse($basket->isSuccessful);

        $this->assertTrue($current >= $basket->completedAt);

        $basket = new Basket(['currency' => 'BGN', 'id' => 20]);

        $basket->sendRequest($request);
        $this->assertSame($response, $result);
        $this->assertEquals($response->getData(), $basket->responseData);
        $this->assertFalse($basket->isSuccessful);

        $this->assertTrue($current >= $basket->completedAt);

        $basket = new Basket(['currency' => 'BGN', 'id' => 20]);

        $basket->sendRequest($request);
        $this->assertSame($response, $result);
        $this->assertEquals($response->getData(), $basket->responseData);
        $this->assertTrue($basket->isSuccessful);

        $this->assertTrue($current >= $basket->completedAt);
    }

    /**
     * @covers ::execute
     */
    public function testExecute()
    {
        $basket = $this->getMock(
            __NAMESPACE__.'\Basket',
            ['getRequestParameters', 'sendRequest', 'assertValid']
        );

        $gateway = $this->getMock(
            'Omnipay\Dummy\Gateway',
            ['purchase']
        );

        $request = new AuthorizeRequest(new HttpClient(), new HttpRequest());
        $response = new Response($request, ['data']);

        $basket
            ->expects($this->once())
            ->method('getRequestParameters')
            ->with($this->equalTo(['test' => 'test2']))
            ->will($this->returnValue(['test2' => 'test3']));

        $basket
            ->expects($this->exactly(2))
            ->method('assertValid')
            ->will($this->returnSelf());

        $basket
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->identicalTo($request))
            ->will($this->returnValue($response));

        $gateway
            ->expects($this->once())
            ->method('purchase')
            ->with($this->equalTo(['test2' => 'test3']))
            ->will($this->returnValue($request));

        $basket->execute($gateway, 'purchase', ['test' => 'test2']);

        $this->assertTrue($basket->isSaved());
    }
}
