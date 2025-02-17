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
    $table = $wpdb->prefix . 'cclist2a_products'; // Changed prefix

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
    $table = $wpdb->prefix . 'cclist2a_products'; // Changed prefix

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
    $table = $wpdb->prefix . 'cclist2a_products'; // Changed prefix

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
    $table = $wpdb->prefix . 'cclist2a_products'; // Changed prefix

    error_log("cclist_save_product called. Data: " . print_r($data, true));

    // Check if this is an import or a form submission
    if (isset($data['variations'])) {
        // Form submission: process variations array
        $category = sanitize_text_field($data['category']);
        $item_name = sanitize_text_field($data['item']);
        $variations = $data['variations'];

        // For edits, delete existing variations for the item
        if (isset($data['item_name'])) {
            $deleted = $wpdb->delete(
                $table,
                array('item' => $data['item_name']),
                array('%s')
            );
            error_log("Deleting variations for item: " . $data['item_name'] . ". Result: " . $deleted);
        }

        if (!is_array($variations) || empty($variations)) {
            error_log("Error: Variations data is not an array or is empty.");
            return false;
        }

        foreach ($variations as $variation) {
            if (empty($variation['price'])) {
                error_log("Skipping variation due to empty price: " . print_r($variation, true));
                continue;
            }
            $fields = array(
                'category' => $category,
                'item' => $item_name,
                'size' => !empty($variation['size']) ? sanitize_text_field($variation['size']) : null,
                'price' => floatval($variation['price']),
                'quantity_min' => isset($variation['quantity_min']) ? intval($variation['quantity_min']) : 1,
                'quantity_max' => !empty($variation['quantity_max']) ? intval($variation['quantity_max']) : null,
                'discount' => !empty($variation['discount']) ? floatval($variation['discount']) : null
            );

            error_log("Inserting variation: " . print_r($fields, true));
            $inserted = $wpdb->insert(
                $table,
                $fields,
                array('%s', '%s', '%s', '%f', '%d', '%d', '%f')
            );
            if ($inserted === false) {
                error_log("WPDB Error:" . $wpdb->last_error);
            } else {
                error_log("Variation inserted successfully. Insert ID: " . $wpdb->insert_id);
            }
        }
    } else {
        // Import: process individual product data
        $fields = array(
            'category' => sanitize_text_field($data['category']),
            'item' => sanitize_text_field($data['item']),
            'size' => !empty($data['size']) ? sanitize_text_field($data['size']) : null,
            'price' => floatval($data['price']),
            'quantity_min' => isset($data['quantity_min']) ? intval($data['quantity_min']) : 1,
            'quantity_max' => !empty($data['quantity_max']) ? intval($data['quantity_max']) : null,
            'discount' => !empty($data['discount']) ? floatval($data['discount']) : null
        );

        error_log("Inserting imported product: " . print_r($fields, true));
        $inserted = $wpdb->insert(
            $table,
            $fields,
            array('%s', '%s', '%s', '%f', '%d', '%d', '%f')
        );
        if ($inserted === false) {
            error_log("WPDB Error:" . $wpdb->last_error);
        }
         else {
            error_log("Imported Product inserted successfully. Insert ID: " . $wpdb->insert_id);
        }
    }

    return true;
}
/**
 * Delete a product
 */
function cclist_delete_product($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cclist2a_products'; // Changed prefix

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
     $table = $wpdb->prefix . 'cclist2a_products';
    return $wpdb->get_col("SELECT DISTINCT category FROM $table ORDER BY category ASC");
}

/**
* Add a category if it does not exist
*/

function add_category_if_not_exists($category){
   global $wpdb;
    $table_categories = $wpdb->prefix . 'cclist2a_categories'; // Changed prefix
    if( !$wpdb->get_row("SELECT * FROM $table_categories WHERE category_name = '" . $category . "'") ){
      $wpdb->insert($table_categories, array('category_name' => $category));
      error_log("adding category" . $category);
    }
}

/**
 * Import products from JSON data
 */
function cclist_import_products($json_data) {
     error_log("cclist_import_products: Received JSON data: " . $json_data);
    $products = json_decode($json_data, true);
    error_log("cclist_import_products: decoded JSON data: " . print_r($products,true));

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("cclist_import_products: JSON decode error: " . json_last_error_msg());
        return new WP_Error('invalid_json', 'Invalid JSON data provided');
    }

    if (!is_array($products)) {
        error_log("cclist_import_products: Decoded data is not an array.");
        return false;
    }

    // Add categories if they don't already exist.
    if(isset($products['available_categories']) && is_array($products['available_categories'])){
      foreach($products['available_categories'] as $category){
        add_category_if_not_exists($category);
      }
    }

   $success_count = 0;
   foreach ($products as $product) {
      add_category_if_not_exists($product['category']);
       if(empty($product['category']) && empty($product['item'])){
         error_log("skipping empty looking product");
         continue;
       }
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
    $table_products = $wpdb->prefix . 'cclist2a_products'; // Changed prefix

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

/**
 * Empty the products table.
 */
function cclist_empty_products_table() {
    global $wpdb;
    $table_products = $wpdb->prefix . 'cclist2a_products'; // Changed prefix
    return $wpdb->query("TRUNCATE TABLE $table_products");
}

/**
 * Empty the categories table
 */

 function cclist_empty_categories_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'cclist2a_categories';  // Changed prefix
    return $wpdb->query("TRUNCATE TABLE $table_name");
<<<<<<< HEAD
<<<<<<< HEAD
 }

/**
 * Import products from a CSV file.
 *
 * @param string $csv_data The CSV data as a string.
 *
 * @return array|WP_Error An array with 'success', 'imported' (count), and 'total' keys, or a WP_Error on failure.
 */
function cclist_import_csv($csv_data) {
    error_log("cclist_import_csv called. Data: " . $csv_data);

    $lines = explode(PHP_EOL, trim($csv_data)); // Split into lines, trim whitespace
    if (empty($lines)) {
        error_log("cclist_import_csv: Empty CSV data.");
        return new WP_Error('empty_csv', 'No CSV data provided.');
    }

    // Get the header row
    $header = str_getcsv(array_shift($lines)); // Remove and get the first line (header)
    error_log("CSV Header: " . print_r($header, true));

    // Check if the header row has the required columns
    $required_columns = array('category', 'item', 'size', 'price', 'quantity_min', 'quantity_max');
    $missing_columns = array_diff($required_columns, $header);
    if (!empty($missing_columns)) {
        error_log("cclist_import_csv: Missing required columns: " . implode(', ', $missing_columns));
        return new WP_Error('invalid_csv', 'CSV data is missing required columns: ' . implode(', ', $missing_columns));
    }
    // If 'discount' is not present, we'll add it later with a default value.


    $products = array();
    foreach ($lines as $line) {
        $row = str_getcsv($line);
        error_log("CSV Row: " . print_r($row, true));

        // Combine the header and row to create an associative array
        if (count($header) !== count($row)) {
            error_log("cclist_import_csv: Mismatch between header and row length. Padding row with empty values.");
            while (count($row) < count($header)) {
                $row[] = ''; // Pad with empty values
            }
        }
        $product_data = array_combine($header, $row);
         if ($product_data === false) {
            error_log("cclist_import_csv: array_combine failed. Skipping row.");
            continue; // Skip rows that don't match the header length
        }

        // Add 'discount' if it doesn't exist
        if (!isset($product_data['discount'])) {
            $product_data['discount'] = null; // Default to null
        }

      // Make sure price is correctly cast
      if(isset($product_data['price'])){
        $product_data['price'] = (float) $product_data['price'];
      }

        $products[] = $product_data; // Add to the products array
    }

    error_log("cclist_import_csv: Processed products: " . print_r($products, true));

    $success_count = 0;
    foreach ($products as $product) {
        // Add category
        add_category_if_not_exists($product['category']);

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
=======
 }
>>>>>>> parent of 6e34678 (55)
=======
 }
>>>>>>> parent of 6e34678 (55)
