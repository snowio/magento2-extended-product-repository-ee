<?php

namespace SnowIO\ExtendedProductRepositoryEE\Plugin\Model;

use SnowIO\ExtendedProductRepository\Model\ProductDataMapper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use SnowIO\ExtendedProductRepositoryEE\Api\Data\SpecialPriceMappingInterface;
use Magento\CatalogStaging\Model\ResourceModel\Product\Price\SpecialPrice;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\SpecialPriceInterface;

class ProductDataMapperPlugin
{
    /** @var SpecialPrice  */
    private $stagingSpecialPriceModel;
    /** @var StoreRepositoryInterface  */
    private $storeRepository;
    /** @var SpecialPriceInterface  */
    private $specialPrice;

    /**
     * ProductDataMapperPlugin constructor.
     * @param SpecialPrice $stagingSpecialPriceModel
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        SpecialPrice $stagingSpecialPriceModel,
        StoreRepositoryInterface $storeRepository,
        SpecialPriceInterface $specialPrice
    )
    {
        $this->stagingSpecialPriceModel = $stagingSpecialPriceModel;
        $this->storeRepository = $storeRepository;
        $this->specialPrice = $specialPrice;
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

        $specialPrices = [];
        $specialPriceClone = clone $this->specialPrice;
        
        /** @var \SnowIO\ExtendedProductRepositoryEE\Api\Data\SpecialPriceMappingInterface $price */
        foreach ($prices as $price) {
            if (!$this->validatePricePayload($price)) {
                throw new LocalizedException(new Phrase(
                    'Missing data from special_price extension attribute payload'
                ));
            }

            /**
             * IMPORTANT:
             *
             * In most cases, $price->getStoreId() === store code, not the ID.
             *
             * This is because we only have access to the store code in mapping.
             * We have to name this store_id so the special price data model matches vanilla.
             *
             * @see https://github.com/snowio/magento2-data-model/blob/v0.5.10/src/SpecialPrice.php
             *
             * If payload does contain numeric store_id, continue.
             */
            if (!is_numeric($price->getStoreId())) {
                $store = $this->storeRepository->get($price->getStoreId());
                $price->setStoreId($store->getId());
            }

            /**
             * We now need to transform SpecialPriceMappingInterface into vanilla SpecialPriceInterface.
             * This is to ensure vanilla update functionality works.
             *
             * @see \Magento\CatalogStaging\Model\ResourceModel\Product\Price\SpecialPrice::priceSelectionsAreEqual
             */
            $specialPrices[] = $specialPriceClone
                ->setPrice($price->getPrice())
                ->setStoreId($price->getStoreId())
                ->setSku($price->getSku())
                ->setPriceFrom($price->getPriceFrom())
                ->setPriceTo($price->getPriceTo());
        }

        /**
         * $specialPrices = [
         *     \Magento\Catalog\Api\Data\SpecialPriceInterface,
         *     \Magento\Catalog\Api\Data\SpecialPriceInterface,
         *     ...
         * ]
         */
        $this->stagingSpecialPriceModel->update($specialPrices);
    }

    /**
     * @author Liam Toohey (lt@amp.co)
     * @param SpecialPriceMappingInterface $price
     * @return bool
     */
    private function validatePricePayload(SpecialPriceMappingInterface $price)
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