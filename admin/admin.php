<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get grouped products
$grouped_products = cclist_get_products_grouped(); // Ensure we fetch fresh data
$categories = cclist_get_categories();

?><div class="wrap cclist-admin">
    <h1 class="wp-heading-inline">CCList Products</h1>
    <a href="<?php echo admin_url('admin.php?page=cclist-admin-new'); ?>" class="page-title-action">Add New Product</a>
    
    <input type="file" id="import-products" accept=".json" style="display: none;">
    <label for="import-products" class="button button-secondary">Import Products</label>
    <button type="button" class="button button-secondary" id="empty-products">Empty Products Table</button>
    <button type="button" class="button button-secondary" id="empty-categories">Empty Categories Table</button>



    <div class="cclist-filters">
        <select id="category-filter">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo esc_attr($category); ?>">
                    <?php echo esc_html($category); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <input type="text" id="search-filter" placeholder="Search items...">
        
        <button type="button" class="button" id="expand-all">Expand All</button>
        <button type="button" class="button" id="collapse-all">Collapse All</button>
    </div>

    <div class="cclist-products-wrapper">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-toggle"></th>
                    <th class="column-category">Category</th>
                    <th class="column-item">Item</th>
                    <th class="column-variations">Variations</th>
                    <th class="column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grouped_products)): ?>
                    <tr>
                        <td colspan="5">No products found. <a href="<?php echo admin_url('admin.php?page=cclist-admin-new'); ?>">Add your first product</a></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($grouped_products as $group): ?>
                        <tr class="group-row" data-category="<?php echo esc_attr($group['category']); ?>" data-item="<?php echo esc_attr($group['item']); ?>">
                            <td class="column-toggle">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </td>
                            <td class="column-category"><?php echo esc_html($group['category']); ?></td>
                            <td class="column-item"><?php echo esc_html($group['item']); ?></td>
                            <td class="column-variations"><?php echo count($group['variations']); ?> variations</td>
                            <td class="column-actions">
                                <button type="button" class="button-link edit-group" data-item="<?php echo esc_attr($group['item']); ?>">Edit Group</button>
                                |
                                <button type="button" class="button-link duplicate-group" data-item="<?php echo esc_attr($group['item']); ?>">Duplicate Group</button>
                                |
                                <button type="button" class="button-link delete-group" data-item="<?php echo esc_attr($group['item']); ?>">Delete Group</button>
                            </td>
                        </tr>
                        <tr class="variations-row hidden">
                            <td colspan="5">
                                <table class="variations-table">
                                    <thead>
                                        <tr>
                                            <th>Size</th>
                                            <th>Price</th>
                                            <th>Quantity Range</th>
                                            <th>Discount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['variations'] as $product): ?>
                                            <tr data-id="<?php echo esc_attr($product['id']); ?>">
                                                <td><?php echo esc_html($product['size'] ?? 'N/A'); ?></td>
                                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $qty_range = $product['quantity_min'];
                                                    if ($product['quantity_max']) {
                                                        $qty_range .= ' - ' . $product['quantity_max'];
                                                    } else {
                                                        $qty_range .= '+';
                                                    }
                                                    echo esc_html($qty_range);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (!empty($product['discount'])) {
                                                        echo esc_html(number_format($product['discount'] * 100, 0) . '%');
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="button-link edit-product" 
                                                            data-id="<?php echo esc_attr($product['id']); ?>">Edit</button>
                                                    |
                                                    <button type="button" class="button-link delete-product" 
                                                            data-id="<?php echo esc_attr($product['id']); ?>">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="edit-product-modal" class="cclist-modal" style="display: none;">
    <div class="cclist-modal-content">
        <span class="cclist-modal-close">&times;</span>
        <div id="edit-product-form-container">
            <!-- Form will be loaded here via AJAX -->
        </div>
    </div>
</div>