<?php

namespace CL\Transfer\Test;

use Omnipay\Omnipay;

/**
 * @coversNothing
 *
 * @author    Ivan Kerin <ikerin@gmail.com>
 * @copyright 2014, Clippings Ltd.
 * @license   http://spdx.org/licenses/BSD-3-Clause
 */
class InitegrationTest extends AbstractTestCase
{
    /**
     * @covers ::testMethod
     */
    public function testTest()
    {
        $basket = new Basket();
        $product1 = Product::find(1);
        $product2 = Product::find(2);

        $item1 = new ProductItem(['quantity' => 2]);
        $item1->setProduct($product1);

        $item2 = new ProductItem(['quantity' => 4]);
        $item2->setProduct($product2);

        $basket
            ->getItems()
                ->add($item1)
                ->add($item2);

        Basket::save($basket);

        $gateway = Omnipay::getFactory()->create('Dummy');

        $parameters = [
            'card' => [
                'number' => '4242424242424242',
                'expiryMonth' => 12,
                'expiryYear' => date('Y'),
                'cvv' => 123,
            ],
            'clientIp' => '192.168.0.1',
        ];

        $basket->freeze();

        $response = $basket->purchase($gateway, $parameters);

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($basket->isSuccessful);

        $this->assertQueries([
            'SELECT Product.* FROM Product WHERE (id = 1) LIMIT 1',
            'SELECT Product.* FROM Product WHERE (id = 2) LIMIT 1',
            'INSERT INTO Basket (id, currency, isSuccessful, completedAt, responseData, deletedAt, isFrozen, value) VALUES (NULL, "GBP", , NULL, NULL, NULL, , 0)',
            'INSERT INTO ProductItem (id, basketId, productId, quantity, deletedAt, isFrozen, value) VALUES (NULL, NULL, NULL, 2, NULL, , 0), (NULL, NULL, NULL, 4, NULL, , 0)',
            'UPDATE ProductItem SET basketId = CASE id WHEN 1 THEN "1" WHEN 2 THEN "1" ELSE basketId END, productId = CASE id WHEN 1 THEN 1 WHEN 2 THEN 2 ELSE productId END WHERE (id IN (1, 2))',
            'UPDATE Basket SET isSuccessful = 1, completedAt = "'.$basket->completedAt.'", responseData = "{"amount":"1000.00","reference":"'.$basket->responseData['reference'].'","success":true,"message":"Success"}", isFrozen = 1, value = 100000 WHERE (id = "1")',
            'UPDATE ProductItem SET isFrozen = CASE id WHEN 1 THEN 1 WHEN 2 THEN 1 ELSE isFrozen END, value = CASE id WHEN 1 THEN 10000 WHEN 2 THEN 20000 ELSE value END WHERE (id IN (1, 2))',
        ]);
    }
}
