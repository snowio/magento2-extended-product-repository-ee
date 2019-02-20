<?php

namespace SnowIO\ExtendedProductRepositoryEE\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use SnowIO\ExtendedProductRepositoryEE\Model\SpecialPriceScheduler;

class EntitySaveAfter implements ObserverInterface
{
    /** @var SpecialPriceScheduler  */
    protected $specialPriceScheduler;

    public function __construct(SpecialPriceScheduler $specialPriceScheduler)
    {
        $this->specialPriceScheduler = $specialPriceScheduler;
    }

    /**
     * @param Observer $observer
     * @return $this|void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        $entity = $observer->getData('entity');

        /**
         * Only schedule special prices if part of snow extension attribute payload.
         */
        if (!$entity ||
            !$entity instanceof ProductInterface ||
            !$prices = $entity->getExtensionAttributes()->getSpecialPrice()
        ) {
            return $this;
        }

        $this->specialPriceScheduler->mapProductSpecialPrices($prices);
    }
}