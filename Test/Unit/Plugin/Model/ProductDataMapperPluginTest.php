<?php

namespace SnowIO\ExtendedProductRepositoryEE\Test\Unit\Plugin\Model;

use PHPUnit\Framework\TestCase as TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use SnowIO\ExtendedProductRepositoryEE\Model\SpecialPriceMapping;
use SnowIO\ExtendedProductRepositoryEE\Plugin\Model\ProductDataMapperPlugin as Plugin;
use SnowIO\ExtendedProductRepository\Model\ProductDataMapper;
use SnowIO\ExtendedProductRepositoryEE\Test\Unit\Fixture\SpecialPrice as SpecialPriceFixture;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogStaging\Model\ResourceModel\Product\Price\SpecialPrice;
use Magento\Store\Model\StoreRepository;
use Magento\Store\Model\Store;

class ProductDataMapperPluginTest extends TestCase
{
    private $product;
    private $extensionAttributes;
    private $specialPriceMapping;
    private $specialPrice;
    private $subject;
    private $plugin;
    private $stagingSpecialPriceModel;
    private $storeRepository;
    private $store;

    protected function setUp()
    {
        $om = new ObjectManager($this);

        /**
         * BEGIN CREATION OF MOCK CLASSES.
         */
        $this->product = $this
            ->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->extensionAttributes = $this
            ->getMockBuilder(ProductExtensionInterface::class)
            ->setMethods(['getSpecialPrice'])
            ->disableOriginalConstructor()
            ->getMock();

        /**
         * This class will be used for getting/setting data.
         * @see \SnowIO\ExtendedProductRepositoryEE\Test\Unit\Plugin\Model\ProductDataMapperPluginTest::testMapProductDataWithPayloads
         */
        $this->specialPrice = new SpecialPriceFixture();

        $this->specialPriceMapping = $this
            ->getMockBuilder(SpecialPriceMapping::class)
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
        /**
         * END CREATION OF MOCK CLASSES.
         */

        // Create subject class required in constructor for plugin class.
        $this->subject = $om->getObject(ProductDataMapper::class);
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

        // Create actual testable class
        $this->plugin = new Plugin($this->stagingSpecialPriceModel, $this->storeRepository, $this->specialPrice);

        $result = $this->plugin->aroundMapProductDataForSave(
            $this->subject,
            $this->mockProceedForPlugin(),
            $this->product
        );

        $this->assertNull($result);
    }

    /**
     * Test that a product valid special price payload returns null (void method).
     *
     * @author Liam Toohey (lt@amp.co)
     * @dataProvider dataForAroundMapProductDataForSave
     */
    public function testMapProductDataWithPayloads(
        $price,
        $storeId,
        $actualStoreId,
        $sku,
        $priceFrom,
        $priceTo,
        $expected
    )
    {
        $this->specialPriceMapping
            ->expects($this->any())
            ->method('getPrice')
            ->willReturn($price);

        $this->specialPriceMapping
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $this->specialPriceMapping
            ->expects($this->any())
            ->method('getSku')
            ->willReturn($sku);

        $this->specialPriceMapping
            ->expects($this->any())
            ->method('getPriceFrom')
            ->willReturn($priceFrom);

        $this->specialPriceMapping
            ->expects($this->any())
            ->method('getPriceTo')
            ->willReturn($priceTo);

        $this->extensionAttributes
            ->expects($this->any())
            ->method('getSpecialPrice')
            ->willReturn([$this->specialPriceMapping]);

        $this->product
            ->expects($this->any())
            ->method('getExtensionAttributes')
            ->willReturn($this->extensionAttributes);

        /**
         * Actual store id is set from data provider if payload includes store code instead of store id.
         * We need to mock that the store id actually gets set to the special price, not the code.
         */
        if(!is_null($actualStoreId)) {
            $this->store
                ->expects($this->any())
                ->method('getId')
                ->willReturn($actualStoreId);

            $this->specialPriceMapping
                ->expects($this->any())
                ->method('getStoreId')
                ->willReturn($actualStoreId);
        }

        $this->storeRepository
            ->expects($this->any())
            ->method('get')
            ->willReturn($this->store);

        /**
         * This method is not tested (out of scope, core Magento method).
         * If we make it to this call, the payload is valid and this function should return true.
         */
        $this->stagingSpecialPriceModel
            ->expects($this->any())
            ->method('update')
            ->willReturn(true);

        // Create actual testable class
        $this->plugin = new Plugin($this->stagingSpecialPriceModel, $this->storeRepository, $this->specialPrice);

        if ($expected === LocalizedException::class) {
            $this->expectException(LocalizedException::class);
        }

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

    public function dataForAroundMapProductDataForSave()
    {
        return [
            'Invalid payload' => [
                $price = null,
                $storeId = null,
                $actualStoreId = null,
                $sku = null,
                $priceFrom = null,
                $priceTo = null,
                $expected = LocalizedException::class
            ],
            'Valid payload with store code' => [
                $price = '10.00',
                // Store ID will likely be store code as we only have code in mappings.
                $storeId = 'ms_uk',
                // This is the actual store ID loaded from Magento, using the store code.
                $actualStoreId = '1',
                $sku = 'TEST01',
                $priceFrom = '2020-12-04 13:48:05',
                $priceTo = '2020-12-07 00:00:00',
                // Successful calls to aroundMapProductDataForSave return void.
                $expected = null
            ],
            'Valid payload with store id' => [
                $price = '10.00',
                // Also handle cases where correct store id is passed in payload.
                $storeId = '1',
                // Actual store ID not loaded as store ID passes in payload.
                $actualStoreId = null,
                $sku = 'TEST01',
                $priceFrom = '2020-12-04 13:48:05',
                $priceTo = '2020-12-07 00:00:00',
                // Successful calls to aroundMapProductDataForSave return void.
                $expected = null
            ],
        ];
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
