<?php

namespace SnowIO\ExtendedProductRepositoryEE\Model;

use mysql_xdevapi\Exception;
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
use Magento\Staging\Model\ResourceModel\Db\CampaignValidator;
use Magento\Staging\Model\VersionManager\Proxy as VersionManager;

class SpecialPriceScheduler
{
    /** @var SpecialPrice  */
    private $stagingSpecialPriceModel;
    /** @var StoreRepositoryInterface  */
    private $storeRepository;
    /** @var SpecialPriceInterface  */
    private $specialPrice;
    /** @var CampaignValidator  */
    private $campaignValidator;
    /**
     * ProductDataMapperPlugin constructor.
     * @param SpecialPrice $stagingSpecialPriceModel
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        SpecialPrice $stagingSpecialPriceModel,
        StoreRepositoryInterface $storeRepository,
        SpecialPriceInterface $specialPrice,
        CampaignValidator $campaignValidator
    )
    {
        $this->stagingSpecialPriceModel = $stagingSpecialPriceModel;
        $this->storeRepository = $storeRepository;
        $this->specialPrice = $specialPrice;
        $this->campaignValidator = $campaignValidator;
    }

    /**
     * @param array $prices
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function mapProductSpecialPrices(array $prices)
    {
        $specialPrices = [];
        /** @var \SnowIO\ExtendedProductRepositoryEE\Api\Data\SpecialPriceMappingInterface $price */
        foreach ($prices as $price) {
            if (!$price instanceof SpecialPriceMappingInterface) {
                throw new LocalizedException(new Phrase(
                    'Scheduled price payload is not instance of SpecialPriceMappingInterface'
                ));
            }
            if (!$this->validatePricePayload($price)) {
                if ($price->getPrice() === (float)0) {
                    // Ignore 0 prices
                    continue;
                }
                throw new LocalizedException(new Phrase(
                    'Missing data from special_price extension attribute payload'
                ));
            } elseif ($price->getPriceTo() && strtotime($price->getPriceTo()) < time()) {
                // If outdated special price, ignore.
                continue;
            }

            /**
             * To schedule a special price, a to and from date MUST be provided.
             * These dates are necessary as they are used to create versions of the product.
             *
             * When a product is created with no updates, the created_in and updated_in dates span the maximum time available.
             * This means this version of the product will always be used.
             *
             * If we have no to and from date, we need to follow this same methodology:
             *
             * - If no special from date, scheduled price has been valid since start of time.
             * - If no special to date, scheduled price is valid until end of time.
             *
             * @see \Magento\Staging\Model\Operation\Create::execute
             */
            if (!$price->getPriceFrom()) {
                $price->setPriceTo(date('Y-m-d H:i:s', 1));
            } elseif (!$price->getPriceTo()) {
                $price->setPriceTo(date('Y-m-d H:i:s', VersionManager::MAX_VERSION));
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
             * SpecialPriceMappingInterface will not be accepted in the vanilla functions used to update special prices.
             * We need to create vanilla SpecialPriceInterface instances from our SpecialPriceMappingInterface data.
             *
             * Example of SpecialPriceInterface instance being required in vanilla functionality:
             * @see \Magento\CatalogStaging\Model\ResourceModel\Product\Price\SpecialPrice::priceSelectionsAreEqual
             */
            $specialPriceClone = clone $this->specialPrice;
            $specialPrices[] = $specialPriceClone
                ->setPrice($price->getPrice())
                ->setStoreId($price->getStoreId())
                ->setSku($price->getSku())
                ->setPriceFrom($price->getPriceFrom())
                ->setPriceTo($price->getPriceTo());
        }
        if (!empty($specialPrices)) {
            /**
             * $specialPrices = [
             *     \Magento\Catalog\Api\Data\SpecialPriceInterface,
             *     \Magento\Catalog\Api\Data\SpecialPriceInterface,
             *     ...
             * ]
             */
            $this->stagingSpecialPriceModel->update($specialPrices);
        }
    }

    /**
     * @author Liam Toohey (lt@amp.co)
     * @param SpecialPriceMappingInterface $price
     * @return bool
     */
    private function validatePricePayload(SpecialPriceMappingInterface $price)
    {
        if (!$price->getPrice() ||
            is_null($price->getStoreId()) ||
            !$price->getSku() ||
            (!$price->getPriceFrom() &&
                !$price->getPriceTo())
        ) {
            return false;
        }
        return true;
    }
}
