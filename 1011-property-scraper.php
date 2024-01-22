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
function property_scraper_page()
{
    echo '<h1>Property Scraper</h1>';
}
function property_scraper_menu()
{
    add_menu_page('Property Scraper', 'Property Scraper', 'manage_options', 'property-scraper', 'property_scraper_page');
}
add_action('admin_menu', 'property_scraper_menu');


require_once __DIR__ . '/vendor/autoload.php'; // Adjust the path based on your project structure
use Goutte\Client;


class ScrapProvincesLinks
{
    public function scrapeAndStoreData($wpdb)
    {
        $client = new Client();
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
                $this->scrapeAndStoreProvincesData($data, $wpdb);
            }
        });
    }

    private function scrapeAndStoreProvincesData($data, $wpdb)
    {
        // Table name with prefix
        $table_name = $wpdb->prefix . '1011_property_provinces';

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

    public function createProvincesTable($wpdb)
    {
        // Table name with prefix
        $table_name = $wpdb->prefix . '1011_property_provinces';

        // SQL to create the table
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id INT AUTO_INCREMENT PRIMARY KEY,
                link VARCHAR(255) NOT NULL,
                title VARCHAR(255) NOT NULL,
                last_page VARCHAR(255),
                total_properties VARCHAR(255),
                scrapped_pages VARCHAR(255)
            )";

        // Execute the SQL statement
        $wpdb->query($sql);
    }
}

// add_action('init', function () {
//     global $wpdb;
//     $pluginInstance = new ScrapProvincesLinks();
//     $pluginInstance->createProvincesTable($wpdb);
//     $pluginInstance->scrapeAndStoreData($wpdb);
// });

class ScrapPropertyData
{
    public function scrapePropertyDataByProvince($wpdb)
    {
        $table_name = $wpdb->prefix . '1011_property_provinces';
        $sql = "SELECT * FROM $table_name WHERE scrapped_pages < last_page";
        $results = $wpdb->get_results($sql);
        foreach ($results as $result) {
            $scrapped_page = $result->scrapped_pages;
            $last_page = $result->last_page;
            $result_id = $result->id;
            for ($i = $scrapped_page; $i <= $last_page; $i++) {
                $this->scrapePropertyData($result->link, $i);
                $wpdb->update(
                    $table_name,
                    array('scrapped_pages' => $i < $last_page ? $i + 1 : $i),
                    array('id' => $result_id)
                );
                break;
            }
            break;
        }
    }
    public function scrapePropertyData($page_link, $page_number)
    {
        $client = new Client();
        $provinceToScrapUrl = $page_link . '?pag=' . $page_number;
        echo 'Province To Scrap Url: ' . $provinceToScrapUrl . '<br>';
        $crawler = $client->request('GET', $provinceToScrapUrl);
        $links = $crawler->filter('a')->links();
        foreach ($links as $link) {
            // sleep(5);
            break;
            $href = $link->getUri();
            if (strpos($href, 'aggelies') !== false) {
                $subCrawler = $client->request('GET', $href);
                // $translateButton = $subCrawler->filter('.nd-button.nd-button--link.in-description__button');
                // $translateButtonText = $translateButton->each(function ($node) {
                //     return $node->text();
                // });
                // Use preg_match to extract the number from the URL
                if (preg_match('/\/(\d+)\/$/', $href, $matches)) {
                    // $matches[1] contains the extracted number
                    $propertyId = $matches[1];
                }
                $title = $subCrawler->filter('.in-titleBlock__title');
                $titleText = $title->count() ? $title->text() : null;

                $descriptions = $subCrawler->filter('.in-readAll');
                $descriptionsText = $descriptions->count() ? $descriptions->text() : null;

                $price = $subCrawler->filter('.nd-list__item.in-feat__item.in-feat__item--main.in-detail__mainFeaturesPrice');
                $priceText = $price->count() ? $price->text() : null;

                $roomsElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="rooms"]');
                $roomsElementText = $roomsElement->count() ? $roomsElement->text() : null;

                $roomElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="room"]');
                $roomElementText = $roomElement->count() ? $roomElement->text() : null;

                $surfaceElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="surface"]');
                $surfaceElementText = $surfaceElement->count() ? $surfaceElement->text() : null;

                $bathroomsElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="bathrooms"]');
                $bathroomsElementText = $bathroomsElement->count() ? $bathroomsElement->text() : null;

                $bathroomElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="bathroom"]');
                $bathroomElementText = $bathroomElement->count() ? $bathroomElement->text() : null;

                $floorElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="floor"]');
                $floorElementText = $floorElement->count() ? $floorElement->text() : null;


                $yearOfConstructionDt = $subCrawler->filterXPath('//dt[@class="in-realEstateFeatures__title" and text()="year of construction"]');
                if ($yearOfConstructionDt->count()) {
                    $yearOfConstructionDd = $yearOfConstructionDt->nextAll()->filter('dd');
                    $yearOfConstruction = $yearOfConstructionDd->count() ? $yearOfConstructionDd->text() : null;
                } else {
                    $yearOfConstruction = null;
                }

                $slideElement = $subCrawler->filter('.nd-slideshow__content');
                $imagesURL = $slideElement->filter('img')->each(function ($node) {
                    return $node->attr('src');
                });

                echo 'Title ' . $titleText . '<br>';
                // echo 'Descriptions '. $descriptionsText .'<br>';
                // echo 'Price ' . $priceText . '<br>';
                // echo 'Rooms ' . $roomsElementText . '<br>';
                // echo 'Room ' . $roomElementText . '<br>';
                // echo 'Surface ' . $surfaceElementText . '<br>';
                // echo 'Bathrooms ' . $bathroomsElementText . '<br>';
                // echo 'Bathroom ' . $bathroomElementText . '<br>';
                // echo 'Floor' . $floorElementText . '<br>';
                // echo 'Year of Construction ' . $yearOfConstruction . '<br>';
                // echo 'Images '. $imagesURL .'<br>';

                // echo '<br><br>';
                $response = array(
                    'message' => 'Iteration completed successfully.',
                    'title' => $titleText,
                    'descriptions' => $descriptionsText,
                    'price' => $priceText,
                    'rooms' => $roomsElementText,
                    'room' => $roomElementText,
                    'surface' => $surfaceElementText,
                    'bathrooms' => $bathroomsElementText,
                    'bathroom' => $bathroomElementText,
                    'floor' => $floorElementText,
                    'year_of_construction' => $yearOfConstruction,
                );
                // break;
                sleep(2);
            }
        }
    }
}

add_action('init', function () {
    global $wpdb;
    // $propertyInstance = new ScrapPropertyData();
    // $propertyInstance->scrapePropertyDataByProvince($wpdb);
    // schedule_scrape_property_event();
    function SCRAP($wpdb)
    {

        
        $response = array(
            'message' => 'Iteration completed successfully.',
            'title' => 'titleText',
            'descriptions' => 'descriptionsText',
            'price' => 'priceText',
            'rooms' => 'roomsElementText',
            'room' => 'roomElementText',
            'surface' => 'surfaceElementText',
            'bathrooms' => 'bathroomsElementText',
            'bathroom' =>'bathroomElementText',
            'floor' => 'floorElementText',
            'year_of_construction' => 'yearOfConstruction',
            'images' => 'imagesURL',
        );
        $table_name = $wpdb->prefix . '1011_properties_new';
        $sql = "INSERT INTO $table_name (title, url, property_id, descriptions, price, rooms, room, surface, bathrooms, bathroom, floor, year_of_construction, images) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)";
        $data = array(
            $response['title'],
            $response['url'],
            $response['property_id'],
            $response['descriptions'],
            $response['price'],
            $response['rooms'],
            $response['room'],
            $response['surface'],
            $response['bathrooms'],
            $response['bathroom'],
            $response['floor'],
            $response['year_of_construction'],
            $response['images'],
        );
        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
        $wpdb->query($wpdb->prepare($sql, $data, $format));
        if ($wpdb->last_error) {
            echo "Error: " . $wpdb->last_error;
        } else {
            echo "Data inserted successfully!";
        }
        
return;
        $client = new Client();
        $provinceToScrapUrl = 'https://www.indomio.gr/en/pwlhsh-katoikies/athina-anatolika-proastia-perifereia/' . '?pag=' . '1';
        echo 'Province To Scrap Url: ' . $provinceToScrapUrl . '<br>';
        $crawler = $client->request('GET', $provinceToScrapUrl);
        $links = $crawler->filter('a')->links();
        foreach ($links as $link) {
            $href = $link->getUri();
            if (strpos($href, 'aggelies') !== false) {
                $subCrawler = $client->request('GET', $href);
                // $translateButton = $subCrawler->filter('.nd-button.nd-button--link.in-description__button');
                // $translateButtonText = $translateButton->each(function ($node) {
                //     return $node->text();
                // });
                // Use preg_match to extract the number from the URL
                if (preg_match('/\/(\d+)\/$/', $href, $matches)) {
                    // $matches[1] contains the extracted number
                    $propertyId = $matches[1];
                }
                $title = $subCrawler->filter('.in-titleBlock__title');
                $titleText = $title->count() ? $title->text() : null;

                $descriptions = $subCrawler->filter('.in-readAll');
                $descriptionsText = $descriptions->count() ? $descriptions->text() : null;

                $price = $subCrawler->filter('.nd-list__item.in-feat__item.in-feat__item--main.in-detail__mainFeaturesPrice');
                $priceText = $price->count() ? $price->text() : null;

                $roomsElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="rooms"]');
                $roomsElementText = $roomsElement->count() ? $roomsElement->text() : null;

                $roomElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="room"]');
                $roomElementText = $roomElement->count() ? $roomElement->text() : null;

                $surfaceElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="surface"]');
                $surfaceElementText = $surfaceElement->count() ? $surfaceElement->text() : null;

                $bathroomsElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="bathrooms"]');
                $bathroomsElementText = $bathroomsElement->count() ? $bathroomsElement->text() : null;

                $bathroomElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="bathroom"]');
                $bathroomElementText = $bathroomElement->count() ? $bathroomElement->text() : null;

                $floorElement = $subCrawler->filterXPath('//li[@class="nd-list__item in-feat__item" and @aria-label="floor"]');
                $floorElementText = $floorElement->count() ? $floorElement->text() : null;


                $yearOfConstructionDt = $subCrawler->filterXPath('//dt[@class="in-realEstateFeatures__title" and text()="year of construction"]');
                if ($yearOfConstructionDt->count()) {
                    $yearOfConstructionDd = $yearOfConstructionDt->nextAll()->filter('dd');
                    $yearOfConstruction = $yearOfConstructionDd->count() ? $yearOfConstructionDd->text() : null;
                } else {
                    $yearOfConstruction = null;
                }

                $slideElement = $subCrawler->filter('.nd-slideshow__content');
                $imagesURL = $slideElement->filter('img')->each(function ($node) {
                    return $node->attr('src');
                });

                // echo 'Title---------------- ' . $titleText . '<br>';
                echo 'URL-------------------' . $href . '<br>';
                // echo 'ID------------------- ' . $propertyId . '<br>';
                // echo 'Descriptions--------- '. $descriptionsText .'<br>';
                // echo 'Price---------------- ' . $priceText . '<br>';
                // echo 'Rooms---------------- ' . $roomsElementText . '<br>';
                // echo 'Room----------------- ' . $roomElementText . '<br>';
                // echo 'Surface-------------- ' . $surfaceElementText . '<br>';
                // echo 'Bathrooms------------ ' . $bathroomsElementText . '<br>';
                // echo 'Bathroom------------- ' . $bathroomElementText . '<br>';
                // echo 'Floor-----------------' . $floorElementText . '<br>';
                // echo 'Year of Construction- ' . $yearOfConstruction . '<br>';
                // echo 'Images--------------- '. pri($imagesURL) .'<br>';

                // echo '<br><br>';
                $response = array(
                    'message' => 'Iteration completed successfully.',
                    'title' => $titleText,
                    'descriptions' => $descriptionsText,
                    'price' => $priceText,
                    'rooms' => $roomsElementText,
                    'room' => $roomElementText,
                    'surface' => $surfaceElementText,
                    'bathrooms' => $bathroomsElementText,
                    'bathroom' => $bathroomElementText,
                    'floor' => $floorElementText,
                    'year_of_construction' => $yearOfConstruction,
                    'images' => serialize($imagesURL),
                );
                $table_name = $wpdb->prefix . '1011_properties_new';
                $sql = "INSERT INTO $table_name (title, url, property_id, descriptions, price, rooms, room, surface, bathrooms, bathroom, floor, year_of_construction, images) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)";
                $data = array(
                    $response['title'],
                    $response['url'],
                    $response['property_id'],
                    $response['descriptions'],
                    $response['price'],
                    $response['rooms'],
                    $response['room'],
                    $response['surface'],
                    $response['bathrooms'],
                    $response['bathroom'],
                    $response['floor'],
                    $response['year_of_construction'],
                    $response['images'],
                );
                $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
                $wpdb->query($wpdb->prepare($sql, $data, $format));
                if ($wpdb->last_error) {
                    echo "Error: " . $wpdb->last_error;
                } else {
                    echo "Data inserted successfully!";
                }
                
                break;
                sleep(2);
            }
        }
    };
    SCRAP();
    global $wpdb;
    $table_name = $wpdb->prefix . '1011_properties_new';
    // $sql = "CREATE TABLE IF NOT EXISTS $table_name (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     title VARCHAR(255) NOT NULL,
    //     url VARCHAR(255) NOT NULL,
    //     property_id VARCHAR(255) NOT NULL,
    //     descriptions VARCHAR(255) NOT NULL,
    //     price VARCHAR(255) NOT NULL,
    //     rooms VARCHAR(255) NOT NULL,
    //     room VARCHAR(255) NOT NULL,
    //     surface VARCHAR(255) NOT NULL,
    //     bathrooms VARCHAR(255) NOT NULL,
    //     bathroom VARCHAR(255) NOT NULL,
    //     floor VARCHAR(255) NOT NULL,
    //     year_of_construction VARCHAR(255) NOT NULL,
    //     images VARCHAR(255) NOT NULL
    // )";

    // $wpdb->query($sql);
});

function schedule_scrape_property_event()
{
    if (!wp_next_scheduled('scrape_property_cron_event')) {
        echo 'Scheduling cron event...';
        wp_schedule_event(time(), 'every_minutes', 'scrape_property_cron_event');
    }
}

// Hook the event to your function
add_action('scrape_property_cron_event', 'scrape_property_cron_function');

// Function to be executed during the cron event
function scrape_property_cron_function()
{
    global $wpdb;
    $propertyInstance = new ScrapPropertyData();
    $propertyInstance->scrapePropertyDataByProvince($wpdb);
}

// Define the custom cron interval
add_filter('cron_schedules', 'add_every_minutes_cron_interval');

function add_every_minutes_cron_interval($schedules)
{
    $schedules['every_minutes'] = array(
        'interval' => 60, // 1 minutes in seconds
        'display' => __('Every 1 Minutes'),
    );
    return $schedules;
}
// function unschedule_scrape_property_event() {
//     wp_clear_scheduled_hook('scrape_property_cron_event');
// }
