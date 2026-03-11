/**
 * block_graphreports/charts
 */
define(['core/chartjs'], function(Chart) {
    const initCharts = () => {
        const canvases = document.querySelectorAll('canvas[data-chart]');

        canvases.forEach((canvas) => {
            if (canvas.dataset.initialized) {
                return;
            }

            try {
                const config = JSON.parse(canvas.dataset.chart);
                new Chart(canvas, config);
                canvas.dataset.initialized = '1';
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('[block_graphreports] Chart init error:', canvas.id, error);
            }
        });
    };

    return {
        init: function() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCharts);
            } else {
                initCharts();
            }
        }
    };
});
