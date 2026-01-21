# Database Documentation

Complete documentation of the application's data model, organized by domain.

## Domains

### [Catalog](./catalog.md)

-   **Product**: Core product entity with pricing, stock management, and visibility
-   **Category**: Product categorization with slug-based routing

### [Cart](./cart.md)

-   **Cart**: Shopping cart persistence and lifecycle
-   **CartItem**: Individual items in the cart with quantity management

### [Checkout](./checkout.md)

-   **CheckoutService**: Cart-to-order conversion and order creation workflow
-   **StripePaymentService**: Payment processing and Stripe integration
-   **ShippingMethod**: Shipping options and cost calculation

### [Orders](./orders.md)

-   **Order**: Customer orders with complete workflow and reference system
-   **OrderItem**: Order line items with historical price tracking

### [Users](./users.md)

-   **Address**: Shipping addresses with default address management
-   **User**: User accounts and authentication

## Key Principles

-   **Price Precision**: Always use `BCMath` for monetary calculations, never floats
-   **Historical Data**: Prices and quantities are frozen in OrderItem for audit trails
-   **Soft Deletes**: Use `isActive` flags instead of hard deletes to preserve order history
-   **Cascade Rules**: Deletions cascade from User → Cart, User → Addresses

## Related Documentation

-   [Entity Relationships](../entities.md)
-   [Testing](../testing.md)
-   [Docker Setup](../docker.md)
