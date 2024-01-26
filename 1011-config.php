<?php

require_once __DIR__ . '/vendor/autoload.php';
use Goutte\Client;

$client = new Client();

define('SCRAPE_PROVINCE_EVENT', '1011_scrape_province_cron_event');
define('SCRAPE_PROPERTIES_EVENT', '1011_scrape_properties_cron_event');