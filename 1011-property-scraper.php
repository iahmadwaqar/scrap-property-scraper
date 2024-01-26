<?php
/*
Plugin Name: 1011 Property Scraper
Description: A WordPress plugin for web scraping property data.
Version: 1.0
Author: 1011 Technologies
Author URI: https://www.1011tech.com
*/

require_once __DIR__ . '/1011-config.php';
require_once __DIR__ . '/includes/class-ten11-plugin-setup.php';
require_once __DIR__ . '/includes/class-ten11-schedule-and-scrape-provinces.php';
require_once __DIR__ . '/includes/class-ten11-schedule-and-scrape-properties.php';


$plugin_setup = new Ten11PluginSetup();

$propertyScrappingProvincesSetup  = new Ten11ScheduleAndScrapeProvincesData($client);
$propertyScrappingPropertiesSetup  = new Ten11ScheduleAndScrapePropertiesData($client);