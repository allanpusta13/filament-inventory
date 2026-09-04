{{-- Scan-to-Receive Landing Page and Reconciliation Form --}}
{{-- Displays read-only dispatched items and allows users to input received quantities --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan to Receive - {{ $requisition->reference_code }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

    {{-- Notification Flash Messages --}}
    @if (session('notification'))
        <div class="fixed top-4 right-4 z-50 w-80" x-data="{ open: true }" x-show.open x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" @keydown.escape.window="open = false">
            <div class="rounded-lg shadow-lg p-4" :class="{
                'bg-red-100 text-red-800': {{ json_encode(session('notification')['type'] === 'danger') }},
                'bg-yellow-100 text-yellow-800': {{ json_encode(session('notification')['type'] === 'warning') }},
                'bg-green-100 text-green-800': {{ json_encode(session('notification')['type'] === 'success') }},
                'bg-blue-100 text-blue-800': {{ json_encode(session('notification')['type'] === 'info') }}
            }">
                <div class="flex">
                    <div class="flex-shrink-0">
                        {{-- Notification Icon --}}
                        @if (session('notification')['type'] === 'success')
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @elseif (session('notification')['type'] === 'warning')
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0z"/></svg>
                        @elseif (session('notification')['type'] === 'danger')
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        @else
                            <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">{{ session('notification')['body'] }}</p>
                    </div>
                    <div class="ml-auto flex-shrink-0">
                        <button class="rounded-md p-1 text-[session('notification')['type'] === 'danger' ? 'red-500' : session('notification')['type'] === 'warning' ? 'yellow-500' : session('notification')['type'] === 'success' ? 'green-500' : 'blue-500']" @click="open = false" aria-label="Close notification">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Content --}}
    <div class="flex-1 p-6">

        {{-- Header --}}
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">
                Scan to Receive: {{ $requisition->reference_code }}
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Receiving transfer from <strong>{{ $requisition->fromWarehouse->name }}</strong> to <strong>{{ $requisition->toWarehouse->name }}</strong>
            </p>
        </header>

        {{-- Status Badge --}}
        <div class="mb-6">
            <span class="px-3 py-1 text-xs font-medium rounded-full"
                  class="{{ $requisition->status === 'Dispatched' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800' }}">
                {{ ucfirst(str_replace('_', ' ', $requisition->status)) }}
            </span>
        </div>

        {{-- Instructions --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Instructions</h2>
            <p class="text-sm text-gray-600">
                Scan each item to update received quantities. Enter the number of good units and damaged units
                for each line item. The system will automatically calculate any missing units as lost/damaged.
            </p>
            @if ($requisition->status === 'PartiallyReceived')
                <p class="mt-2 text-sm text-yellow-800">
                    <strong>Note:</strong> This transfer has been partially received. Only update quantities for items not yet processed.
                </p>
            @endif
        </div>

        {{-- Received Items Form --}}
        <form action="{{ route('stn.scan.receive', $requisition) }}" method="POST" class="bg-white rounded-lg shadow-md p-6">
            @csrf

            {{-- Header Row --}}
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div class="font-medium text-gray-700">Item</div>
                <div class="font-medium text-gray-700">Expected (Base)</div>
                <div class="font-medium text-gray-700 text-center">Good Qty</div>
                <div class="font-medium text-gray-700 text-center">Damaged Qty</div>
                <div class="font-medium text-gray-700 text-center">Lost Qty (Calc)</div>
                <div class="font-medium text-gray-700">Loss Reason</div>
            </div>

            {{-- Items Repeater --}}
            <div id="items-container" class="space-y-4">
                @foreach ($requisition->items as $item)
                    <div class="border-t border-gray-200 py-4" data-item-id="{{ $item->id }}">
                        {{-- Hidden Item ID --}}
                        <input type="hidden" name="received_items[{{ $loop->index }}][item_id]" value="{{ $item->id }}">

                        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                            {{-- Item Name with Substitute Variant Alert --}}
                            <div class="flex items-center space-x-2">
                                <div class="flex-shrink-0">
                                    @if ($item->substitute_variant_id)
                                        <svg class="h-4 w-4 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0z"/></svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate" title="{{ $item->variant->sku }} - {{ $item->variant->name }}">
                                        {{ $item->variant->sku }} - {{ $item->variant->name }}
                                    </p>
                                    @if ($item->substitute_variant_id)
                                        <p class="text-xs text-yellow-600 italic">
                                            ⚠️ Substituted: {{ $item->substituteVariant->sku }} - {{ $item->substituteVariant->name }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Expected Quantity (Base UOM) --}}
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Base Units</p>
                                <p class="font-medium text-gray-900">{{ number_format($item->shipped_base_qty) }}</p>
                                <input type="hidden" name="received_items[{{ $loop->index }}][expected_qty]" value="{{ $item->shipped_base_qty }}">
                            </div>

                            {{-- Good Quantity Input --}}
                            <div class="text-center">
                                <input type="number"
                                       name="received_items[{{ $loop->index }}][good_qty]"
                                       value="{{ $item->received_good_base_qty ?? 0 }}"
                                       min="0"
                                       class="w-24 text-center border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       oninput="calculateLost(this, {{ $loop->index }})">
                            </div>

                            {{-- Damaged Quantity Input --}}
                            <div class="text-center">
                                <input type="number"
                                       name="received_items[{{ $loop->index }}][damaged_qty]"
                                       value="{{ $item->received_damaged_base_qty ?? 0 }}"
                                       min="0"
                                       class="w-24 text-center border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       oninput="calculateLost(this, {{ $loop->index }})">
                            </div>

                            {{-- Lost Quantity (Calculated) --}}
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Base Units</p>
                                <p class="font-medium text-gray-900 lost-qty-{{ $loop->index }}">
                                    {{ number_format(($item->shipped_base_qty ?? 0) - (($item->received_good_base_qty ?? 0) + ($item->received_damaged_base_qty ?? 0))) }}
                                </p>
                                <input type="hidden" name="received_items[{{ $loop->index }}][lost_qty]" class="lost-qty-hidden-{{ $loop->index }}" value="{{ ($item->shipped_base_qty ?? 0) - (($item->received_good_base_qty ?? 0) + ($item->received_damaged_base_qty ?? 0)) }}">
                            </div>

                            {{-- Loss Reason Select --}}
                            <div>
                                <select name="received_items[{{ $loop->index }}][loss_category]"
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        @change="validateLossReason(this, {{ $loop->index }})">
                                    <option value="">Select a reason...</option>
                                    <option value="Damaged in Transit" {{ old('received_items.' . $loop->index . '.loss_category') === 'Damaged in Transit' ? 'selected' : '' }}>Damaged in Transit</option>
                                    <option value="Short Shipment" {{ old('received_items.' . $loop->index . '.loss_category') === 'Short Shipment' ? 'selected' : '' }}>Short Shipment</option>
                                    <option value="Spoiled" {{ old('received_items.' . $loop->index . '.loss_category') === 'Spoiled' ? 'selected' : '' }}>Spoiled</option>
                                    <option value="Transit Variance" {{ old('received_items.' . $loop->index . '.loss_category') === 'Transit Variance' ? 'selected' : '' }}>Transit Variance</option>
                                </select>
                                @error('received_items.' . $loop->index . '.loss_category')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Submit Button --}}
            <div class="mt-8 pt-6 border-t border-gray-200">
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md transition-colors duration-200"
                        id="submitBtn">
                    Process Receiving
                </button>
            </div>
        </form>
    </div>

    <script>
        // Calculate lost quantity when good or damaged changes
        function calculateLost(input, index) {
            const goodInput = document.querySelector(`input[name="received_items[${index}][good_qty]"]`);
            const damagedInput = document.querySelector(`input[name="received_items[${index}][damaged_qty]"]`);
            const expectedQty = parseFloat(document.querySelector(`input[name="received_items[${index}][expected_qty]"]`).value);
            const goodQty = parseFloat(goodInput.value) || 0;
            const damagedQty = parseFloat(damagedInput.value) || 0;
            const lostQty = Math.max(0, expectedQty - (goodQty + damagedQty));
            
            // Update displayed lost quantity
            const lostDisplay = document.querySelector(`.lost-qty-${index}`);
            if (lostDisplay) {
                lostDisplay.textContent = lostQty.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
            }
            
            // Update hidden lost quantity
            const lostHidden = document.querySelector(`.lost-qty-hidden-${index}`);
            if (lostHidden) {
                lostHidden.value = lostQty;
            }
            
            // Validate quantities don't exceed expected
            if ((goodQty + damagedQty) > expectedQty) {
                input.value = Math.max(0, expectedQty - (input === goodInput ? damagedQty : goodQty));
                calculateLost(input, index);
                
                // Show notification
                showNotification('Invalid Entry', 'Combined good and damaged quantities cannot exceed the expected quantity.', 'warning');
            }
        }

        // Validate that loss reason is provided when there's loss/damage
        function validateLossReason(select, index) {
            const goodQty = parseFloat(document.querySelector(`input[name="received_items[${index}][good_qty]"]`).value) || 0;
            const damagedQty = parseFloat(document.querySelector(`input[name="received_items[${index}][damaged_qty]"]`).value) || 0;
            const lostQty = parseFloat(document.querySelector(`.lost-qty-hidden-${index}`).value) || 0;
            
            const hasLossOrDamage = (lostQty > 0) || (damagedQty > 0);
            
            if (hasLossOrDamage && !select.value) {
                select.focus();
                showNotification('Missing Reason', 'Please select a loss reason for items with damage or shortage.', 'warning');
            }
        }

        // Show toast notification
        function showNotification(title, body, type = 'info') {
            // Remove any existing notifications
            const existing = document.querySelector('.notification-container');
            if (existing) existing.remove();
            
            // Create notification container
            const container = document.createElement('div');
            container.className = 'notification-container fixed top-4 right-4 z-50 w-80';
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `rounded-lg shadow-lg p-4 ${type === 'danger' ? 'bg-red-100 text-red-800' : type === 'warning' ? 'bg-yellow-100 text-yellow-800' : type === 'success' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}`;
            notification.innerHTML = `
                <div class="flex">
                    <div class="flex-shrink-0">
                        ${type === 'success' ? '<svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' : ''}
                        ${type === 'warning' ? '<svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0z"/></svg>' : ''}
                        ${type === 'danger' ? '<svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' : ''}
                        ${type === 'info' ? '<svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' : ''}
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">${body}</p>
                    </div>
                    <div class="ml-auto flex-shrink-0">
                        <button class="rounded-md p-1 text-[type === 'danger' ? 'red-500' : type === 'warning' ? 'yellow-500' : type === 'success' ? 'green-500' : 'blue-500']" @click="this.closest('.notification-container').remove()" aria-label="Close notification">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(notification);
            document.body.appendChild(container);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (container.parentNode) {
                    container.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>