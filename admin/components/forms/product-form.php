<?php
if (!defined('ABSPATH')) {
    exit;
}

$product = null;
$is_edit = false;
$variations = array();

if (isset($_GET['id'])) {
    $product = cclist_get_product(intval($_GET['id']));
    $is_edit = true;
    // If editing, fetch all variations for this item
    if ($product) {
        global $wpdb;
        $table_products = $wpdb->prefix . 'cclist_products';
        $variations = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_products WHERE item = %s ORDER BY size ASC, quantity_min ASC", $product['item']),
            ARRAY_A
        );
    }
}

$categories = cclist_get_categories();
?>

<div class="wrap cclist-admin">
    <h1><?php echo $is_edit ? 'Edit Product' : 'Add New Product'; ?></h1>
    
    <form id="product-form" method="post" action="">
        <?php wp_nonce_field('cclist_product_nonce'); ?>
        <input type="hidden" name="action" value="cclist_save_product">
        <?php if ($is_edit): ?>
            <input type="hidden" name="item_name" value="<?php echo esc_attr($product['item']); ?>">
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
        </table>
        
        <h2>Variations</h2>
        <p>Add one or more variations for this product. Each variation can have a different size, price, and quantity range.</p>

        <table class="widefat fixed striped table-view-list" id="variations-table">
          <thead>
            <tr>
              <th>Size</th>
              <th>Price</th>
              <th>Quantity Min</th>
              <th>Quantity Max</th>
              <th>Discount</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($variations)) :
                foreach ($variations as $index => $variation) : ?>
                <tr class="variation-row">
                    <input type="hidden" name="variations[<?php echo $index; ?>][id]" value="<?php echo esc_attr($variation['id']); ?>">
                    <td><input type="text" name="variations[<?php echo $index; ?>][size]" value="<?php echo esc_attr($variation['size']); ?>" placeholder="Size"></td>
                    <td><input type="number" step="0.01" name="variations[<?php echo $index; ?>][price]" value="<?php echo esc_attr($variation['price']); ?>" placeholder="Price"></td>
                    <td><input type="number" name="variations[<?php echo $index; ?>][quantity_min]" value="<?php echo esc_attr($variation['quantity_min']); ?>" placeholder="Min Quantity"></td>
                    <td><input type="number" name="variations[<?php echo $index; ?>][quantity_max]" value="<?php echo esc_attr($variation['quantity_max']); ?>" placeholder="Max Quantity"></td>
                    <td><input type="number" step="0.01" name="variations[<?php echo $index; ?>][discount]" value="<?php echo esc_attr($variation['discount']); ?>" placeholder="Discount"></td>
                    <td><button type="button" class="button remove-variation">Remove</button></td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="6">
                <button type="button" class="button" id="add-variation">Add Variation</button>
              </td>
            </tr>
          </tfoot>
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

    // Add variation row
    $('#add-variation').on('click', function() {
        const index = $('#variations-table tbody tr').length;
        const row = `
            <tr class="variation-row">
                <input type="hidden" name="variations[${index}][id]" value="">
                <td><input type="text" name="variations[${index}][size]" placeholder="Size"></td>
                <td><input type="number" step="0.01" name="variations[${index}][price]" placeholder="Price"></td>
                <td><input type="number" name="variations[${index}][quantity_min]" placeholder="Min Quantity"></td>
                <td><input type="number" name="variations[${index}][quantity_max]" placeholder="Max Quantity"></td>
                <td><input type="number" step="0.01" name="variations[${index}][discount]" placeholder="Discount"></td>

                <td><button type="button" class="button remove-variation">Remove</button></td>
            </tr>
        `;
        $('#variations-table tbody').append(row);
    });

    // Remove variation row
    $(document).on('click', '.remove-variation', function() {
        $(this).closest('tr').remove();
    });

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

        const formData = $(this).serializeArray();
        console.log('Form Data:', formData);
        $.post(ajaxurl, formData, function(response) {
          if(response.success){
            window.location.href = "<?php echo admin_url('admin.php?page=cclist-admin');?>"
          } else {
            alert('Error saving product');
          }
        })
    });
});
</script>