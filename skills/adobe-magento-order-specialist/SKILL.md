---
name: adobe-magento-order-specialist
description: "Native Magento 2.4.8 / Adobe Commerce quote-to-order model: cart totals and quote items, quote fields reaching the order, order events in dispatch order, states versus statuses, invoice/shipment/credit memo services, payment data, increment ids, order grid and archive, async grid and email, B2B negotiable quote and purchase order. Use when code touches carts, checkout, orders or their documents, or when inspecting order data. Products and prices: adobe-magento-catalog-specialist."
---

# Magento quote and order

Native Magento behavior from cart to refund. Product, price and stock rules: `adobe-magento-catalog-specialist`. What the project decided on top (credit, approval, ERP flows): `bunge-domain-lookup`.

## Rules: quote

- **Cart totals come from collectors** (`etc/sales.xml`) on `collectTotals()`; never set `grand_total` by hand.
- **Configurable and bundle lines exist twice** in quote and order: parent plus child rows (`parent_item_id`). Use `getAllVisibleItems()` for what the customer sees; `getAllItems()` double counts.
- **Quote data reaches the order with no observer**: the same column on the quote and order tables plus `etc/fieldset.xml` (`sales_convert_quote`, aspect `to_order`). Example: `magento-example checkout-field`.

## Rules: placing the order

- **Order events, in dispatch order**:
  - `checkout_submit_before` (quote only): block or validate.
  - `sales_model_service_quote_submit_before` (order built, not saved): adjust order data.
  - `sales_order_place_before` / `sales_order_place_after`: inside `Order::place()`, before the save, so `entity_id` doesn't exist yet. Never write rows that reference the order id here.
  - `sales_model_service_quote_submit_success` (order saved).
  - `checkout_submit_all_after` (placement finished): side effects.
  - Later: `sales_order_save_after` (every save; compare `getOrigData('status')`), `sales_order_invoice_pay`, `sales_order_invoice_register`, `sales_order_shipment_save_after`, `sales_order_creditmemo_refund`, `order_cancel_after`.
- **Observers in the placement path never throw and never call external services**: an exception breaks the customer's order. Publish to a queue instead (`adobe-create-queue`, `magento-example observer`).
- **Increment ids come from `sequence_order_<store_id>`** (`Magento_SalesSequence`), per store. Gaps are normal (a failed placement consumes a value); never generate or reuse ids yourself, and match orders to external systems by `increment_id` plus store, not `entity_id`.
- **Payment data lives in `sales_order_payment`**: gateway fields go in `additional_information` through `$payment->setAdditionalInformation()`, never card numbers or CVV. Read them with `getAdditionalInformation($key)`.

## Rules: after placement

- **State is the fixed lifecycle; status is a label assigned to a state.** States: `new`, `pending_payment`, `processing`, `complete`, `closed`, `canceled`, `holded`, `payment_review`. Branch logic on state. Add a status with a data patch using `Order\Status::assignState($state, $isDefault, $visibleOnFront)`.
- **Documents move the state.** Use `InvoiceOrderInterface`, `ShipOrderInterface`, `RefundOrderInterface` (offline), `RefundInvoiceInterface` (online) and `OrderManagementInterface::cancel/hold/unHold`. Check `canInvoice()`, `canShip()`, `canCreditmemo()`, `canCancel()` first. A bare `setState()->save()` leaves `qty_invoiced/shipped/refunded` and totals wrong and gives no salable quantity back.
- **History comments** through `$order->addCommentToStatusHistory($comment, $status, $visibleOnFront)` or `OrderManagementInterface::addComment()`, so the Admin and the customer see why a status changed.
- **Read orders with `OrderRepositoryInterface::getList()`** and a `SearchCriteria` (filter by `increment_id`, `status`, `created_at`, page size set); never load a full order collection.
- **The admin order grid reads `sales_order_grid`**: a new `sales_order` column also needs a `sales_order_grid` column and an entry in the `columns` argument of the `Magento\Sales\Model\ResourceModel\Order\Grid` virtual type.
- **Async options move work off placement**: `dev/grid/async_indexing` = 1 fills the grids by cron (`sales_grid_order_async_insert`), and `sales_email/general/async_sending` = 1 sends order emails by cron (`sales_send_order_emails`). With either on, a missing grid row or email right after placement is a stopped cron, not a bug.
- **Order archive (Commerce)**: with `sales/magento_salesarchive/active`, old orders move to `magento_sales_order_grid_archive`; grid queries and reports must include the archive table.

## Rules: B2B

- **A negotiable quote is a regular `quote` row plus a `negotiable_quote` row** (extension attribute `negotiable_quote`), with its own statuses (`created`, `submitted_by_customer`, `processing_by_admin`, `submitted_by_admin`, `ordered`, `expired`, `declined`, `closed`). Price or total customizations also hit them: decide explicitly.
- **A purchase order** (`purchaseorder_enabled`) holds the cart until approval (`pending`, `approval_required`, `approved`, `order_placed`, `rejected`, ...); the order is created later by the `purchaseorder.toorder` consumer, so placement observers run without the customer's session.

## Verify

- `php "$T/magento-api.php" 'Magento\Sales\Api\InvoiceOrderInterface'` (real signatures; `$T` is the `scripts/` folder of `adobe-magento-toolbox`) and `php "$T/magento-verify.php"` before done.
- `bin/magento config:show dev/grid/async_indexing`; `bin/magento config:show sales_email/general/async_sending`.
- `bin/magento queue:consumers:list | grep purchaseorder`.
- Read-only SQL: `SELECT state, status FROM sales_order WHERE increment_id = '<id>'`; `SELECT status, comment, created_at FROM sales_order_status_history WHERE parent_id = <entity_id>`.

## Pitfalls

- Status that never shows in the Admin -> not assigned to a state in `sales_order_status_state`.
- Order missing from the admin grid -> async grid indexing with cron stopped, or the order is archived.
- Totals double counted in a custom report -> summed `getAllItems()` including child rows.
- Purchase order approved but no order -> `purchaseorder.toorder` consumer not running.

## Docs

- https://experienceleague.adobe.com/en/docs/commerce-admin/stores-sales/order-management/orders/order-status
- https://experienceleague.adobe.com/en/docs/commerce-admin/stores-sales/order-management/orders/order-archive
- https://developer.adobe.com/commerce/php/development/components/add-custom-field-to-checkout/
- https://experienceleague.adobe.com/en/docs/commerce-admin/b2b/quotes/quotes
- https://experienceleague.adobe.com/en/docs/commerce-admin/b2b/purchase-orders/account-dashboard-my-purchase-orders
