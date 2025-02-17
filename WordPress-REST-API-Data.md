# WordPress REST API Data Structure for cclist App

This document outlines the required data structure for the WordPress REST API endpoint that provides product data to the cclist application. The API should return a JSON array of product objects, where each object conforms to the following structure:

## Product Object Structure

Each object in the array represents a product and should have the following properties:

| Property       | Type             | Description                                                                 | Required |
| -------------- | ---------------- | --------------------------------------------------------------------------- | -------- |
| `category`     | string           | The category of the product.                                                | Yes      |
| `item`         | string           | The name of the product item.                                               | Yes      |
| `size`         | string or null   | The size or weight of the product. Can be `null` if size is not applicable. | Yes      |
| `price`        | number           | The base price of the product for the given size and quantity range.        | Yes      |
| `quantity_min` | number (optional) | The minimum quantity for which this price is applicable. Defaults to 1 if not provided. | No       |
| `quantity_max` | number or null (optional) | The maximum quantity for which this price is applicable. Can be `null` for no limit. | No       |
| `discount`     | number (optional) | A discount applicable for this product entry, represented as a decimal (e.g., 0.1 for 10%). | No       |
| `prices`       | object (optional) | An object containing prices for different sizes of the same item. This is used when prices vary by size. The keys of this object are size strings, and the values are the corresponding prices. | No       |

### Example Product Object

```json
{
  "category": "Fruits",
  "item": "Apple",
  "size": "1kg",
  "price": 2.50,
  "quantity_min": 1,
  "quantity_max": 10,
  "discount": 0.05
}
```

### Example Product Object with Size-Based Pricing

```json
{
  "category": "Beverages",
  "item": "Juice",
  "size": null,
  "price": 5.00,
  "prices": {
    "small": 2.50,
    "medium": 5.00,
    "large": 7.00
  }
}
```

## Data Format

The WordPress REST API should return data in **JSON format**.

**Important notes on data format:**

*   **Prices as Numbers**: Ensure that all price values (both in the `price` property and within the `prices` object) are provided as **numbers**, not strings. The cclist application uses `parseFloat` to process these values, so using number format is crucial to avoid parsing issues.

## More about the `prices` object

The optional `prices` object is used to handle products where the price varies depending on the size. For example, a beverage might have different prices for "small", "medium", and "large" sizes.

If a product has size-based pricing, the `prices` object should be included in the product object. The keys of this object should be strings representing the sizes (e.g., "small", "medium", "large", "100g", "2kg"), and the values should be the corresponding prices as numbers.

When the `prices` object is used, the main `price` property of the product object might represent a default price or the price for a base size, or it could be less relevant as the actual price is determined by the `prices` object based on the selected size. In the example above for "Juice", the base `price` of 5.00 could be for a "medium" size, while the `prices` object specifies prices for "small", "medium", and "large".

## API Endpoint

The cclist application expects to fetch product data from the following WordPress REST API endpoint:

`/wp-json/cclist/v1/products`

This endpoint should return a JSON array of product objects as described above.

## Error Handling

The WordPress REST API should implement proper error handling. In case of errors, the API should return appropriate HTTP status codes and informative error messages in JSON format.

For example:

*   **400 Bad Request**: If the request is malformed or missing required parameters.
*   **500 Internal Server Error**: If there is an issue on the server side while fetching or processing data.

Error responses should include a JSON body with details about the error, which can be helpful for debugging. For example:

```json
{
  "error": "Invalid request parameter",
  "details": "The 'category' parameter is missing."
}
```

## Data Handling in cclist App

The `src/dataHandler.tsx` file in the cclist application is responsible for fetching and processing data from this API endpoint. It defines the `Product` interface and uses the `getAllProducts` function to retrieve data. Ensure that the WordPress REST API response is compatible with this structure for seamless integration.

By adhering to this data structure and the guidelines outlined in this document, the WordPress REST API will successfully provide the necessary product data to the cclist application.