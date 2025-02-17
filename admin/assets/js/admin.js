console.log("admin.js loaded");
jQuery(document).ready(function($) {
    console.log('ajaxurl:', cclistAdmin.ajaxUrl);

    // Group expansion/collapse
    function toggleGroup($row) {
        const $icon = $row.find('.dashicons');
        const $variationsRow = $row.next('.variations-row');

        $row.toggleClass('expanded');
        $variationsRow.toggleClass('hidden');

        if ($row.hasClass('expanded')) {
            $icon.css('transform', 'rotate(90deg)');
        } else {
            $icon.css('transform', 'rotate(0deg)');
        }
    }

    $('.group-row').on('click', function(e) {
        if (!$(e.target).hasClass('button-link')) {
            toggleGroup($(this));
        }
    });

    // Expand/Collapse All
    $('#expand-all').on('click', function() {
        $('.group-row').each(function() {
            if (!$(this).hasClass('expanded')) {
                toggleGroup($(this));
            }
        });
    });

    $('#collapse-all').on('click', function() {
        $('.group-row').each(function() {
            if ($(this).hasClass('expanded')) {
                toggleGroup($(this));
            }
        });
    });

    // Filtering
    function filterProducts() {
        const category = $('#category-filter').val().toLowerCase();
        const search = $('#search-filter').val().toLowerCase();

        $('.group-row').each(function() {
            const $row = $(this);
            const rowCategory = $row.data('category').toLowerCase();
            const rowItem = $row.data('item').toLowerCase();

            const categoryMatch = !category || rowCategory === category;
            const searchMatch = !search || rowItem.includes(search);

            if (categoryMatch && searchMatch) {
                $row.show();
                $row.next('.variations-row').show();
            } else {
                $row.hide();
                $row.next('.variations-row').hide();
            }
        });
    }

    $('#category-filter, #search-filter').on('change keyup', filterProducts);

    // Modal handling
    const $modal = $('#edit-product-modal');
    const $modalContent = $('.cclist-modal-content');

    function openModal() {
        $modal.fadeIn(200);
    }

    function closeModal() {
        $modal.fadeOut(200);
    }

    $('.cclist-modal-close').on('click', closeModal);

    $(window).on('click', function(e) {
        if ($(e.target).is($modal)) {
            closeModal();
        }
    });

    // Edit product
    $('.edit-product').on('click', function(e) {
        e.preventDefault();
        const productId = $(this).data('id');

        // Load form via AJAX
        $.get(cclistAdmin.ajaxUrl, {
            action: 'cclist_get_product_form',
            id: productId,
            nonce: cclistAdmin.nonce
        }, function(response) {
            if (response.success) {
                $('#edit-product-form-container').html(response.data.form);
                openModal();
            } else {
                showMessage(response.data.message, 'error');
            }
        });
    });

    // Delete product
    $('.delete-product').on('click', function(e) {
        e.preventDefault();
        const productId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this product?')) {
            return;
        }

        $.post(cclistAdmin.ajaxUrl, {
            action: 'cclist_delete_product',
            id: productId,
            nonce: cclistAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                showMessage(response.data.message, 'error');
            }
        });
    });

    // Delete group
    $('.delete-group').on('click', function(e) {
        e.preventDefault();
        const item = $(this).data('item');

        if (!confirm('Are you sure you want to delete this entire group and all its variations?')) {
            return;
        }

        $.post(cclistAdmin.ajaxUrl, {
            action: 'cclist_delete_group',
            item: item,
            nonce: cclistAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                showMessage(response.data.message, 'error');
            }
        });
    });

    // Duplicate Group
    $('.duplicate-group').on('click', function(e) {
        e.preventDefault();
        const item = $(this).data('item');
        if (!confirm('Are you sure you want to duplicate this group?')) {
            return;
        }

        $.post(cclistAdmin.ajaxUrl, {
          action: 'cclist_duplicate_group',
          item: item,
          nonce: cclistAdmin.nonce
        }, function(response) {
          if (response.success) {
              location.reload();
          } else {
              showMessage(response.data.message, 'error');
          }
        });
    });

    // Edit group
    $('.edit-group').on('click', function(e) {
        e.preventDefault();
        const item = $(this).data('item');

        // Redirect to new product form with group data
        window.location.href = `${cclistAdmin.adminUrl}?page=cclist-admin-new&group=${item}`;
    });

    // Message display
    function showMessage(message, type = 'success') {
        const $message = $(`<div class="cclist-message ${type}">${message}</div>`);
        $('.cclist-admin').prepend($message);

        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Loading state
    function setLoading($element) {
        $element.addClass('cclist-loading');
    }

    function removeLoading($element) {
        $element.removeClass('cclist-loading');
    }

    // Form handling
    $(document).on('submit', '#product-form', function(e) {
        e.preventDefault();
        console.log('Form submit handler attached');
        const $form = $(this);
        setLoading($form);

        $.post(cclistAdmin.ajaxUrl, $form.serialize(), function(response) {
            removeLoading($form);

            if (response.success) {
                if ($modal.is(':visible')) {
                    closeModal();
                }
                location.reload();
            } else {
                showMessage(response.data.message, 'error');
            }
        });
    });

   // Import Products
    $('#import-products').on('click', function(e) {
        e.preventDefault(); // Prevent default button behavior
        const jsonData = $('#import-json').val();

        if (!jsonData) {
            showMessage('Please paste JSON data into the textarea.', 'error');
            return;
        }

        try {
            const data = JSON.parse(jsonData);
            console.log("data to be imported", data);
            $.post(cclistAdmin.ajaxUrl, {
                action: 'cclist_import_products',
                data: JSON.stringify(data), // Send stringified JSON
                nonce: cclistAdmin.nonce
            }, function(response) {
                if (response.success) {
                    showMessage(`Successfully imported ${response.data.imported} products`);
                    location.reload();
                } else {
                      console.log(response);
                        showMessage(response.data.message, 'error');
                    }
                });
            } catch (error) {
                showMessage('Invalid JSON file', 'error');
            }
        });

    // Empty Products Table
    $('#empty-products').on('click', function() {
        if (!confirm('Are you sure you want to empty the products table? This cannot be undone.')) {
            return;
        }

        $.post(cclistAdmin.ajaxUrl, {
            action: 'cclist_empty_products_table',
            nonce: cclistAdmin.nonce
        }, function(response) {
            if (response.success) {
                showMessage('Products table emptied successfully.');
                location.reload(); // Refresh the page
            } else {
                showMessage('Error emptying products table.', 'error');
            }
        });
    });

      // Empty Categories Table
      $('#empty-categories').on('click', function() {
        if (!confirm('Are you sure you want to empty the categories table? This cannot be undone.')) {
            return;
        }

        $.post(cclistAdmin.ajaxUrl, {
            action: 'cclist_empty_categories_table',
            nonce: cclistAdmin.nonce
        }, function(response) {
            if (response.success) {
                showMessage('Categories table emptied successfully.');
                location.reload(); // Refresh the page
            } else {
                showMessage('Error emptying categories table.', 'error');
            }
        });
    });
});