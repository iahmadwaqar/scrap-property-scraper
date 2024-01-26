<?php
/*
Plugin Name: 1011 Property Scraper
Description: A WordPress plugin for web scraping property data.
Version: 1.0
Author: 1011 Technologies
Author URI: https://www.1011tech.com
*/

function pri($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

require_once __DIR__ . '/vendor/autoload.php';
use Goutte\Client;

$client = new Client();

class Ten11PluginSetup
{
    const PROVINCES_TABLE = '1011_property_provinces';
    const PROPERTIES_TABLE = '1011_properties_new';
    public function __construct()
    {
        add_action('admin_menu', array($this, 'property_scraper_menu'));
        add_filter('cron_schedules', array($this, 'add_every_minute_cron_interval'));
        add_filter('cron_schedules', array($this, 'add_monthly_cron_interval'));

        register_activation_hook(__FILE__, array($this, 'activate'));

        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    function activate()
    {

        global $wpdb;
        $this->createTableforProvinces($wpdb);
        $this->createTableforProperties($wpdb);
        if (false === wp_next_scheduled('1011_scrape_province_cron_event')) {
            wp_schedule_single_event(time(), '1011_scrape_province_cron_event');
            $this->logMessage('Scheduled scraping event at activation.');
        }

        if (false === wp_next_scheduled('1011_scrape_properties_cron_event')) {
            wp_schedule_event(time() + 300, 'every_minute', '1011_scrape_properties_cron_event');
            $this->logMessage('Scheduled Every minute scraping event for properties.');
        }

    }
    function property_scraper_menu()
    {
        add_menu_page('Property Scraper', 'Property Scraper', 'manage_options', 'property-scraper', array($this, 'property_scraper_page'));
    }

    function property_scraper_page()
    {
        echo '<h1>Property Scraper</h1>';
        echo '<p>This is the property scraper page.</p>';
        echo '<p> Next Property Scrap Scheduled at: ' . date('Y-m-d H:i:s', wp_next_scheduled('1011_scrape_properties_cron_event')) . '</p>';
        echo '<p> Next Province Scrap Scheduled at: ' . date('Y-m-d H:i:s', wp_next_scheduled('1011_scrape_province_cron_event')) . '</p>';
        echo '<button id="scrape_provinces_button" >Scrape Provinces</button>';
    }

    function add_every_minute_cron_interval($schedules)
    {
        $schedules['every_minute'] = array(
            'interval' => 60, // 1 minutes in seconds
            'display' => __('Every 1 Minutes'),
        );
        return $schedules;
    }

    function add_monthly_cron_interval($schedules)
    {
        $schedules['monthly'] = array(
            'interval' => 2592000, // 1 month in seconds
            'display' => __('Every 1 Month'),
        );
        return $schedules;
    }

    private function createTableforProvinces($wpdb)
    {
        $table_name = $wpdb->prefix . self::PROVINCES_TABLE;
        $sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                link VARCHAR(255) NOT NULL,
                title LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                last_page INT NOT NULL,
                total_properties VARCHAR(255) NULL,
                scrapped_pages INT NOT NULL
            )";

        $this->createTable($wpdb, $table_name, $sql);
    }

    private function createTableforProperties($wpdb)
    {
        $table_name = $wpdb->prefix . self::PROPERTIES_TABLE;
        $sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            p_id VARCHAR(255) NULL,
            p_link VARCHAR(255)  NULL,
            p_title LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            p_description LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            p_agent VARCHAR(255)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_agent_details LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_country VARCHAR(255)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_province VARCHAR(255)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_region VARCHAR(255)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_city VARCHAR(255)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_address VARCHAR(255)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_price DECIMAL(10, 2) NOT NULL,
            p_price_formatted VARCHAR(255)  NULL,
            p_rooms VARCHAR(255)  NULL,
            p_bathrooms VARCHAR(255)  NULL,
            p_surface VARCHAR(255)  NULL,
            p_floors VARCHAR(255)  NULL,
            p_year_of_construction YEAR NULL,
            p_images LONGTEXT  NULL,
            p_featured_image VARCHAR(255)  NULL,
            p_latitude DECIMAL(10, 8)  NULL,
            p_longitude DECIMAL(11, 8)  NULL,
            p_json_data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
            p_is_synced TINYINT(1) DEFAULT 0
            )";

        $this->createTable($wpdb, $table_name, $sql);
    }

    private function createTable($wpdb, $tableName, $sql)
    {
        try {
            $result = $wpdb->query($sql);

            if ($result === false) {
                throw new Exception("Error creating table $tableName: " . $wpdb->last_error);
            }
        } catch (Exception $e) {
            $this->logError($e->getMessage());
        }
    }
    // Deactivation callback
    public function deactivate()
    {
        wp_clear_scheduled_hook('1011_scrape_province_cron_event');
        wp_clear_scheduled_hook('1011_scrape_properties_cron_event');
    }

    private function logMessage($message)
    {
        error_log("1011 Property Scraper Message: " . $message);
    }

    private function logError($error)
    {
        error_log("1011 Property Scraper Error: " . $error);
    }
}

$plugin_setup = new Ten11PluginSetup();

class Ten11ScheduleAndScrapeProvincesData
{
    const PROVINCES_TABLE = '1011_property_provinces';
    const PROPERTIES_TABLE = '1011_properties_new';
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
        if (false === wp_next_scheduled('1011_scrape_province_cron_event')) {

            wp_schedule_event(time(), 'monthly', '1011_scrape_province_cron_event');
            $this->logMessage('Scheduled Monthly scraping event.');
        }
        add_action('1011_scrape_province_cron_event', array($this, 'setupScrapeProvinceCronEvent'));
    }

    public function setupScrapeProvinceCronEvent()
    {
        global $wpdb;
        $this->emptyProvincesTable($wpdb);
        $this->scrapeAndStoreProvincesData($wpdb);
    }
    private function emptyProvincesTable($wpdb)
    {
        if ($wpdb instanceof wpdb) {
            $table_name = $wpdb->prefix . self::PROVINCES_TABLE;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $sql = "TRUNCATE TABLE $table_name";
                try {
                    $result = $wpdb->query($sql);
                    if ($result === false) {
                        throw new Exception("Error executing query: " . $wpdb->last_error);
                    }
                    $this->logMessage("Table $table_name truncated successfully.");
                } catch (Exception $e) {
                    // Log or handle the exception
                    $this->logError($e->getMessage());
                    return false;
                }
            } else {
                // Log or handle if the table doesn't exist
                $this->logError("Table $table_name does not exist.");
            }
        }
    }
    private function emptyPropertiesTable($wpdb)
    {
        if ($wpdb instanceof wpdb) {
            $table_name = $wpdb->prefix . self::PROPERTIES_TABLE;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $sql = "TRUNCATE TABLE $table_name";
                try {
                    $result = $wpdb->query($sql);
                    if ($result === false) {
                        throw new Exception("Error executing query: " . $wpdb->last_error);
                    }
                    $this->logMessage("Table $table_name truncated successfully.");
                } catch (Exception $e) {
                    // Log or handle the exception
                    $this->logError($e->getMessage());
                    return false;
                }
            } else {
                // Log or handle if the table doesn't exist
                $this->logError("Table $table_name does not exist.");
            }
        }
    }


    private function scrapeAndStoreProvincesData($wpdb)
    {
        try {
            $client = $this->client;
            $crawler = $client->request('GET', 'https://www.indomio.gr/en/');
            $links = $crawler->filter('a');
            $links->each(function ($link) use ($client, $wpdb) {
                $href = $link->attr('href');
                if (strpos($href, 'dhmoi') !== false) {
                    $cleanedLink = str_replace('/dhmoi/', '/', $href);
                    $subCrawler = $client->request('GET', $cleanedLink);
                    $lastPaginationText = $subCrawler->filter('.in-pagination__list .in-pagination__item')->last()->text();
                    $totalProperties = $subCrawler->filter('.in-resultsHeader__title')->text();
                    preg_match('/\b\d+(?:,\d+)?\b/', $totalProperties, $matches);
                    $number = $matches[0];
                    $data = [
                        'link' => $cleanedLink,
                        'title' => $link->text(),
                        'last_pagination_text' => $lastPaginationText,
                        'total_properties' => $number,
                    ];
                    $this->storeProvincesData($data, $wpdb);
                    sleep(1);
                }
            });
        } catch (Exception $e) {
            $this->logError($e->getMessage());
        }
    }

    private function storeProvincesData($data, $wpdb)
    {
        // Table name with prefix
        $table_name = $wpdb->prefix . self::PROVINCES_TABLE;

        // Insert data into the table
        $wpdb->insert(
            $table_name,
            array(
                'link' => $data['link'],
                'title' => $data['title'],
                'last_page' => $data['last_pagination_text'],
                'total_properties' => $data['total_properties'],
                'scrapped_pages' => 1,
            )
        );
    }

    private function logMessage($message)
    {
        error_log("1011 Property Scraper Message: " . $message);
    }

    private function logError($error)
    {
        error_log("1011 Property Scraper Error: " . $error);
    }
}

$propertyScrappingSetup = new Ten11ScheduleAndScrapeProvincesData($client);



class Ten11ScheduleAndScrapePropertiesData
{
    const PROVINCES_TABLE = '1011_property_provinces';
    const PROPERTIES_TABLE = '1011_properties_new';
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
        if (false === wp_next_scheduled('1011_scrape_properties_cron_event')) {
            wp_schedule_event(time(), 'every_minute', '1011_scrape_properties_cron_event');
            $this->logMessage('Scheduled Every minute scraping event for properties.');
        }
        add_action('1011_scrape_properties_cron_event', array($this, 'setupScrapePropertyCronEvent'));
    }
    public function setupScrapePropertyCronEvent()
    {
        global $wpdb;
        $this->scrapePropertyDataByProvince($wpdb);
    }
    public function scrapePropertyDataByProvince($wpdb)
    {
        $table_name = $wpdb->prefix . self::PROVINCES_TABLE;
        $sql = "SELECT * FROM $table_name WHERE scrapped_pages < last_page";
        $results = $wpdb->get_results($sql);

        try {
            foreach ($results as $result) {
                $scrapped_page = $result->scrapped_pages;
                $last_page = $result->last_page;
                $result_id = $result->id;

                if ($scrapped_page < $last_page) {
                    for ($i = $scrapped_page; $i <= $last_page; $i++) {
                        $this->scrapeAndStorePropertiesData($result->link, $i, $wpdb);
                        $wpdb->update(
                            $table_name,
                            array('scrapped_pages' => $i < $last_page ? $i + 1 : $i),
                            array('id' => $result_id)
                        );

                        // Break to scrape one page per iteration
                        break;
                    }
                } else {
                    $this->logMessage("No more pages to scrape for result id: $result_id\n");
                }
                // Break to scrape one page in one province per iteration
                break;
            }
        } catch (Exception $e) {
            $this->logError("Error while processing result id: $result_id. Message: " . $e->getMessage() . "\n");
        }
    }
    private function scrapeAndStorePropertiesData($page_link, $page_number, $wpdb)
    {
        $client = $this->client;
        $provinceToScrapUrl = $page_link . '?pag=' . $page_number;
        try {
            $crawler = $client->request('GET', $provinceToScrapUrl);
            $links = $crawler->filter('a')->links();
            foreach ($links as $link) {
                $href = $link->getUri();
                if (strpos($href, 'aggelies') !== false) {
                    $propertyId = null;
                    $propertyURL = $href;
                    $propertyTitle = null;
                    $propertyDescriptions = null;
                    $propertyAgent = null;
                    $propertyAgentDetails = null;
                    $propertyCountry = 'Greece';
                    $propertyAddress = null;
                    $propertyCity = null;
                    $propertyProvince = null;
                    $propertyRegion = null;
                    $propertyPrice = null;
                    $propertyPriceFormatted = null;
                    $propertyRooms = null;
                    $propertyBathrooms = null;
                    $propertySurface = null;
                    $propertyFloor = null;
                    $propertyYearOfConstruction = null;
                    $propertyImages = null;
                    $propertyFeaturedImage = null;
                    $propertyLatitude = null;
                    $propertyLongitude = null;
                    $propertyJSONData = null;
                    try {
                        $subCrawler = $client->request('GET', $href);
                        $jsonData = $subCrawler->filter('script#__NEXT_DATA__')->text();
                        $jsonDataToArray = json_decode($jsonData, true);
                        $props = $jsonDataToArray['props'];
                        if (isset($props['pageProps']['detailData']['realEstate'])) {
                            $propertyJSONData = $props['pageProps']['detailData']['realEstate'];
                        }
                        if (isset($propertyJSONData['id'])) {
                            $propertyId = $propertyJSONData['id'];
                        } else if (preg_match('/\/(\d+)\/$/', $href, $matches)) {
                            $propertyId = $matches[1];
                        }

                        if (isset($propertyJSONData['title'])) {
                            $propertyTitle = $propertyJSONData['title'];
                        } else {
                            $title = $subCrawler->filter('.in-titleBlock__title');
                            $propertyTitle = $title->count() ? $title->text() : null;
                        }

                        if (isset($propertyJSONData['properties'][0]['defaultDescription'])) {
                            $propertyDescriptions = $propertyJSONData['properties'][0]['defaultDescription'];
                        } else {
                            $descriptions = $subCrawler->filter('.in-readAll');
                            $propertyDescriptions = $descriptions->count() ? $descriptions->text() : null;
                        }

                        if (isset($propertyJSONData['advertiser']['agency']['label'])) {
                            $propertyAgent = $propertyJSONData['advertiser']['agency']['displayName'];
                        } else if (isset($propertyJSONData['advertiser']['supervisor']['label'])) {
                            $propertyAgent = $propertyJSONData['advertiser']['supervisor']['label'];
                        } else {
                            $agent = $subCrawler->filter('.in-referent a p');
                            $propertyAgent = $agent->count() ? $agent->text() : 'private';
                        }

                        if (isset($propertyJSONData['advertiser'])) {
                            $propertyAgentDetails = $propertyJSONData['advertiser'];
                        } else {
                            $agent = $subCrawler->filter('.in-referent');
                            $propertyAgentDetails = $agent->count() ? $agent->text() : null;
                        }
                        if (isset($propertyJSONData['properties'][0]['location']['province'])) {
                            $propertyProvince = $propertyJSONData['properties'][0]['location']['province'];
                        } else {

                        }

                        if (isset($propertyJSONData['properties'][0]['location']['city'])) {
                            $propertyCity = $propertyJSONData['properties'][0]['location']['city'];
                        } else {

                        }

                        if (isset($propertyJSONData['properties'][0]['location']['address'])) {
                            $propertyAddress = $propertyJSONData['properties'][0]['location']['address'];
                        } else {

                        }

                        if (isset($propertyJSONData['properties'][0]['location']['region'])) {
                            $propertyRegion = $propertyJSONData['properties'][0]['location']['region'];
                        } else {

                        }
                        if (isset($propertyJSONData['price']['value'])) {
                            $propertyPrice = $propertyJSONData['price']['value'];
                        } else {
                            $price = $subCrawler->filter('.in-detail__mainFeaturesPrice');
                            $propertyPrice = $price->count() ? $price->text() : null;
                        }

                        if (isset($propertyJSONData['price']['formattedValue'])) {
                            $propertyPriceFormatted = $propertyJSONData['price']['formattedValue'];
                        } else {
                            $price = $subCrawler->filter('.in-detail__mainFeaturesPrice');
                            $propertyPriceFormatted = $price->count() ? $price->text() : null;
                        }

                        if (isset($propertyJSONData['properties'][0]['rooms'])) {
                            $propertyRooms = $propertyJSONData['properties'][0]['rooms'];
                        } else {

                            $roomsElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="rooms"]');
                            $roomsElementText = $roomsElement->count() ? $roomsElement->text() : null;

                            $roomElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="room"]');
                            $roomElementText = $roomElement->count() ? $roomElement->text() : null;

                            $propertyRooms = $roomsElementText ?: $roomElementText;
                        }

                        if (isset($propertyJSONData['properties'][0]['bathrooms'])) {
                            $propertyBathrooms = $propertyJSONData['properties'][0]['bathrooms'];
                        } else {
                            $bathroomsElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="bathrooms"]');
                            $bathroomsElementText = $bathroomsElement->count() ? $bathroomsElement->text() : null;
                            $bathroomElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="bathroom"]');
                            $bathroomElementText = $bathroomElement->count() ? $bathroomElement->text() : null;
                            $propertyBathrooms = $bathroomsElementText ?: $bathroomElementText;
                        }

                        if (isset($propertyJSONData['properties'][0]['surface'])) {
                            $propertySurface = $propertyJSONData['properties'][0]['surface'];
                        } else {
                            $surfaceElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="surface"]');
                            $propertySurface = $surfaceElement->count() ? $surfaceElement->text() : null;
                        }

                        if (isset($propertyJSONData['properties'][0]['floors'])) {
                            $propertyFloor = $propertyJSONData['properties'][0]['floors'];
                        } else {
                            $floorElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="floor"]');
                            $propertyFloor = $floorElement->count() ? $floorElement->text() : null;
                        }

                        if (isset($propertyJSONData['properties'][0]['buildingYear'])) {
                            $propertyYearOfConstruction = $propertyJSONData['properties'][0]['buildingYear'];
                        } else {
                            $yearOfConstructionDt = $subCrawler->filterXPath('//dt[@class="in-realEstateFeatures__title" and text()="year of
construction"]');
                            if ($yearOfConstructionDt->count()) {
                                $yearOfConstructionDd = $yearOfConstructionDt->nextAll()->filter('dd');
                                $propertyYearOfConstruction = $yearOfConstructionDd->count() ? $yearOfConstructionDd->text() : null;
                            }
                        }

                        if (isset($propertyJSONData['properties'][0]['multimedia']['photos'])) {
                            $imagesArray = $propertyJSONData['properties'][0]['multimedia']['photos'];
                            $propertyImages = [];
                            foreach ($imagesArray as $image) {
                                $largeImageUrl = $image['urls']['large'];
                                $propertyImages[] = $largeImageUrl;
                            }

                        } else {

                            $slideElement = $subCrawler->filter('.nd-slideshow__content');
                            $propertyImages = $slideElement->filter('img')->each(function ($node) {
                                return $node->attr('src');
                            });
                        }

                        if (isset($propertyJSONData['properties'][0]['photo']['urls']['large'])) {
                            $propertyFeaturedImage = $propertyJSONData['properties'][0]['photo']['urls']['large'];
                        } else {
                            $featuredImage = $subCrawler->filter('.in-image__item');
                            $propertyFeaturedImage = $featuredImage->count() ? $featuredImage->text() : null;
                        }

                        if (isset($propertyJSONData['properties'][0]['location']['latitude'])) {
                            $propertyLatitude = $propertyJSONData['properties'][0]['location']['latitude'];
                        }

                        if (isset($propertyJSONData['properties'][0]['location']['longitude'])) {
                            $propertyLongitude = $propertyJSONData['properties'][0]['location']['longitude'];
                        }

                        $propertyDataToStore = [
                            'p_id' => $propertyId,
                            'p_link' => $propertyURL,
                            'p_title' => $propertyTitle,
                            'p_description' => $propertyDescriptions,
                            'p_agent' => $propertyAgent,
                            'p_agent_details' => serialize($propertyAgentDetails),
                            'p_country' => $propertyCountry,
                            'p_address' => $propertyAddress,
                            'p_city' => $propertyCity,
                            'p_province' => $propertyProvince,
                            'p_region' => $propertyRegion,
                            'p_price' => $propertyPrice,
                            'p_price_formatted' => $propertyPriceFormatted,
                            'p_rooms' => $propertyRooms,
                            'p_bathrooms' => $propertyBathrooms,
                            'p_surface' => $propertySurface,
                            'p_floors' => $propertyFloor,
                            'p_year_of_construction' => $propertyYearOfConstruction,
                            'p_images' => serialize($propertyImages),
                            'p_featured_image' => $propertyFeaturedImage,
                            'p_latitude' => $propertyLatitude,
                            'p_longitude' => $propertyLongitude,
                            'p_json_data' => serialize($propertyJSONData)
                        ];
                        $this->storePropertiesData($propertyDataToStore, $wpdb);
                        sleep(2);


                    } catch (Exception $e) {
                        $this->logError($e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            $this->logError($e->getMessage());
        }
    }

    private function storePropertiesData($propertyDataToStore, $wpdb)
    {
        $table_name = $wpdb->prefix . self::PROPERTIES_TABLE;
        // Insert data into the table
        $wpdb->insert($table_name, $propertyDataToStore);

        // Check for errors
        if ($wpdb->last_error) {
            $this->logError("Error: " . $wpdb->last_error);
        } else {
            $this->logMessage("Data inserted successfully!");
        }


    }

    private function logMessage($message)
    {
        error_log("1011 Property Scraper Message: " . $message);
    }

    private function logError($error)
    {
        error_log("1011 Property Scraper Error: " . $error);
    }
}

$client = new Client();
$propertyScrappingSetup = new Ten11ScheduleAndScrapePropertiesData($client);