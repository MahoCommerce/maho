/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class MahoDashboard
{

    constructor(config = {}) {
        this.config = config;

        console.log(config)

        this.initializeChartJs();
        document.addEventListener('DOMContentLoaded', this.bindEventListeners.bind(this));
    }

    bindEventListeners() {
        document.getElementById(this.config.diagrams?.switcherId)?.addEventListener('change', (event) => {
            this.changeDiagramsPeriod(event.target);
        });
    }

    async changeDiagramsPeriod(periodSwitcherEl) {
        if (!Array.isArray(this.config.diagrams?.tabs)) {
            return;
        }

        for (const tabId of this.config.diagrams.tabs) {
            const html = await mahoFetch(setRouteParams(this.config.diagrams.ajaxUrl, {
                block: `tab_${tabId}`,
                period: periodSwitcherEl.value,
            }));

            const tabContentEl = document.getElementById(`${this.config.diagrams.htmlId}_${tabId}_content`);
            updateElementHtmlAndExecuteScripts(tabContentEl, html);
        }

        const html = await mahoFetch(setRouteParams(this.config.diagrams.ajaxUrl, {
            block: 'totals',
            period: periodSwitcherEl.value,
        }));

        const totalsEl = document.getElementById('dashboard_diagram_totals');
        totalsEl.outerHTML = html;
    }

    drawChart(canvasId, datasets, labels) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');

        const borderColor = '#adb41a';
        const backgroundColor = ctx.createLinearGradient(0, 0, 0, 400);
        backgroundColor.addColorStop(0, `${borderColor}77`);
        backgroundColor.addColorStop(1, `${borderColor}00`);

        const config = {
            type: 'line',
            data: {
                labels,
                datasets: Object.entries(datasets).map(([id, data]) => ({
                    id,
                    data,
                    borderColor,
                    fill: true,
                    backgroundColor,
                    pointRadius: 0,
                    tension: 0.2,
                })),
            },
            options: {
                interaction: {
                    intersect: false,
                    mode: 'index',
                    position: 'cursor',
                },
                scales: {
                    y: {
                        min: 0,
                        //suggestedMax: 10,
                    },
                },
            },
        };
        new Chart(ctx, config);
    }


    initializeChartJs() {
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.animation.duration = 0;
        Chart.defaults.plugins.tooltip.animation = false;
        Chart.defaults.plugins.legend.display = false;

        Chart.Tooltip.positioners.cursor = (_, coordinates) => coordinates;

        Chart.register({
            id: 'linePlugin',
            afterInit: (chart, args, opts) => {
                chart.hoverState = { x: 0, draw: false };
            },
            afterEvent: (chart, args) => {
                chart.hoverState = { x: args.event.x, draw: args.inChartArea}
                chart.draw()
            },
            beforeDatasetsDraw: (chart, args, opts) => {
                if (!chart.hoverState.draw) {
                    return;
                }

                const ctx = chart.ctx;
                ctx.save()
                ctx.beginPath()
                ctx.lineWidth = 0.25;
                ctx.strokeStyle = 'black';
                ctx.moveTo(chart.hoverState.x, chart.chartArea.bottom)
                ctx.lineTo(chart.hoverState.x, chart.chartArea.top)
                ctx.stroke()
                ctx.restore()
            }
        });
    }

}
