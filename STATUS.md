# Status
## Done
- Ratio-field convergence: all five `*_unit_ratio` fields (PurchaseOrder, SalesOrder, TransferRequisition `requested_`/`proposed_`, DirectTransfer) now use the icon-with-tooltip pattern; `->hintIcon()` removed. `PurchaseOrderForm.php` parse fatal already cleared. New `FormSyntaxTest` (parse guard + no-`hintIcon` guard) passing; Pint clean.
- Blueprint amended: §7B.1/§7C.1/§7G.1/§7H.1 samples, §7O.8 #12 rule, §12 test list, §14 invariants, §27 change-row #11.
## In progress
## Blocked
- Pre-existing, unrelated: 21 `RouteWiringTest` failures — `routes/web.php` imports `App\Http\Controllers\STNManifestController` which does not exist (`app/Http/Controllers/` absent). Out of scope for this task.
## Next
- Owner decision: remove orphaned `TransferRequisitions\Schemas\RevisionsForm.php` (zero call sites; excluded from both form guards).
