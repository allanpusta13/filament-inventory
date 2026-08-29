<div class="sr-only" role="region" aria-label="Top 10 High-Turnover Products Data Table">
    <h3>Top 10 High-Turnover Products</h3>
    <table>
        <thead>
            <tr>
                <th>Product</th>
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
