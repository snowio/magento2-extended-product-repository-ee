<?php

namespace SnowIO\ExtendedProductRepositoryEE\Plugin\Model;

use SnowIO\ExtendedProductRepository\Model\ProductDataMapper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Catalog\Api\Data\SpecialPriceInterface;
use Magento\CatalogStaging\Model\ResourceModel\Product\Price\SpecialPrice;

class ProductDataMapperPlugin
{
    /** @var SpecialPrice  */
    private $stagingSpecialPriceModel;

    /**
     * ProductDataMapperPlugin constructor.
     * @param SpecialPrice $stagingSpecialPriceModel
     */
    public function __construct(SpecialPrice $stagingSpecialPriceModel)
    {
        $this->stagingSpecialPriceModel = $stagingSpecialPriceModel;
    }

    /**
     * @author Liam Toohey (lt@amp.co)
     * @param ProductDataMapper $subject
     * @param \Closure $proceed
     * @param ProductInterface $product
     * @return mixed|void
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function aroundMapProductDataForSave(ProductDataMapper $subject, \Closure $proceed, ProductInterface $product)
    {
        /**
         * Original function has no return value.
         */
        $proceed($product);

        if (!$extensionAttributes = $product->getExtensionAttributes()) {
            return;
        }

        $this->mapProductSpecialPrices($extensionAttributes);
    }

    /**
     * @author Liam Toohey (lt@amp.co)
     * @param ProductExtensionInterface $extensionAttributes
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    private function mapProductSpecialPrices(ProductExtensionInterface $extensionAttributes)
    {
        if (null === $prices = $extensionAttributes->getSpecialPrice()) {
            return;
        }

        foreach ($prices as $price) {
            if (!$this->validatePricePayload($price)) {
                throw new LocalizedException(new Phrase(
                    'Missing data from special_price extension attribute payload'
                ));
            }
        }
        /**
         * $prices = [
         *     \Magento\Catalog\Api\Data\SpecialPriceInterface,
         *     \Magento\Catalog\Api\Data\SpecialPriceInterface,
         *     ...
         * ]
         */
        $this->stagingSpecialPriceModel->update($prices);
    }

    /**
     * @author Liam Toohey (lt@amp.co)
     * @param SpecialPriceInterface $price
     * @return bool
     */
    private function validatePricePayload(SpecialPriceInterface $price)
    {
        if (
            !$price->getPrice() ||
            !$price->getStoreId() ||
            !$price->getSku() ||
            !$price->getPriceFrom() ||
            !$price->getPriceTo()
        ) {
            return false;
        }
        return true;
    }
}