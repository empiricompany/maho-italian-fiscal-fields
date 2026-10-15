<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ItalianFiscalFields
 */

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$usedInForms = [
    'adminhtml_customer_address',
    'customer_address_edit',
    'customer_register_address',
];

$fiscalAttributes = [
    'cod_fisc' => 'Codice Fiscale',
    'email_pec' => 'Email PEC',
    'cod_sdi' => 'Codice SdI',
];

foreach ($fiscalAttributes as $code => $label) {
    $installer->addAttribute('customer_address', $code, [
        'type' => 'varchar',
        'label' => $label,
        'input' => 'text',
        'required' => 0,
        'sort_order' => 120,
        'position' => 120,
        'visible' => 1,
        'system' => 1,
        'user_defined' => 0,
    ]);

    // eav/config cannot see the row just inserted by addAttribute(): read the id back.
    $attributeId = (int) $installer->getAttributeId('customer_address', $code);
    if (!$attributeId) {
        continue;
    }

    Mage::getModel('customer/attribute')
        ->load($attributeId)
        ->setData('used_in_forms', $usedInForms)
        ->save();
}

$installer->endSetup();
