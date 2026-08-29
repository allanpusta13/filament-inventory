<div class="sr-only" role="region" aria-label="Stock Movement Trend Data Table">
    <h3>Stock Inflow vs Outflow Data (30 Days)</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Stock In</th>
                <th>Stock Out</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['labels'] as $index => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ $data['datasets'][0]['data'][$index] }}</td>
                    <td>{{ $data['datasets'][1]['data'][$index] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
