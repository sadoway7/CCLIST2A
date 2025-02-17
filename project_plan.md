# WordPress Plugin with REST API Integration Plan

## Overview
Build a WordPress plugin that:
1. Manages product data in the WordPress database
2. Provides admin UI for product management with product grouping
3. Exposes data via WordPress REST API for cclist application

## Structure
Following existing plugin structure from logic-summary.md:
```
admin/
├── admin.php              # Main admin page handler
├── components/
│   ├── forms/            # Product, filter, import forms
│   └── tables/           # Product listing table with grouping
└── assets/
    ├── js/              # Form and table handlers
    └── css/             # Admin styles

includes/
└── data-handler.php    # Database operations & REST API integration
```

## Implementation Steps

### 1. Database Integration
- Use WordPress database tables for product storage
- Implement data access functions in data-handler.php
- Handle product CRUD operations

### 2. Admin Interface (from logic-summary.md)

#### Product Table Display
- Group products by item name
- Show variations within groups:
  ```
  + Buffstone (Plainsman) - 20kg
    • 1-9: $35.65
    • 10-39: $32.50
    • 40-79: $31.65
    • 80+: $30.65

  + Cobalt Carbonate
    • 125g: $29.80
    • 250g: $57.80
    • 500g: $113.75
    • 2.5kg: $562.00
  ```
- Collapsible groups for better organization
- Sort options:
  - By category
  - By item name
  - By size
  - By price range

#### Product Management
- Add/Edit form handling both cases:
  1. Price break variations:
     - Same item/size, different quantity ranges
     - Fields for min/max quantity and corresponding prices
  2. Size variations:
     - Same item, different sizes
     - Fields for size and corresponding prices
- Bulk actions for entire product groups
- Group duplication functionality

#### Filtering and Display
- Filter by:
  - Category
  - Item name (searches within groups)
  - Size
  - Price range
  - Quantity range
- Option to expand/collapse all groups
- Show price break ranges clearly
- Highlight size variations

### 3. WordPress REST API Integration
Register endpoint using WordPress REST API infrastructure:
```php
register_rest_route('cclist/v1', '/products', [
    'methods' => 'GET',
    'callback' => 'get_products_for_api',
    'permission_callback' => '__return_true'
]);
```

Ensure API response matches required format:
```json
{
    "category": "string",
    "item": "string",
    "size": "string|null",
    "price": number,
    "quantity_min": number,
    "quantity_max": number|null,
    "discount": number,
    "prices": object (optional)
}
```

### 4. Data Handling
data-handler.php will:
- Store/retrieve data from WordPress database
- Group products by item name for admin display
- Format data for REST API responses
- Handle import/export operations
- Manage product relationships (size variations and price breaks)

## Testing
1. Admin Interface
   - Product grouping functionality
   - Price break display
   - Size variation display
   - CRUD operations within groups
   - Filtering and sorting of grouped items
   - Bulk actions on groups
   - Import/Export with grouping preserved

2. REST API
   - Endpoint returns correct JSON format
   - All variations and price breaks included
   - Error responses use proper status codes

## Success Criteria
1. Admin UI:
   - Clearly displays product groups
   - Shows price breaks and size variations effectively
   - Makes it easy to manage related products
2. WordPress REST API endpoint returns data in format specified by WordPress-REST-API-Data.md
3. Data structure matches example.md, handling both:
   - Multiple price breaks (like Buffstone example)
   - Size variations (like Cobalt Carbonate example)
4. Clean integration with WordPress database

## Next Steps
1. Review this WordPress-integrated plan with enhanced grouping
2. Switch to Code mode to implement the plugin