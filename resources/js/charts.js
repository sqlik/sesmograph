import ApexCharts from 'apexcharts';

/*
 * Chart layer - no blue/violet, custom palette only (see docs/architecture.md).
 * Hex values are the design tokens from app.css converted out of OKLCH,
 * lightness-separated so mint/coral stays legible under deutan/protan
 * simulation (validated with the dataviz palette checker).
 */
const color = {
    mint: '#4cb86a', // oklch(70% 0.15 150)
    coral: '#c1133a', // oklch(52% 0.20 18)
    pear: '#cbb042', // oklch(76% 0.13 95)
    // Theme-dependent: refreshed from the CSS tokens in mountCharts.
    ink: '#12171b',
    inkFaint: '#6c6960',
    edge: '#d4d1c7',
    panel: '#eeebdf',
    // Donut-only shades: same three families (green = good, red = failed,
    // yellow = warned) plus warm grays for neutral pipeline events.
    forest: '#2e7d4f',
    mintSoft: '#8fd3a4',
    coralDeep: '#8f1030',
    coralSoft: '#e2708a',
    pearSoft: '#e0d08a',
    stone: '#a39f92',
};

const base = {
    chart: {
        fontFamily: "'Onest', ui-sans-serif, system-ui, sans-serif",
        foreColor: color.inkFaint,
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: false },
        parentHeightOffset: 0,
    },
    grid: {
        borderColor: color.edge,
        strokeDashArray: 0,
        xaxis: { lines: { show: false } },
    },
    dataLabels: { enabled: false },
    tooltip: { theme: 'light' },
    xaxis: {
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { colors: color.inkFaint } },
    },
};

function merge(target, patch) {
    for (const key of Object.keys(patch)) {
        target[key] =
            patch[key] && typeof patch[key] === 'object' && !Array.isArray(patch[key])
                ? merge({ ...(target[key] ?? {}) }, patch[key])
                : patch[key];
    }

    return target;
}

const kinds = {
    // Stacked daily volume: sends decomposed into delivered / bounced / other.
    volume(el) {
        return {
            chart: { type: 'bar', stacked: true, height: 260 },
            series: JSON.parse(el.dataset.series),
            colors: [color.mint, color.coral, color.pear],
            plotOptions: {
                bar: { columnWidth: '62%', borderRadius: 2, borderRadiusApplication: 'end' },
            },
            // Panel-colored stroke keeps a visible gap between stacked segments.
            stroke: { show: true, width: 2, colors: [color.panel] },
            legend: { position: 'top', horizontalAlign: 'left', markers: { shape: 'circle' } },
            xaxis: { categories: JSON.parse(el.dataset.categories), tickAmount: 10 },
            yaxis: { labels: { formatter: (v) => Math.round(v) } },
        };
    },

    // Single-series rate line with an AWS threshold annotation.
    rate(el) {
        const threshold = parseFloat(el.dataset.threshold);
        const seriesColor = color[el.dataset.color] ?? color.coral;
        const digits = threshold < 1 ? 2 : 1;

        return {
            chart: { type: 'line', height: 220 },
            series: [{ name: el.dataset.name, data: JSON.parse(el.dataset.series) }],
            colors: [seriesColor],
            stroke: { width: 2, curve: 'straight' },
            markers: { size: 0, hover: { size: 5 } },
            xaxis: { categories: JSON.parse(el.dataset.categories), tickAmount: 10 },
            yaxis: {
                min: 0,
                max: (max) => Math.max(max * 1.2, threshold * 1.3),
                labels: { formatter: (v) => `${v.toFixed(digits)}%` },
            },
            tooltip: { y: { formatter: (v) => (v === null ? 'no sends' : `${v.toFixed(digits)}%`) } },
            annotations: {
                yaxis: [
                    {
                        y: threshold,
                        borderColor: color.coral,
                        strokeDashArray: 4,
                        label: {
                            text: `AWS limit ${threshold}%`,
                            position: 'left',
                            textAnchor: 'start',
                            offsetY: -6,
                            borderWidth: 0,
                            style: { background: color.panel, color: color.ink, fontSize: '11px', padding: { left: 4, right: 4, top: 2, bottom: 2 } },
                        },
                    },
                ],
            },
        };
    },

    // Donut of event types. The legend is our own HTML pinned to the card
    // bottom (see mountLegend) - entries toggle their slice on click.
    distribution(el) {
        const rows = JSON.parse(el.dataset.series);

        return {
            chart: { type: 'donut', height: 380 },
            series: rows.map((r) => r.count),
            labels: rows.map((r) => r.label),
            colors: rows.map((r) => color[r.color] ?? color.inkFaint),
            stroke: { width: 2, colors: [color.panel] },
            legend: { show: false },
            plotOptions: {
                pie: {
                    expandOnClick: false,
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { fontSize: '13px', color: color.inkFaint },
                            value: {
                                fontSize: '22px',
                                fontWeight: 600,
                                color: color.ink,
                                formatter: (v) => Number(v).toLocaleString(),
                            },
                            total: {
                                show: true,
                                label: 'Events',
                                fontSize: '13px',
                                color: color.inkFaint,
                                formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString(),
                            },
                        },
                    },
                },
            },
            tooltip: { y: { formatter: (v) => v.toLocaleString() } },
        };
    },
};

// Custom legend in a `[data-chart-legend]` sibling: dots + labels, click
// toggles the slice (chart.toggleSeries mirrors a legend click).
function mountLegend(el, chart) {
    const target = el.parentElement?.querySelector('[data-chart-legend]');

    if (!target) {
        return;
    }

    JSON.parse(el.dataset.series).forEach((row) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'inline-flex items-center gap-1.5 rounded text-sm text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus';

        const dot = document.createElement('span');
        dot.className = 'inline-block h-2.5 w-2.5 rounded-full';
        dot.style.background = color[row.color] ?? color.inkFaint;

        btn.append(dot, document.createTextNode(row.label));
        btn.setAttribute('aria-pressed', 'true');
        btn.addEventListener('click', () => {
            const wasHidden = chart.toggleSeries(row.label);
            btn.style.opacity = wasHidden ? '' : '0.4';
            btn.setAttribute('aria-pressed', wasHidden ? 'true' : 'false');
        });

        target.appendChild(btn);
    });
}

// Pull the theme-dependent colors from the active CSS tokens so charts
// follow the user's theme; data colors (mint/coral/pear...) stay fixed.
function syncThemeColors() {
    const styles = getComputedStyle(document.documentElement);
    const token = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;

    color.ink = token('--color-ink', color.ink);
    color.inkFaint = token('--color-ink-faint', color.inkFaint);
    color.edge = token('--color-chart-grid', color.edge);
    color.panel = token('--color-panel', color.panel);

    base.chart.foreColor = color.inkFaint;
    base.grid.borderColor = color.edge;
}

export default function mountCharts() {
    syncThemeColors();

    document.querySelectorAll('[data-chart]').forEach((el) => {
        const build = kinds[el.dataset.chart];

        if (build) {
            const chart = new ApexCharts(el, merge(structuredClone(base), build(el)));
            chart.render();

            if (el.dataset.chart === 'distribution') {
                mountLegend(el, chart);
            }
        }
    });
}
