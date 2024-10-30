<?php
require __DIR__ . '/vendor/autoload.php';
define('PLUGIN_NAME', 'ClinicSoftware');
define('PLUGIN_SLUG', 'clinicsoftware-woocommerce');
define('PLUGIN_PATH', WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/');

use ClinicSoftware\ClinicSoftware;

ClinicSoftware::instance()->my_uninstall();
