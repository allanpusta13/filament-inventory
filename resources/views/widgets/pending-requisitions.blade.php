@if ($requisitions->isEmpty())
    <div class="text-center py-8">
        <p class="text-gray-500">No pending requisitions</p>
    </div>
@else
    <div class="space-y-4">
        @foreach ($requisitions as $req)
            <div class="p-4 border rounded-lg">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <div class="font-medium">{{ $req['reference_code'] }}</div>
                        <div class="text-sm text-gray-600">
                            From: {{ $req['from_warehouse_name'] }} → To: {{ $req['to_warehouse_name'] }}
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 text-xs font-medium 
                            {{ $req['status'] === 'draft' ? 'bg-gray-100 text-gray-800' : 
                              ($req['status'] === 'requested' ? 'bg-yellow-100 text-yellow-800' : 
                               ($req['status'] === 'under_review_fulfiller' || $req['status'] === 'under_review_requestor' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) }}">
                            {{ ucfirst(str_replace('_', ' ', $req['status'])) }}
                        </span>
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    Requested by: {{ $req['requested_by_name'] }} | {{ $req['requested_at'] }}
                </div>
            </div>
        @endforeach
    </div>
@endif