<?php

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
        if (false === wp_next_scheduled('SCRAPE_PROVINCE_EVENT')) {
            wp_schedule_single_event(time(), 'SCRAPE_PROVINCE_EVENT');
            $this->logMessage('Scheduled scraping event at activation.');
        }

        if (false === wp_next_scheduled('SCRAPE_PROPERTIES_EVENT')) {
            wp_schedule_event(time() + 300, 'every_minute', 'SCRAPE_PROPERTIES_EVENT');
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
        echo '<p> Next Property Scrap Scheduled at: ' . date('Y-m-d H:i:s', wp_next_scheduled('SCRAPE_PROPERTIES_EVENT')) . '</p>';
        echo '<p> Next Province Scrap Scheduled at: ' . date('Y-m-d H:i:s', wp_next_scheduled('SCRAPE_PROVINCE_EVENT')) . '</p>';
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
        wp_clear_scheduled_hook('SCRAPE_PROVINCE_EVENT');
        wp_clear_scheduled_hook('SCRAPE_PROPERTIES_EVENT');
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
