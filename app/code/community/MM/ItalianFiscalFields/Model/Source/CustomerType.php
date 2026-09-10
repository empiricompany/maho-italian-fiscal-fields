<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

class MM_ItalianFiscalFields_Model_Source_CustomerType extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    public const TYPE_PRIVATE = 'private';
    public const TYPE_COMPANY = 'company';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[\Override]
    public function getAllOptions(): array
    {
        return $this->toOptionArray();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_PRIVATE, 'label' => Mage::helper('italianfiscalfields')->__('Private')],
            ['value' => self::TYPE_COMPANY, 'label' => Mage::helper('italianfiscalfields')->__('Company')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->toOptionArray() as $option) {
            $result[$option['value']] = $option['label'];
        }
        return $result;
    }
}
