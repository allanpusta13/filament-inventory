# Plan: Use high-precision decimals (15,6) for financial fields

## Goal
Change the precision of financial decimal columns from 15,4 to 15,6 to accommodate higher precision requirements.

## Financial Fields to Update
1. product_variants.cost_price
2. product_variants.sale_price
3. product_prices.price
4. loss_ledger.unit_cost_price
5. loss_ledger.total_financial_loss

## Steps
1. Create a new migration file to alter the columns to decimal(15,6).
2. In the migration, use Schema::table to modify each column.
3. Run the migration.
4. Run the test suite to ensure compatibility.
5. If any tests fail, adjust accordingly.

## Testing
- Run `php artisan test` to ensure all tests pass.
- No browser tests required as this is not a UI feature.

## Notes
- Changing the scale from 4 to 6 will allow storing values with up to 6 decimal places.
- Existing data will be preserved; the alteration should not change existing values.