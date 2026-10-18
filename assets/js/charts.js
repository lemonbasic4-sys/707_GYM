/**
 * 707 GYM – Chart.js Configuration & Factory Functions
 * Dark-mode defaults with orange accent palette.
 * Requires Chart.js >= 4 loaded before this script.
 */

/* ── Global Palette ──────────────────────────────────────── */
const GYM_COLORS = {
    accent:  '#FF3D00',
    blue:    '#3B82F6',
    green:   '#22C55E',
    red:     '#EF4444',
    yellow:  '#EAB308',
    purple:  '#A855F7',
    teal:    '#14B8A6',
    muted:   '#6B7280',
    grid:    'rgba(255, 255, 255, 0.06)',
    text:    '#F5F5F6',
    textMuted: '#6B7280',
};

/** Default palette array used when callers don't specify colors. */
const DEFAULT_PALETTE = [
    GYM_COLORS.accent,
    GYM_COLORS.blue,
    GYM_COLORS.green,
    GYM_COLORS.purple,
    GYM_COLORS.yellow,
    GYM_COLORS.teal,
    GYM_COLORS.red,
];

/* ── Global Chart.js Defaults ────────────────────────────── */
if (typeof Chart !== 'undefined') {
    Chart.defaults.color            = GYM_COLORS.textMuted;
    Chart.defaults.font.family      = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size        = 12;
    Chart.defaults.animation.duration = 600;
    Chart.defaults.plugins.legend.labels.boxWidth   = 12;
    Chart.defaults.plugins.legend.labels.borderRadius = 3;
    Chart.defaults.plugins.legend.labels.padding    = 16;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(28, 28, 33, 0.95)';
    Chart.defaults.plugins.tooltip.borderColor     = 'rgba(255,255,255,0.08)';
    Chart.defaults.plugins.tooltip.borderWidth      = 1;
    Chart.defaults.plugins.tooltip.padding          = 12;
    Chart.defaults.plugins.tooltip.cornerRadius     = 8;
    Chart.defaults.plugins.tooltip.titleColor       = GYM_COLORS.text;
    Chart.defaults.plugins.tooltip.bodyColor        = GYM_COLORS.textMuted;
}

/* ── Helpers ─────────────────────────────────────────────── */
/**
 * Destroy an existing chart on a canvas before creating a new one.
 * @param {string} canvasId
 */
function destroyChart(canvasId) {
    const existing = Chart.getChart(canvasId);
    if (existing) existing.destroy();
}

/**
 * Build common scale options for X/Y axes.
 */
function buildScaleDefaults() {
    return {
        x: {
            grid:  { color: GYM_COLORS.grid, drawBorder: false },
            ticks: { color: GYM_COLORS.textMuted },
        },
        y: {
            grid:  { color: GYM_COLORS.grid, drawBorder: false },
            ticks: { color: GYM_COLORS.textMuted },
            beginAtZero: true,
        },
    };
}

/* ── Line Chart ──────────────────────────────────────────── */
/**
 * Create (or recreate) a line chart.
 *
 * @param {string}   canvasId   ID of <canvas> element
 * @param {string[]} labels     X-axis labels
 * @param {Object[]} datasets   Chart.js dataset objects
 * @param {Object}   [options]  Partial Chart.js options (merged)
 * @returns {Chart}
 */
function createLineChart(canvasId, labels, datasets, options = {}) {
    destroyChart(canvasId);

    // Apply default colors if none specified
    datasets = datasets.map((ds, i) => ({
        borderColor:     ds.borderColor     ?? DEFAULT_PALETTE[i % DEFAULT_PALETTE.length],
        backgroundColor: ds.backgroundColor ?? `${DEFAULT_PALETTE[i % DEFAULT_PALETTE.length]}20`,
        borderWidth:     ds.borderWidth     ?? 2,
        tension:         ds.tension         ?? 0.4,
        fill:            ds.fill            ?? true,
        pointRadius:     ds.pointRadius     ?? 3,
        pointHoverRadius: ds.pointHoverRadius ?? 5,
        ...ds,
    }));

    return new Chart(canvasId, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: datasets.length > 1 },
                ...options.plugins,
            },
            scales: {
                ...buildScaleDefaults(),
                ...options.scales,
            },
            ...options,
        },
    });
}

/* ── Bar Chart ───────────────────────────────────────────── */
/**
 * Create (or recreate) a bar chart.
 *
 * @param {string}   canvasId
 * @param {string[]} labels
 * @param {Object[]} datasets
 * @param {Object}   [options]
 * @returns {Chart}
 */
function createBarChart(canvasId, labels, datasets, options = {}) {
    destroyChart(canvasId);

    datasets = datasets.map((ds, i) => ({
        backgroundColor: ds.backgroundColor ?? DEFAULT_PALETTE[i % DEFAULT_PALETTE.length],
        borderRadius:    ds.borderRadius     ?? 6,
        borderSkipped:   ds.borderSkipped    ?? false,
        ...ds,
    }));

    return new Chart(canvasId, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: datasets.length > 1 },
                ...options.plugins,
            },
            scales: {
                ...buildScaleDefaults(),
                ...options.scales,
            },
            ...options,
        },
    });
}

/* ── Doughnut Chart ──────────────────────────────────────── */
/**
 * Create (or recreate) a doughnut chart.
 *
 * @param {string}   canvasId
 * @param {string[]} labels
 * @param {number[]} data        Single dataset values
 * @param {Object}   [options]
 * @returns {Chart}
 */
function createDoughnutChart(canvasId, labels, data, options = {}) {
    destroyChart(canvasId);

    const colors = options.colors ?? DEFAULT_PALETTE.slice(0, data.length);

    return new Chart(canvasId, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: GYM_COLORS.textMuted, padding: 14, boxWidth: 12 },
                    ...options.legend,
                },
                ...options.plugins,
            },
            ...options,
        },
    });
}
