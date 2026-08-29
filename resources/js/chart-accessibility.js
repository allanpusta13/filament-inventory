// Chart.js Accessibility Plugin + Data Table Alternatives
// Provides WCAG AAA compliant chart accessibility

import Chart from 'chart.js/auto';

// Accessibility plugin for Chart.js
const accessibilityPlugin = {
    id: 'accessibility',
    beforeInit(chart) {
        // Store original data for data table generation
        chart.accessibilityData = {
            labels: chart.data.labels,
            datasets: chart.data.datasets.map(ds => ({
                label: ds.label,
                data: ds.data,
                backgroundColor: ds.backgroundColor,
                borderColor: ds.borderColor,
            })),
        };
    },
    afterDraw(chart) {
        // Add ARIA attributes to canvas
        const canvas = chart.canvas;
        if (!canvas.hasAttribute('aria-label')) {
            const title = chart.options.plugins?.title?.text || 'Chart';
            canvas.setAttribute('aria-label', `${title}. Press Enter for data table.`);
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('tabindex', '0');
        }
    },
};

// Register the plugin
Chart.register(accessibilityPlugin);

// Generate accessible data table HTML from chart data
export function generateChartDataTable(chart) {
    const { labels, datasets } = chart.accessibilityData || {};
    if (!labels || !datasets) return '';

    let html = '<table class="fi-chart-data-table" aria-hidden="false"><thead><tr><th scope="col">Period</th>';
    datasets.forEach(ds => {
        html += `<th scope="col">${ds.label}</th>`;
    });
    html += '</tr></thead><tbody>';

    labels.forEach((label, i) => {
        html += `<tr><th scope="row">${label}</th>`;
        datasets.forEach(ds => {
            const value = ds.data[i] ?? 0;
            html += `<td>${typeof value === 'number' ? value.toLocaleString() : value}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table>';
    return html;
}

// Initialize keyboard navigation for charts
export function initChartKeyboardNavigation() {
    document.querySelectorAll('.fi-chart-widget canvas').forEach(canvas => {
        canvas.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                showChartDataTable(canvas);
            }
        });
    });
}

// Show data table modal for a chart
function showChartDataTable(canvas) {
    // Find the chart instance
    const chartId = canvas.id;
    const chart = Chart.getChart(chartId);
    if (!chart) return;

    const tableHtml = generateChartDataTable(chart);
    if (!tableHtml) return;

    // Create modal
    const modal = document.createElement('div');
    modal.className = 'fi-chart-data-modal fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4';
    modal.innerHTML = `
        <div class="fi-modal-window w-full max-w-3xl max-h-[80vh] overflow-y-auto bg-white dark:bg-slate-900 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Chart Data Table</h2>
                <button class="fi-chart-data-close text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close data table">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="overflow-x-auto">${tableHtml}</div>
        </div>
    `;

    document.body.appendChild(modal);

    // Focus management
    const closeBtn = modal.querySelector('.fi-chart-data-close');
    closeBtn?.focus();

    // Close handlers
    const closeModal = () => {
        modal.remove();
        canvas.focus();
    };

    closeBtn?.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function escHandler(e) {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escHandler);
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChartKeyboardNavigation);
} else {
    initChartKeyboardNavigation();
}

// Re-initialize after Livewire navigation
document.addEventListener('livewire:navigated', initChartKeyboardNavigation);