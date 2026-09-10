<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    // Graft the Italian fiscal fields onto the sales address tables. Kept here so
    // module removal stays a single delete; the columns carry no data until an
    // address is saved with them populated.
    $quoteAddress = $schema->getTable('sales_flat_quote_address');
    $quoteAddress->addColumn('cod_fisc', Types::STRING, [
        'length' => 255,
        'notnull' => false,
        'comment' => 'Codice Fiscale',
    ]);
    $quoteAddress->addColumn('email_pec', Types::STRING, [
        'length' => 255,
        'notnull' => false,
        'comment' => 'Email PEC',
    ]);
    $quoteAddress->addColumn('cod_sdi', Types::STRING, [
        'length' => 255,
        'notnull' => false,
        'comment' => 'Codice SdI',
    ]);

    $orderAddress = $schema->getTable('sales_flat_order_address');
    $orderAddress->addColumn('cod_fisc', Types::STRING, [
        'length' => 255,
        'notnull' => false,
        'comment' => 'Codice Fiscale',
    ]);
    $orderAddress->addColumn('email_pec', Types::STRING, [
        'length' => 255,
        'notnull' => false,
        'comment' => 'Email PEC',
    ]);
    $orderAddress->addColumn('cod_sdi', Types::STRING, [
        'length' => 255,
        'notnull' => false,
        'comment' => 'Codice SdI',
    ]);
};
