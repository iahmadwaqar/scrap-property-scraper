<?php
use Goutte\Client;

class Ten11ScheduleAndScrapeProvincesData
{
    const PROVINCES_TABLE = '1011_property_provinces';
    const PROPERTIES_TABLE = '1011_properties_new';
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
        if (false === wp_next_scheduled('SCRAPE_PROVINCE_EVENT')) {

            wp_schedule_event(time(), 'monthly', 'SCRAPE_PROVINCE_EVENT');
            $this->logMessage('Scheduled Monthly scraping event.');
        }
        add_action('SCRAPE_PROVINCE_EVENT', array($this, 'setupScrapeProvinceCronEvent'));
    }

    public function setupScrapeProvinceCronEvent()
    {
        global $wpdb;
        // $this->emptyProvincesTable($wpdb);
        // $this->emptyPropertiesTable($wpdb);
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
