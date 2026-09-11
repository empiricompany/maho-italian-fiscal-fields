#!/usr/bin/env php
<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: AFL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

/**
 * PHPUnit runner with automatic SQLite test-database setup, modeled after
 * maho's own tests/pest-with-test-db.php (simplified: SQLite only, no
 * sample data, no HTTP server).
 *
 * 1. Installs a fresh Maho store into var/db/maho_test.sqlite with this
 *    package as the project root (the module's own installers run, so the
 *    EAV attributes and the sales address columns are created).
 * 2. Seeds the fixtures the API tests need: a company customer with fiscal
 *    fields on its address, and one order carrying the same fields.
 * 3. Runs vendor/bin/phpunit.
 *
 * Usage (from the project root, or via composer):
 *   php tests/run-with-test-db.php
 *   composer test:db
 */

final class TestDbRunner
{
    private const LOCAL_XML = 'app/etc/local.xml';
    private const LOCAL_XML_BACKUP = 'app/etc/local.xml.bak';

    private string $root;
    private string $dbPath;

    public function __construct()
    {
        $this->root = dirname(__DIR__);
        $this->dbPath = $this->root . '/var/db/maho_test.sqlite';
    }

    public function run(): int
    {
        $hadLocalXml = is_file(self::LOCAL_XML);
        if ($hadLocalXml && !copy(self::LOCAL_XML, self::LOCAL_XML_BACKUP)) {
            fwrite(STDERR, "Failed to back up app/etc/local.xml\n");
            return 1;
        }

        try {
            $this->prepareSqliteFile();
            $this->install();
            $this->seed();
            return $this->runPhpunit();
        } catch (Throwable $e) {
            fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
            return 1;
        } finally {
            if ($hadLocalXml) {
                copy(self::LOCAL_XML_BACKUP, self::LOCAL_XML);
                unlink(self::LOCAL_XML_BACKUP);
                echo "Restored original app/etc/local.xml\n";
            } elseif (is_file(self::LOCAL_XML)) {
                unlink(self::LOCAL_XML);
                echo "Removed sandbox app/etc/local.xml\n";
            }
        }
    }

    private function prepareSqliteFile(): void
    {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
        echo "Prepared SQLite test database: {$this->dbPath}\n";
    }

    private function install(): void
    {
        // The installer refuses to run when local.xml exists; the runner
        // removes it beforehand (fresh install every run).
        if (is_file(self::LOCAL_XML)) {
            unlink(self::LOCAL_XML);
        }

        $url = 'http://localhost:8901/';
        $cmd = implode(' ', [
            'php', escapeshellarg(dirname(__DIR__) . '/tests/maho-cli.php'), 'install', '--ansi',
            '--license_agreement_accepted', 'yes',
            '--locale', 'en_US',
            '--timezone', 'Europe/Rome',
            '--default_currency', 'EUR',
            '--db_engine', 'sqlite',
            '--db_name', escapeshellarg($this->dbPath),
            '--url', escapeshellarg($url),
            '--secure_base_url', escapeshellarg($url),
            '--use_secure', '0',
            '--use_secure_admin', '0',
            '--admin_lastname', 'admin',
            '--admin_firstname', 'admin',
            '--admin_email', 'admin@example.com',
            '--admin_username', 'admin',
            '--admin_password', 'testpassword123',
        ]);
        $this->execute($cmd);
        $this->execute('php ' . escapeshellarg(dirname(__DIR__) . '/tests/maho-cli.php') . ' migrate --ansi');
    }

    /**
     * Seed the fixtures the API tests discover: one company customer whose
     * address carries the fiscal fields, and one order whose billing
     * address carries the same fields.
     */
    private function seed(): void
    {
        require $this->root . '/vendor/autoload.php';

        Mage::setRoot($this->root);
        Mage::app('admin');

        $storeId = Mage::app()->getStore('default')->getId();
        $websiteId = Mage::app()->getWebsite()->getId();

        $fiscalFields = [
            'company' => 'Acme Srl',
            'cod_fisc' => 'BNCLCU80A01H501V',
            'email_pec' => 'acme-test@pec.it',
            'cod_sdi' => 'M5UXCR1',
        ];

        // Customer with a fiscal address
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId)
            ->setStore(Mage::app()->getStore($storeId))
            ->setEmail('fiscal-test@example.com')
            ->setFirstname('Luca')
            ->setLastname('Bianchi')
            ->setPassword('TestPassword123!');
        $customer->save();

        $address = Mage::getModel('customer/address');
        $address->setCustomerId($customer->getId())
            ->setFirstname('Luca')
            ->setLastname('Bianchi')
            ->setStreet(['Via dei Test 1'])
            ->setCity('Milano')
            ->setCountryId('IT')
            ->setRegion('Milano')
            ->setPostcode('20121')
            ->setTelephone('0212345678')
            ->addData($fiscalFields);
        $address->save();

        $customer->setDefaultBilling($address->getId());
        $customer->setDefaultShipping($address->getId());
        $customer->save();

        // Virtual product (no shipping needed for the seeded order)
        $product = Mage::getModel('catalog/product');
        $product->setStoreId(0)
            ->setWebsiteIds([$websiteId])
            ->setAttributeSetId($product->getDefaultAttributeSetId())
            ->setTypeId('virtual')
            ->setSku('fiscal-test-product')
            ->setName('Fiscal Test Product')
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setPrice(10)
            ->setTaxClassId(0)
            ->setStockData(['qty' => 100, 'manage_stock' => 0, 'use_config_manage_stock' => 0, 'is_in_stock' => 1]);
        $product->save();

        // Reload from the database so the quote sees a product with its
        // stock item attached (the in-memory instance does not carry one).
        $product = Mage::getModel('catalog/product')->setStoreId(0)->load($product->getId());

        // Order placed as guest, billing address carrying the fiscal fields
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId);
        $quote->setCustomerEmail('fiscal-test@example.com');
        $quote->addProduct($product, 1);

        $billing = $quote->getBillingAddress();
        $billing->addData([
            'firstname' => 'Luca',
            'lastname' => 'Bianchi',
            'street' => ['Via dei Test 1'],
            'city' => 'Milano',
            'country_id' => 'IT',
            'region' => 'Milano',
            'postcode' => '20121',
            'telephone' => '0212345678',
            'email' => 'fiscal-test@example.com',
        ] + $fiscalFields);

        $quote->setPaymentMethod('checkmo');
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $quote->collectTotals()->save();

        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();

        $order = $service->getOrder();
        if (!$order || !$order->getId()) {
            throw new RuntimeException('seed: order was not placed');
        }

        echo sprintf(
            "Seeded: customer %d, address %d, product %d, order %s\n",
            $customer->getId(),
            $address->getId(),
            $product->getId(),
            $order->getIncrementId(),
        );
    }

    private function runPhpunit(): int
    {
        passthru('php ' . escapeshellarg(dirname(__DIR__) . '/vendor/bin/phpunit'), $exitCode);
        return $exitCode;
    }

    private function execute(string $command): void
    {
        echo "Running: {$command}\n";
        passthru($command, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException("Command failed (exit {$exitCode}): {$command}");
        }
    }
}

exit((new TestDbRunner())->run());
