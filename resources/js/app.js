import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import Chart from 'chart.js/auto';

Alpine.plugin(collapse);

window.Alpine = Alpine;
window.Chart = Chart;
window.__CRM_RELEASE_TAG__ = "v2026.10.22.release2";

Alpine.start();