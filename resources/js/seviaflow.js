import 'flowbite';
import '../css/seviaflow.css';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const initDashboardCharts = () => {
  document.querySelectorAll('[data-sf-chart="1"]').forEach((canvas) => {
    if (!(canvas instanceof HTMLCanvasElement)) {
      return;
    }

    if (canvas.dataset.sfChartInitialized === '1') {
      return;
    }

    const type = canvas.dataset.chartType ?? 'bar';
    const dataRaw = canvas.dataset.chartData ?? '{}';

    let parsedData = {};
    try {
      parsedData = JSON.parse(dataRaw);
    } catch {
      parsedData = { labels: [], datasets: [] };
    }

    const safeType = ['bar', 'line', 'doughnut'].includes(type) ? type : 'bar';

    new Chart(canvas, {
      type: safeType,
      data: parsedData,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
          },
        },
      },
    });

    canvas.dataset.sfChartInitialized = '1';
  });
};

const initDashboardLayoutEditor = () => {
  const editor = document.querySelector('[data-sf-dashboard-layout-editor="1"]');
  if (!(editor instanceof HTMLElement)) {
    return;
  }

  if (editor.dataset.layoutEditing !== '1') {
    return;
  }

  const payloadInput = document.querySelector('[data-sf-dashboard-layout-input]');
  if (!(payloadInput instanceof HTMLInputElement)) {
    return;
  }

  if (editor.dataset.sfLayoutInitialized === '1') {
    return;
  }

  const widgetNodes = () => Array.from(editor.querySelectorAll('[data-widget]'));

  const syncPayload = () => {
    const layout = widgetNodes().map((node, index) => {
      const widget = node.getAttribute('data-widget') ?? '';
      const widthSelect = node.querySelector('[data-widget-width="1"]');
      const visibleInput = node.querySelector('[data-widget-visible="1"]');
      const width = widthSelect instanceof HTMLSelectElement ? parseInt(widthSelect.value, 10) : 1;
      const visible = visibleInput instanceof HTMLInputElement ? visibleInput.checked : true;

      return {
        widget,
        x: 0,
        y: index,
        w: Number.isFinite(width) && width > 0 ? width : 1,
        h: 1,
        visible,
      };
    });

    payloadInput.value = JSON.stringify(layout);
    payloadInput.dispatchEvent(new Event('input', { bubbles: true }));
    payloadInput.dispatchEvent(new Event('change', { bubbles: true }));
  };

  let draggedNode = null;

  widgetNodes().forEach((node) => {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    node.addEventListener('dragstart', () => {
      draggedNode = node;
      node.classList.add('opacity-70');
    });

    node.addEventListener('dragend', () => {
      node.classList.remove('opacity-70');
      draggedNode = null;
      syncPayload();
    });

    node.addEventListener('dragover', (event) => {
      event.preventDefault();
    });

    node.addEventListener('drop', (event) => {
      event.preventDefault();
      if (!(draggedNode instanceof HTMLElement) || draggedNode === node) {
        return;
      }

      const rect = node.getBoundingClientRect();
      const before = event.clientY < rect.top + rect.height / 2;
      if (before) {
        editor.insertBefore(draggedNode, node);
      } else {
        editor.insertBefore(draggedNode, node.nextSibling);
      }
      syncPayload();
    });

    const widthSelect = node.querySelector('[data-widget-width="1"]');
    if (widthSelect instanceof HTMLSelectElement) {
      widthSelect.addEventListener('change', syncPayload);
    }

    const visibleInput = node.querySelector('[data-widget-visible="1"]');
    if (visibleInput instanceof HTMLInputElement) {
      visibleInput.addEventListener('change', syncPayload);
    }
  });

  editor.dataset.sfLayoutInitialized = '1';
  syncPayload();
};

document.addEventListener('DOMContentLoaded', initDashboardCharts);
document.addEventListener('livewire:navigated', initDashboardCharts);
document.addEventListener('DOMContentLoaded', initDashboardLayoutEditor);
document.addEventListener('livewire:navigated', initDashboardLayoutEditor);
