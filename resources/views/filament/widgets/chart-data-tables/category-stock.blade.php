<div class="sr-only" role="region" aria-label="Inventory by Category Data Table">
    <h3>Inventory by Product Category</h3>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Total Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['labels'] as $index => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ $data['datasets'][0]['data'][$index] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
