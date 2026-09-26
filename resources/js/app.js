import './bootstrap';
import { createDesignEditor } from './design-editor';
import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

Alpine.data('flash', () => ({
    visible: true,
}));

Alpine.data('priceEstimator', (configuration) => ({
    endpoint: configuration.endpoint,
    estimate: configuration.estimate ?? null,
    response: configuration.response ?? null,
    loading: false,
    error: '',

    handleSubmit(event) {
        if (event.submitter?.hasAttribute('formaction') || event.submitter?.hasAttribute('data-design-editor-action')) {
            return;
        }

        event.preventDefault();
        this.fetchEstimate();
    },

    async fetchEstimate() {
        this.loading = true;
        this.error = '';

        const parameters = new URLSearchParams();
        const form = this.$refs.form ?? this.$root.closest('form');

        new FormData(form).forEach((value, key) => {
            if (key === '_token' || key === '_method' || (typeof File !== 'undefined' && value instanceof File)) {
                return;
            }

            parameters.append(key, value);
        });

        try {
            const response = await fetch(`${this.endpoint}?${parameters.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok || payload === null) {
                throw new Error('Estimasi harga belum dapat dimuat.');
            }

            this.response = payload;
            this.estimate = payload.estimate ?? payload.price ?? payload.total ?? null;
        } catch (requestError) {
            this.error = requestError.message || 'Terjadi masalah saat menghitung estimasi.';
        } finally {
            this.loading = false;
        }
    },

    formatAmount(value) {
        if (value === null || value === undefined || value === '') {
            return 'Belum dihitung';
        }

        const numericValue = Number(value);

        if (!Number.isFinite(numericValue)) {
            return String(value);
        }

        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(numericValue);
    },
}));

Alpine.data('kilatChart', (configuration) => ({
    configuration,
    chart: null,

    hasData() {
        return Array.isArray(this.configuration?.labels) &&
            this.configuration.labels.length > 0 &&
            Array.isArray(this.configuration?.datasets) &&
            this.configuration.datasets.length > 0;
    },

    init() {
        this.$nextTick(() => {
            if (!this.hasData() || !this.$refs.canvas) {
                return;
            }

            const { plugins = {}, ...chartOptions } = this.configuration.options ?? {};

            this.chart = new Chart(this.$refs.canvas, {
                ...this.configuration,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    ...chartOptions,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                boxHeight: 12,
                                usePointStyle: true,
                                color: '#556174',
                                padding: 18,
                                font: { family: "'Plus Jakarta Sans', system-ui, sans-serif" },
                            },
                        },
                        ...plugins,
                    },
                },
            });
        });
    },

    destroy() {
        this.chart?.destroy();
    },
}));

Alpine.data('confirmAction', (message) => ({
    message,
    confirm(event) {
        if (!window.confirm(this.message)) {
            event.preventDefault();
        }
    },
}));

Alpine.data('designEditor', createDesignEditor);

window.Alpine = Alpine;
Alpine.start();
