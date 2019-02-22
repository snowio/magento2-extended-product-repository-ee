<?php

namespace SnowIO\ExtendedProductRepositoryEE\Plugin;

use \Magento\Catalog\Observer\SetSpecialPriceStartDate;
use \Magento\Framework\Event\Observer;

class SetSpecialPriceStartDatePlugin
{
    /**
     * @param SetSpecialPriceStartDate $subject
     * @param Observer $observer
     * @return Observer
     */
    public function beforeExecute(SetSpecialPriceStartDate $subject, Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        if (is_array($product->getSpecialPrice())) {
            $product->setData('special_price', null);
        }

        return [$observer];
    }
}
