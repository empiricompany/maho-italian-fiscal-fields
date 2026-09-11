#!/usr/bin/env php
<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: AFL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

/**
 * Minimal Maho CLI shim for this package's test sandbox.
 *
 * The maho binary shipped with a project hardcodes MAHO_ROOT_DIR to the
 * project root; this package needs the same commands with the package root
 * (so the module itself is the installed store and app/etc/local.xml points
 * at the SQLite test database). Only the commands used by
 * tests/run-with-test-db.php are registered.
 */

define('MAHO_ROOT_DIR', dirname(__DIR__));
define('MAHO_PUBLIC_DIR', dirname(__DIR__) . '/public');

require dirname(__DIR__) . '/vendor/autoload.php';

use MahoCLI\Commands\CacheFlush;
use MahoCLI\Commands\Install;
use MahoCLI\Commands\Migrate;
use Symfony\Component\Console\Application;

$application = new Application('Maho', Mage::getVersion());
$application->addCommand(new Install());
$application->addCommand(new Migrate());
$application->addCommand(new CacheFlush());
$application->run();
