<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get products for API response
 * Formats data according to cclist app requirements
 */
function cclist_get_products_for_api() {
    global $wpdb;
    $table = $wpdb->prefix . 'cclist_products';
    
    try {
        // Get all products ordered by item name and quantity_min
        $products = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY item ASC, quantity_min ASC",
            ARRAY_A
        );

        if (empty($products)) {
            return new WP_REST_Response([], 200);
        }

        // Group products by item to handle variations
        $grouped_products = array();
        foreach ($products as $product) {
            $key = $product['item'];
            if (!isset($grouped_products[$key])) {
                $grouped_products[$key] = array();
            }
            $grouped_products[$key][] = $product;
        }

        // Format products for API response
        $formatted_products = array();
        foreach ($grouped_products as $item_group) {
            // Check if this is a size variation product
            $has_size_variations = count(array_unique(array_column($item_group, 'size'))) > 1;
            
            if ($has_size_variations) {
                // Handle size variations (like Cobalt Carbonate)
                $base_product = $item_group[0];
                $prices = array();
                
                foreach ($item_group as $variation) {
                    $prices[$variation['size']] = floatval($variation['price']);
                }

                $formatted_products[] = array(
                    'category' => $base_product['category'],
                    'item' => $base_product['item'],
                    'size' => null,
                    'price' => floatval($base_product['price']),
                    'prices' => $prices,
                    'quantity_min' => intval($base_product['quantity_min']),
                    'quantity_max' => $base_product['quantity_max'] ? intval($base_product['quantity_max']) : null
                );
            } else {
                // Handle quantity-based price breaks (like Buffstone)
                foreach ($item_group as $product) {
                    $formatted_product = array(
                        'category' => $product['category'],
                        'item' => $product['item'],
                        'size' => $product['size'],
                        'price' => floatval($product['price']),
                        'quantity_min' => intval($product['quantity_min']),
                        'quantity_max' => $product['quantity_max'] ? intval($product['quantity_max']) : null
                    );

                    // Add discount if present
                    if (!empty($product['discount'])) {
                        $formatted_product['discount'] = floatval($product['discount']);
                    }

                    $formatted_products[] = $formatted_product;
                }
            }
        }

        return new WP_REST_Response($formatted_products, 200);

    } catch (Exception $e) {
        return new WP_Error(
            'server_error',
            'An error occurred while fetching products',
            array('status' => 500)
        );
    }
}

/**
 * Get all products for admin display
 * Groups products by item name
 */
function cclist_get_products_grouped() {
    global $wpdb;
    $table = $wpdb->prefix . 'cclist_products';
    
    $products = $wpdb->get_results(
        "SELECT * FROM $table ORDER BY category ASC, item ASC, quantity_min ASC",
        ARRAY_A
    );
    
    if (empty($products)) {
        return array();
    }

    // Group products by item
    $grouped = array();
    foreach ($products as $product) {
        $key = $product['item'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = array(
                'item' => $product['item'],
                'category' => $product['category'],
                'variations' => array()
            );
        }
        $grouped[$key]['variations'][] = $product;
    }

    return $grouped;
}

/**
 * Get a single product by ID
 */
function cclist_get_product($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cclist_products';
    
    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
        ARRAY_A
    );
}

/**
 * Insert or update a product
 */
function cclist_save_product($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'cclist_products';
    
    $fields = array(
        'category' => sanitize_text_field($data['category']),
        'item' => sanitize_text_field($data['item']),
        'size' => !empty($data['size']) ? sanitize_text_field($data['size']) : null,
        'price' => floatval($data['price']),
        'quantity_min' => isset($data['quantity_min']) ? intval($data['quantity_min']) : 1,
        'quantity_max' => !empty($data['quantity_max']) ? intval($data['quantity_max']) : null,
        'discount' => !empty($data['discount']) ? floatval($data['discount']) : null
    );
    
    if (!empty($data['id'])) {
        // Update existing product
        $wpdb->update(
            $table,
            $fields,
            array('id' => intval($data['id'])),
            array('%s', '%s', '%s', '%f', '%d', '%d', '%f'),
            array('%d')
        );
        return intval($data['id']);
    } else {
        // Insert new product
        $wpdb->insert(
            $table,
            $fields,
            array('%s', '%s', '%s', '%f', '%d', '%d', '%f')
        );
        return $wpdb->insert_id;
    }
}

/**
 * Delete a product
 */
function cclist_delete_product($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cclist_products';
    
    return $wpdb->delete(
        $table,
        array('id' => $id),
        array('%d')
    );
}

/**
 * Get distinct categories
 */
function cclist_get_categories() {
    global $wpdb;
    $table = $wpdb->prefix . 'cclist_products';
    
    return $wpdb->get_col("SELECT DISTINCT category FROM $table ORDER BY category ASC");
}

/**
 * Import products from JSON data
 */
function cclist_import_products($json_data) {
    $products = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_json', 'Invalid JSON data provided');
    }

    $success_count = 0;
    foreach ($products as $product) {
        if (cclist_save_product($product)) {
            $success_count++;
        }
    }

    return array(
        'success' => true,
        'imported' => $success_count,
        'total' => count($products)
    );
}

/**
* Duplicate a product group
*/
function cclist_duplicate_group($item_name){
    global $wpdb;
    $table_products = $wpdb->prefix . 'cclist_products';

    // Get all variations for a product by item name
    $products = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_products WHERE item = %s", $item_name),
        ARRAY_A
    );

    if (empty($products)) {
      return false;
    }
    $new_item_name = $item_name . ' (Copy)';

    $existing_copies = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_products WHERE item LIKE %s",
            $new_item_name . '%'
          )
    );

    if($existing_copies > 0){
      $new_item_name .= ' (' . ($existing_copies + 1) . ')';
    }

    // Insert a copy of each variation
    foreach ($products as $product) {
        $data = array(
            'category' => $product['category'],
            'item' => $new_item_name,
            'size' => $product['size'],
            'price' => $product['price'],
            'quantity_min' => $product['quantity_min'],
            'quantity_max' => $product['quantity_max'],
            'discount' => $product['discount']
        );
        cclist_save_product($data);
    }
    return true;
}