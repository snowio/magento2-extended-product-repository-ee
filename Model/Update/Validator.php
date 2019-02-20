<?php

namespace SnowIO\ExtendedProductRepositoryEE\Model\Update;

use Magento\Staging\Api\Data\UpdateInterface;

class Validator extends \Magento\Staging\Model\Update\Validator
{
    /**
     * Ensure scheduled updates with from dates older than current date are accepted.
     *
     * @param UpdateInterface $entity
     * @return void
     * @throws ValidatorException
     */
    public function validateCreate(UpdateInterface $entity)
    {
        $this->validateUpdate($entity);
        // $this->validateStartTimeNotPast($entity);
    }
}
