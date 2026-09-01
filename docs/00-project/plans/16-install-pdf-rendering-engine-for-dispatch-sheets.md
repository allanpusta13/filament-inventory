# Install PDF Rendering Engine for Dispatch Sheets

## Problem
The application requires a PDF rendering engine to generate dispatch sheets (STNs) for transfer requisitions. Currently, the code references Barryvdh\DomPDF facade but the package is not installed.

## Solution
Install the `barryvdh/laravel-dompdf` package and verify PDF generation works for dispatch sheets.

## Implementation Steps
1. Install the dompdf package via Composer:
   ```bash
   composer require barryvdh/laravel-dompdf
   ```
2. Publish the configuration (optional but recommended for customization):
   ```bash
   php artisan vendor:publish --provider="Barryvdh\LaravelDomPDF\ServiceProvider"
   ```
3. Verify the PDF generation code in `app/Filament/Resources/TransferRequisitions/Pages/ViewTransferRequisition.php` uses the correct facade and view.
4. Ensure the route to generate the PDF is accessible and returns a PDF response.
5. Write a feature test to verify PDF generation works.
6. Write a browser test to verify the PDF download button works in the UI.

## Test Commands
- Backend: `php artisan test`
- Browser: `npx playwright test`

## Acceptance Criteria
- [ ] Dompdf package is installed and autoloaded.
- [ ] PDF generation endpoint returns a valid PDF file.
- [ ] Browser test confirms the download button triggers PDF download.
- [ ] All existing tests continue to pass.