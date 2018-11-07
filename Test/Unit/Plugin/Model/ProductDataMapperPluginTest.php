<?php

namespace SnowIO\ExtendedProductRepositoryEE\Test\Unit\Plugin\Model;

use PHPUnit\Framework\TestCase as TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use SnowIO\ExtendedProductRepositoryEE\Plugin\Model\ProductDataMapperPlugin as Plugin;
use SnowIO\ExtendedProductRepository\Model\ProductDataMapper;
use Magento\Catalog\Api\Data\SpecialPriceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogStaging\Model\ResourceModel\Product\Price\SpecialPrice;
use Magento\Store\Model\StoreRepository;
use Magento\Store\Model\Store;

class ProductDataMapperPluginTest extends TestCase
{
    private $product;
    private $extensionAttributes;
    private $specialPrice;
    private $subject;
    private $plugin;
    private $stagingSpecialPriceModel;
    private $storeRepository;
    private $store;

    protected function setUp()
    {
        $om = new ObjectManager($this);

        $this->product = $this
            ->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->extensionAttributes = $this
            ->getMockBuilder(ProductExtensionInterface::class)
            ->setMethods(['getSpecialPrice'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->specialPrice = $this
            ->getMockBuilder(SpecialPriceInterface::class)
            ->setMethods([
                'setPrice',
                'setStoreId',
                'setSku',
                'setPriceFrom',
                'setPriceTo',
                'setExtensionAttributes',
                'getPrice',
                'getStoreId',
                'getSku',
                'getPriceFrom',
                'getPriceTo',
                'getExtensionAttributes'
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $this->stagingSpecialPriceModel = $this
            ->getMockBuilder(SpecialPrice::class)
            ->setMethods(['update'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->storeRepository = $this
            ->getMockBuilder(StoreRepository::class)
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->store = $this
            ->getMockBuilder(Store::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->subject = $om->getObject(ProductDataMapper::class);
        $this->plugin = new Plugin($this->stagingSpecialPriceModel, $this->storeRepository);
    }

    /**
     * Test that a product with no extension attributes does nothing.
     *
     * @author Liam Toohey (lt@amp.co)
     */
    public function testMapProductDataForSaveWithoutExtensionAttributes()
    {
        $this->product
            ->expects($this->any())
            ->method('getExtensionAttributes')
            ->willReturn(null);

        $result = $this->plugin->aroundMapProductDataForSave(
            $this->subject,
            $this->mockProceedForPlugin(),
            $this->product
        );

        $this->assertNull($result);
    }

    /**
     * Test that a product with extension attributes, but without special price extension attribute does nothing.
     *
     * @author Liam Toohey (lt@amp.co)
     */
    public function testMapProductDataForSaveNoSpecialPriceExtensionAttribute()
    {
        $this->extensionAttributes
            ->expects($this->any())
            ->method('getSpecialPrice')
            ->willReturn(null);

        $this->product
            ->expects($this->any())
            ->method('getExtensionAttributes')
            ->willReturn($this->extensionAttributes);

        $result = $this->plugin->aroundMapProductDataForSave(
            $this->subject,
            $this->mockProceedForPlugin(),
            $this->product
        );

        $this->assertNull($result);
    }

    /**
     * Test that a product with an invalid special price payload throws exception.
     *
     * @author Liam Toohey (lt@amp.co)
     */
    public function testMapProductDataForSaveInvalidPayload()
    {
        $this->specialPrice
            ->expects($this->any())
            ->method('getPrice')
            ->willReturn(null);

        $this->extensionAttributes
            ->expects($this->any())
            ->method('getSpecialPrice')
            ->willReturn([$this->specialPrice]);

        $this->product
            ->expects($this->any())
            ->method('getExtensionAttributes')
            ->willReturn($this->extensionAttributes);

        $this->expectException(LocalizedException::class);

        $this->plugin->aroundMapProductDataForSave(
            $this->subject,
            $this->mockProceedForPlugin(),
            $this->product
        );
    }

    /**
     * Test that a product valid special price payload returns null (void method).
     *
     * @author Liam Toohey (lt@amp.co)
     */
    public function testMapProductDataForSaveValidPayload()
    {
        $this->specialPrice
            ->expects($this->once())
            ->method('getPrice')
            ->willReturn(10.00);

        $this->specialPrice
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn("ms_uk");

        $this->specialPrice
            ->expects($this->once())
            ->method('getSku')
            ->willReturn('SKUTEST');

        $this->specialPrice
            ->expects($this->once())
            ->method('getPriceFrom')
            ->willReturn('2018-12-12 13:48:05');

        $this->specialPrice
            ->expects($this->once())
            ->method('getPriceTo')
            ->willReturn('2018-12-19 00:00:00');

        $this->extensionAttributes
            ->expects($this->once())
            ->method('getSpecialPrice')
            ->willReturn([$this->specialPrice]);

        $this->product
            ->expects($this->once())
            ->method('getExtensionAttributes')
            ->willReturn($this->extensionAttributes);

        $this->store
            ->expects($this->once())
            ->method('getId')
            ->willReturn("1");

        $this->storeRepository
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->store);

        /**
         * This method is not tested (out of scope, core Magento method).
         * If we make it to this call, the payload is valid and this function should return true.
         */
        $this->stagingSpecialPriceModel
            ->expects($this->once())
            ->method('update')
            ->willReturn(true);

        /**
         * Re-instantiate plugin class to include amended store repository mock.
         */
        $this->plugin = new Plugin($this->stagingSpecialPriceModel, $this->storeRepository);

        $result = $this->plugin->aroundMapProductDataForSave(
            $this->subject,
            $this->mockProceedForPlugin(),
            $this->product
        );

        /**
         * Successful calls to aroundMapProductDataForSave return void.
         */
        $this->assertEquals(null, $result);
    }

    /**
     * @author Liam Toohey (lt@amp.co)
     * @return \Closure
     */
    protected function mockProceedForPlugin(): \Closure
    {
        /**
         * Mock closure for method call.
         */
        $callbackMock = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $closure = function () use ($callbackMock) {
            return $callbackMock();
        };
        return $closure;
    }
}
