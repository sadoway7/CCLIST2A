<?php
if (!defined('ABSPATH')) {
    exit;
}

$product = null;
$is_edit = false;

if (isset($_GET['id'])) {
    $product = cclist_get_product(intval($_GET['id']));
    $is_edit = true;
}

$categories = cclist_get_categories();
?>

<div class="wrap cclist-admin">
    <h1><?php echo $is_edit ? 'Edit Product' : 'Add New Product'; ?></h1>
    
    <form id="product-form" method="post" action="">
        <?php wp_nonce_field('cclist_product_nonce'); ?>
        <input type="hidden" name="action" value="cclist_save_product">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($product['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="category">Category</label>
                </th>
                <td>
                    <select name="category" id="category" required>
                        <option value="">Select Category</option>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat); ?>" 
                                    <?php selected($product ? $product['category'] : '', $cat); ?>>
                                    <?php echo esc_html($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <option value="new">+ Add New Category</option>
                    </select>
                    <div id="new-category-field" style="display: none; margin-top: 10px;">
                        <input type="text" id="new-category" placeholder="Enter new category name">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="item">Item Name</label>
                </th>
                <td>
                    <input type="text" name="item" id="item" class="regular-text" 
                           value="<?php echo esc_attr($product ? $product['item'] : ''); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="variation_type">Variation Type</label>
                </th>
                <td>
                    <select name="variation_type" id="variation_type">
                        <option value="quantity">Quantity-based Price Breaks</option>
                        <option value="size">Size Variations</option>
                    </select>
                </td>
            </tr>
            <tr class="size-field">
                <th scope="row">
                    <label for="size">Size/Weight</label>
                </th>
                <td>
                    <input type="text" name="size" id="size" class="regular-text"
                           value="<?php echo esc_attr($product ? $product['size'] : ''); ?>">
                    <p class="description">Example: "20kg", "500g", "2.5kg", etc.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="price">Price</label>
                </th>
                <td>
                    <input type="number" name="price" id="price" class="regular-text" step="0.01" min="0"
                           value="<?php echo esc_attr($product ? $product['price'] : ''); ?>" required>
                </td>
            </tr>
            <tr class="quantity-fields">
                <th scope="row">
                    <label for="quantity_min">Minimum Quantity</label>
                </th>
                <td>
                    <input type="number" name="quantity_min" id="quantity_min" class="regular-text" min="1"
                           value="<?php echo esc_attr($product ? $product['quantity_min'] : '1'); ?>">
                </td>
            </tr>
            <tr class="quantity-fields">
                <th scope="row">
                    <label for="quantity_max">Maximum Quantity</label>
                </th>
                <td>
                    <input type="number" name="quantity_max" id="quantity_max" class="regular-text" min="1"
                           value="<?php echo esc_attr($product ? $product['quantity_max'] : ''); ?>">
                    <p class="description">Leave empty for no upper limit</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="discount">Discount</label>
                </th>
                <td>
                    <input type="number" name="discount" id="discount" class="regular-text" step="0.01" min="0" max="1"
                           value="<?php echo esc_attr($product ? $product['discount'] : ''); ?>">
                    <p class="description">Enter as decimal (e.g., 0.2 for 20% discount)</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo $is_edit ? 'Update Product' : 'Add Product'; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=cclist-admin'); ?>" class="button">Cancel</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle new category field
    $('#category').on('change', function() {
        if ($(this).val() === 'new') {
            $('#new-category-field').show();
        } else {
            $('#new-category-field').hide();
        }
    });

    // Handle variation type changes
    $('#variation_type').on('change', function() {
        if ($(this).val() === 'size') {
            $('.quantity-fields').hide();
            $('.size-field').show();
        } else {
            $('.quantity-fields').show();
            $('.size-field').show();
        }
    }).trigger('change');

    // Form submission
    $('#product-form').on('submit', function(e) {
        e.preventDefault();
        
        // If new category is selected, use the new category value
        if ($('#category').val() === 'new') {
            const newCategory = $('#new-category').val();
            if (!newCategory) {
                alert('Please enter a category name');
                return;
            }
            $('#category').append($('<option>', {
                value: newCategory,
                text: newCategory
            })).val(newCategory);
        }

        const formData = $(this).serialize();
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                window.location.href = '<?php echo admin_url('admin.php?page=cclist-admin'); ?>';
            } else {
                alert(response.data.message || 'Error saving product');
            }
        });
    });
});
</script>