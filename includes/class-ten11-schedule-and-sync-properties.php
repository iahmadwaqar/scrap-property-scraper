<?php
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
if (!is_admin()) {
    require_once(ABSPATH . 'wp-admin/includes/post.php');
}

class Ten11ScheduleAndSyncProperties
{
    const PROPERTIES_TABLE = '1011_properties_new';
    function __construct()
    {
        
        if (false == wp_next_scheduled('SYNC_PROPERTIES_EVENT')) {
            wp_schedule_event(time(), 'every_minute', 'SYNC_PROPERTIES_EVENT');
            $this->logMessage('Scheduled Every minute sync event for properties.');
        }
        add_action('SYNC_PROPERTIES_EVENT', array($this, 'setupSyncPropertiesCronEvent'));

    }
    public function setupSyncPropertiesCronEvent()
    {
        // $posts = get_posts(
        //     array(
        //         'post_type' => 'property',
        //         'post_status' => 'any',
        //         'posts_per_page' => 50,
        //         'orderby' => 'date',
        //         'order' => 'DESC',
        //         'fields' => 'ids'
        //     )
        // );
        // foreach ($posts as $post_id) {
        //     delete_post_meta($post_id, '', '', true);
        //     wp_delete_post($post_id, true);
        // }
        global $wpdb;
        $this->getPropertiesFromDB($wpdb);
    }

    function getPropertiesFromDB($wpdb)
    {
        $table_name = $wpdb->prefix . self::PROPERTIES_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $sql = "SELECT * FROM $table_name WHERE p_is_synced = 0 AND p_post_id IS NULL LIMIT 1;";
            try {
                $result = $wpdb->get_results($wpdb->prepare($sql));
                if ($result === false) {
                    throw new Exception("Error executing query: " . $wpdb->last_error);
                }
                foreach ($result as $row) {
                    $wpdb->update($table_name, array('p_is_synced' => 1), array('id' => $row->id), array('%d'), array('%d'));
                    $post_id = $this->storePropertyDataToPost($row);
                    $wpdb->update($table_name, array('p_post_id' => $post_id), array('id' => $row->id), array('%d'), array('%d'));
                }
                $this->removePreviousPosts();
            } catch (Exception $e) {
                // Log or handle the exception
                $this->logError($e->getMessage());
                return false;
            }
        }
    }

    function storePropertyDataToPost($property_data)
    {
        $url = $property_data->p_link;
        $property_id_indomio = $property_data->p_id;
        $title = $property_data->p_title;
        $description = $property_data->p_description;
        $address = $property_data->p_address;
        $agent = $property_data->p_agent;
        $agent_details = $property_data->p_agent_details;
        $country = $property_data->p_country;
        $city = $property_data->p_province;
        $area = $property_data->p_city;
        $address = $property_data->p_address;
        $price = $property_data->p_price;
        $price_formatted = $property_data->p_price_formatted;
        $rooms = $property_data->p_rooms;
        $bathrooms = $property_data->p_bathrooms;
        $surface = $property_data->p_surface;
        $year_of_construction = $property_data->p_year_of_construction;
        $images = $property_data->p_images;
        $featured_image = $property_data->p_featured_image;
        $latitude = $property_data->p_latitude;
        $longitude = $property_data->p_longitude;
        // Check if the post exists
        // $post_id = post_exists($title, $description);
        // if ($post_id) {
        //     wp_remote_post('https://enev8nwpcevym.x.pipedream.net', array(
        //         'body' => array(
        //             'url' => $url,
        //             'title' => $title,
        //             'post_id' => $post_id,
        //         ),
        //     )
        //     );
        //     return $post_id;
        // }

        $featured_image_id = $this->uploadFeatureImageAndGetId($featured_image);
        if (false == $featured_image_id) {
            return 0;
        }

        $agent_id = ($agent == 'private person') ? '1826525' : $this->saveAndGetAgentId($agent, $agent_details);
        $images_ids = $this->uploadImageAndGetId(unserialize($images));

        $property_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => 'property',
        );

        // Insert the property post
        $property_id = wp_insert_post($property_data);

        // Check if the post is successfully created
        if (!is_wp_error($property_id)) {
            // Dummy data for property meta keys
            $property_meta = array(
                'fave_property_price' => $price,
                'fave_property_bedrooms' => $rooms,
                'fave_property_bathrooms' => $bathrooms,
                'fave_property_size' => $surface,
                'fave_property_year' => $year_of_construction,
                'fave_property_map_address' => $address,
                'fave_property_map' => 1,
                'fave_property_location' => $latitude . ',' . $longitude,
                'houzez_geolocation_lat' => $longitude,
                'houzez_geolocation_long' => $longitude,
                'fave_agent_display_option' => 'agent_info',
                'fave_agents' => $agent_id,
                'fav_original_url' => $url,
                '_thumbnail_id' => $featured_image_id,
                'fave_property_id_indomio' => $property_id_indomio,
            );

            // Save property meta
            foreach ($property_meta as $key => $value) {
                update_post_meta($property_id, $key, $value);
            }
            foreach ($images_ids as $image_id) {
                add_post_meta($property_id, 'fave_property_images', $image_id);
            }

            wp_set_object_terms($property_id, 'property_status', 'For Sale', true);
            $this->addTaxonomyToPost($property_id, 'property_country', $country);
            $this->addTaxonomyToPost($property_id, 'property_city', $city);
            $area_term_id = $this->addTaxonomyToPost($property_id, 'property_area', $area);
            if ($area_term_id > 0) {
                $this->addAreaToCity($area_term_id, $city);
            }

        }
        return $property_id;
    }

    function uploadImageAndGetId($images_url)
    {
        $attachment_ids = array();
        foreach ($images_url as $external_image_url) {
            $attachment_id = media_sideload_image($external_image_url, 0, 'Property Image', 'id');
            if (is_wp_error($attachment_id)) {
                // Print error message if media_sideload_image() returns a WP_Error object
                $this->logError('Error uploading image: ' . $attachment_id->get_error_message());
            }
            if ($attachment_id) {
                $attachment_ids[] = $attachment_id;
            }
        }
        return $attachment_ids;

    }

    function uploadFeatureImageAndGetId($images_url)
    {
        $attachment_id = media_sideload_image($images_url, 0, 'Property Image', 'id');
        if ($attachment_id) {
            return $attachment_id;
        } else {
            return false;
        }
    }

    function saveAndGetAgentId($agent_name, $agent_details)
    {
        $agent_details_array = unserialize($agent_details);
        $args = array(
            'post_type' => 'houzez_agent', // Adjust post type if needed
            'post_status' => 'publish', // Adjust post status if needed
            'posts_per_page' => 1, // Limit to one result
            'title' => $agent_name // Search by post title
        );

        $posts = get_posts($args);
        if (!$posts) {
            $post = array(
                'post_title' => $agent_name,
                // 'post_content' => $agent_details,
                'post_status' => 'publish',
                'post_type' => 'houzez_agent',
            );
            $post_id = wp_insert_post($post);
            if (!is_wp_error($post_id) && $agent_details_array['agency']['agencyUrl']) {
                $agency_url = $agent_details_array['agency']['agencyUrl'];
                update_post_meta($post_id, 'houzez_agent_url', $agency_url);
            }
            return $post_id;
        } else {
            return $posts[0]->ID;
        }
    }

    function addTaxonomyToPost($property_id, $taxonomy_type, $taxonomy_value)
    {
        $term_id = 0;
        $is_term_exists = term_exists($taxonomy_value, $taxonomy_type);
        if (empty($is_term_exists)) {
            $term_stored = wp_insert_term($taxonomy_value, $taxonomy_type);
            $term_id = $term_stored['term_id'];
        }
        wp_set_object_terms($property_id, $taxonomy_value, $taxonomy_type, true);
        return $term_id;

    }

    function addAreaToCity($area_id, $city)
    {

        $city_slug = get_term_by('name', $city, 'property_city')->slug;
        $area_to_city_link_array = array(
            'parent_city' => $city_slug, // No need to urlencode the city slug here
        );
        $option_updated = update_option('_houzez_property_area_' . $area_id, $area_to_city_link_array);

        if ($option_updated) {
            $this->logMessage("Option updated successfully for $area_id term of $city.");
        } else {
            $this->logError("Option update failed for $area_id term of $city.");
        }

    }

    function removePreviousPosts()
    {
        $posts = get_posts(
            array(
                'post_type' => 'property',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
                'fields' => 'ids'
            )
        );
        foreach ($posts as $post_id) {
            delete_post_meta($post_id, '', '', true);
            wp_delete_post($post_id, true);
        }
    }

    function logMessage($message)
    {
        error_log('1011 Property Scraper Message: ' . $message);
    }

    function logError($message)
    {
        error_log('1011 Property Scraper Error: ' . $message);
    }
}
