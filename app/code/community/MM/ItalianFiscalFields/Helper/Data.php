<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

class MM_ItalianFiscalFields_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function showCodiceFiscaleForPrivate(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('customer/italianfiscalfields/codfisc_private_enabled', $storeId);
    }

    public function getDefaultCustomerType(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('customer/italianfiscalfields/default_customer_type', $storeId)
            ?: MM_ItalianFiscalFields_Model_Source_CustomerType::TYPE_PRIVATE;
    }
}
