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
    'customer_account_create',
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
        'is_visible' => 1,
        'is_system' => 0,
        'is_user_defined' => 1,
        'position' => 120,
    ]);

    $attribute = Mage::getSingleton('eav/config')
        ->getAttribute('customer_address', $code);
    if (!$attribute) {
        continue;
    }
    $attribute->setData('used_in_forms', $usedInForms);
    $attribute->save();
}

$installer->endSetup();
