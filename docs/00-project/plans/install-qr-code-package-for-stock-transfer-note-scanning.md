# Install QR code package for Stock Transfer Note scanning

## Problem
The application uses SimpleSoftwareIO/simple-qrcode Facade for generating QR codes in Stock Transfer Note (STN) PDFs and web routes, but the package is not installed in composer.json require section.

## Solution
Install the `simplesoftwareio/simple-qrcode` package via Composer.

## Steps
1. Run `composer require simplesoftwareio/simple-qrcode`
2. Verify the package is added to `composer.json` require section.
3. Ensure the existing code using `SimpleSoftwareIO\QrCode\Facade\QrCode;` continues to work.
4. Run the full test suite to confirm no regressions.
5. Run browser tests (if any) to ensure UI rendering of QR codes works.
6. Run Pint to ensure code style compliance.
7. Commit changes.

## Acceptance Criteria
- `composer.json` includes `"simplesoftwareio/simple-qrcode": "^5.0"` (or latest compatible version) in require.
- `php artisan test` passes.
- `npx playwright test` passes (if applicable).
- No new Pint errors.
- QR code generation in TransferRequisition PDF and scan route works manually verified.