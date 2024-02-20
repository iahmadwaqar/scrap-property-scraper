<?php

class Ten11PluginSetup
{
    const PROVINCES_TABLE = '1011_property_provinces';
    const PROPERTIES_TABLE = '1011_properties_new';
    public function __construct()
    {
        add_action('admin_menu', array($this, 'property_scraper_menu'));
        add_filter('cron_schedules', array($this, 'add_every_minute_cron_interval'));
        add_filter('cron_schedules', array($this, 'add_every_two_minute_cron_interval'));
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
            wp_schedule_event(time() + 300, 'every_two_minute', 'SCRAPE_PROPERTIES_EVENT');
            $this->logMessage('Scheduled Every minute scraping event for properties.');
        }
        if (false === wp_next_scheduled('SYNC_PROPERTIES_EVENT')) {
            wp_schedule_event(time() + 3600, 'every_minute', 'SYNC_PROPERTIES_EVENT');
            $this->logMessage('Scheduled Every minute sync event for properties.');
        }

    }
    function property_scraper_menu()
    {
        add_menu_page('Property Scraper', 'Property Scraper', 'manage_options', 'property-scraper', array($this, 'property_scraper_page'));
    }

    function property_scraper_page()
    {
        global $wpdb;
        $query = 'SELECT COUNT(*) as count FROM wp_1011_properties_new';
        $result = $wpdb->get_row($query);
        $total_properties = $result->count;
        $query = 'SELECT COUNT(*) as count FROM wp_1011_properties_new WHERE p_is_synced = 1';
        $result = $wpdb->get_row($query);
        $scrapped_properties = $result->count; ?>


        <div
            style="max-width: 600px; margin: 50px auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
            <h1 style="font-size: 30px; margin-bottom: 20px;">Property Scraper</h1>
            <p style="font-size: 20px; margin: 10px 0;">Total Properties:
                <span style='color: blue;'>
                    <?php echo $total_properties; ?>
                </span>
            </p>
            <p style="font-size: 20px; margin: 10px 0;">Synced Properties:
                <span style='color: green;'>
                    <?php echo $scrapped_properties; ?>
                </span>
            </p><button id="ajax-button"
                style="font-size: 16px; padding: 10px 20px; background-color: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer;">Get
                New Properties From Indomio</button>
        </div>

        <script>
            // Define the AJAX function
            function callAjaxFunction() {
                // Make AJAX call
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_new_properties' // Replace 'my_ajax_function' with your actual AJAX function name
                    },
                    success: function (response) {
                        console.log('AJAX call successful:', response);
                        // Handle response if needed
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX call error:', error);
                        // Handle error if needed
                    }
                });
            }

            // Bind click event to the button
            jQuery('#ajax-button').click(function () {
                var confirmation = confirm('Are you sure you want to get new properties?');
                if (confirmation) {
                    callAjaxFunction();
                }
            });
        </script>
        <?php
    }

    function add_every_minute_cron_interval($schedules)
    {
        $schedules['every_minute'] = array(
            'interval' => 30, // 1 minutes in seconds
            'display' => __('Every 1 Minutes'),
        );
        return $schedules;
    }

    function add_every_two_minute_cron_interval($schedules)
    {
        $schedules['every_two_minute'] = array(
            'interval' => 120, // 2 minutes in seconds
            'display' => __('Every 2 Minutes'),
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
            p_is_synced TINYINT(1) DEFAULT 0,
            p_post_id VARCHAR(255)  NULL
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
        wp_clear_scheduled_hook('SYNC_PROPERTIES_EVENT');
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
