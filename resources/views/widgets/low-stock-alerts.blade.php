@if ($alerts->isEmpty())
    <div class="text-center py-8">
        <p class="text-gray-500">No low stock alerts</p>
    </div>
@else
    <div class="space-y-4">
        @foreach ($alerts as $alert)
            <div class="p-4 border rounded-lg {{ $alert['days_supply'] <= 3 ? 'border-red-200 bg-red-50' : ($alert['days_supply'] <= 7 ? 'border-yellow-200 bg-yellow-50' : 'border-green-200 bg-green-50') }}">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-medium">{{ $alert['product_name'] }} ({{ $alert['variant_sku'] }})</div>
                        <div class="text-sm text-gray-600">
                            On Hand: <strong>{{ $alert['on_hand_quantity'] }}</strong> | 
                            Reorder Point: <strong>{{ $alert['reorder_point'] }}</strong> |
                            Days Supply: <strong>{{ $alert['days_supply'] }}</strong>
                        </div>
                    </div>
                    <div class="text-right">
                        @if ($alert['days_supply'] <= 3)
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">URGENT</span>
                        @elseif ($alert['days_supply'] <= 7)
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded">LOW</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">OK</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif