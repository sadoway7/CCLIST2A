<?php
/**
 * Plugin Name: CCList Admin
 * Description: A product management application for displaying and managing products in a simple catalog list format.
 * Version: 0.0.50
 * Author: James Sadoway
 * GitHub Plugin URI: sadoway7/CCLIST2A.git
 * GitHub Plugin URI: https://github.com/sadoway7/CCLIST2A.git
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CCLIST_VERSION', '0.0.4');
define('CCLIST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CCLIST_PLUGIN_URL', plugin_dir_url(__FILE__));

// Database version for updates
define('CCLIST_DB_VERSION', '1.0');

// Include required files
require_once CCLIST_PLUGIN_DIR . 'includes/data-handler.php';

// Activation hook
register_activation_hook(__FILE__, 'cclist_activate');

function cclist_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $table_categories = $wpdb->prefix . 'cclist2a_categories';
    $sql_categories = "CREATE TABLE IF NOT EXISTS $table_categories (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category_name varchar(100) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_categories);
    // Create products table
    $table_products = $wpdb->prefix . 'cclist2a_products';
    $sql_products = "CREATE TABLE IF NOT EXISTS $table_products (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        category varchar(100) NOT NULL,
        item varchar(255) NOT NULL,
        size varchar(100) DEFAULT NULL,
        price decimal(10,2) NOT NULL,
        quantity_min int DEFAULT 1,
        quantity_max int DEFAULT NULL,
        discount decimal(4,2) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY item_idx (item),
        KEY category_idx (category)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_products);

    // Save database version
    update_option('cclist_db_version', CCLIST_DB_VERSION);
}

// Admin menu
function cclist_add_admin_menu() {
    add_menu_page(
        'CCList Admin',
        'CCList Admin',
        'manage_options',
        'cclist-admin',
        'cclist_admin_page',
        'dashicons-products'
    );

    add_submenu_page(
        'cclist-admin',
        'Products',
        'Products',
        'manage_options',
        'cclist-admin',
        'cclist_admin_page'
    );

    add_submenu_page(
        'cclist-admin',
        'Add New Product',
        'Add New',
        'manage_options',
        'cclist-admin-new',
        'cclist_admin_new_product'
    );
}
add_action('admin_menu', 'cclist_add_admin_menu');

// Enqueue admin scripts and styles
function cclist_admin_enqueue_scripts($hook) {
    if (!strpos($hook, 'cclist-admin')) {
        return;
    }

    wp_enqueue_style(
        'cclist-admin-styles',
        CCLIST_PLUGIN_URL . 'admin/assets/css/admin.css',
        array(),
        CCLIST_VERSION
    );

    wp_enqueue_script(
        'cclist-admin-scripts',
        CCLIST_PLUGIN_URL . 'admin/assets/js/admin.js',
        array('jquery'),
        CCLIST_VERSION,
        true
    );

    wp_localize_script('cclist-admin-scripts', 'cclistAdmin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'adminUrl' => admin_url('admin.php'),
        'nonce' => wp_create_nonce('cclist_admin_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'cclist_admin_enqueue_scripts');

// Admin page callbacks
function cclist_admin_page() {
    include_once(CCLIST_PLUGIN_DIR . 'admin/admin.php');
}

function cclist_admin_new_product() {
    include_once(CCLIST_PLUGIN_DIR . 'admin/components/forms/product-form.php');
}

// Ajax handlers
function cclist_ajax_save_product() {
    check_ajax_referer('cclist_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
    }

    $data = array(
      'category' => sanitize_text_field($_POST['category']),
      'item' => sanitize_text_field($_POST['item']),
      'variations' => $_POST['variations']
    );

    // Check if this is an edit operation (if an old item name is supplied)
    if (isset($_POST['item_name'])) {
      $data['item_name'] = sanitize_text_field($_POST['item_name']);
    }

    $result = cclist_save_product($data);

    if ($result) {
        wp_send_json_success(array('message' => 'Product saved successfully'));
    } else {
        wp_send_json_error(array('message' => 'Error saving product'));
    }
}
add_action('wp_ajax_cclist_save_product', 'cclist_ajax_save_product');

function cclist_ajax_delete_product() {
    check_ajax_referer('cclist_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
    }

    $product_id = intval($_POST['id']);
    $result = cclist_delete_product($product_id);

    if ($result) {
        wp_send_json_success(array('message' => 'Product deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Error deleting product'));
    }
}
add_action('wp_ajax_cclist_delete_product', 'cclist_ajax_delete_product');

function cclist_ajax_delete_group() {
    check_ajax_referer('cclist_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cclist_products';
    $item = sanitize_text_field($_POST['item']);

    $result = $wpdb->delete(
        $table,
        array('item' => $item),
        array('%s')
    );

    if ($result !== false) {
        wp_send_json_success(array('message' => 'Product group deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Error deleting product group'));
    }
}
add_action('wp_ajax_cclist_delete_group', 'cclist_ajax_delete_group');

function cclist_ajax_duplicate_group(){
  check_ajax_referer('cclist_admin_nonce', 'nonce');

  if (!current_user_can('manage_options')) {
      wp_send_json_error(array('message' => 'Unauthorized access'));
  }

  $item = sanitize_text_field($_POST['item']);
  $result = cclist_duplicate_group($item);


  if($result){
    wp_send_json_success(array('message' => 'Product Group duplicated'));
  } else {
    wp_send_json_error(array('message' => 'Error duplicating group'));
  }
}

add_action('wp_ajax_cclist_duplicate_group', 'cclist_ajax_duplicate_group');


function cclist_ajax_get_product_form() {
    check_ajax_referer('cclist_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
    }

    $product_id = intval($_GET['id']);
    $product = cclist_get_product($product_id);

    if (!$product) {
        wp_send_json_error(array('message' => 'Product not found'));
    }

    ob_start();
    include(CCLIST_PLUGIN_DIR . 'admin/components/forms/product-form.php');
    $form = ob_get_clean();

    wp_send_json_success(array('form' => $form));
}
add_action('wp_ajax_cclist_get_product_form', 'cclist_ajax_get_product_form');

function cclist_ajax_import_products() {
    check_ajax_referer('cclist_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
    }
    error_log("cclist_ajax_import_products: Function called");
    error_log("cclist_ajax_import_products: raw POST data: " . print_r($_POST, true));
    $json_data = $_POST['data'];
    error_log("cclist_ajax_import_products: json_data after stripslashes: " . $json_data);
    $result = cclist_import_products($json_data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        wp_send_json_success($result);
    }
}
add_action('wp_ajax_cclist_import_products', 'cclist_ajax_import_products');

// Register REST API endpoints
function cclist_register_rest_routes() {
    register_rest_route('cclist/v1', '/products', array(
        'methods' => 'GET',
        'callback' => 'cclist_get_products_api',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'cclist_register_rest_routes');

// REST API callback
function cclist_get_products_api() {
    return cclist_get_products_for_api();
}

// Ajax handler to empty the products table
add_action('wp_ajax_cclist_empty_products_table', 'cclist_ajax_empty_products_table');
function cclist_ajax_empty_products_table() {
  check_ajax_referer('cclist_admin_nonce', 'nonce');

  if (!current_user_can('manage_options')) {
      wp_send_json_error(array('message' => 'Unauthorized access'));
  }

  $result = cclist_empty_products_table();

  if ($result) {
    wp_send_json_success();
  }
  else{
    wp_send_json_error(array('message' => 'Could not empty table'));
  }
}

add_action('wp_ajax_cclist_empty_categories_table', 'cclist_ajax_empty_categories_table');
function cclist_ajax_empty_categories_table() {
  check_ajax_referer('cclist_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
    }
  $result = cclist_empty_categories_table();
    if ($result) {
    wp_send_json_success();
  }
  else{
    wp_send_json_error(array('message' => 'Could not empty table'));
  }
}

// AJAX handler for CSV import
add_action('wp_ajax_cclist_import_csv', 'cclist_ajax_import_csv');
function cclist_ajax_import_csv() {
    check_ajax_referer('cclist_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
    }

    $csv_data = $_POST['data'];
    $result = cclist_import_csv($csv_data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        wp_send_json_success($result); // Send back success/failure and number of imported
    }
}