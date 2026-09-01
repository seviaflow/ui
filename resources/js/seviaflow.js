import 'flowbite';
import '../css/seviaflow.css';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const sidebarPreferenceKey = 'seviaflow:sidebar:collapsed';
const tableDensityPreferenceKey = 'seviaflow:table:density';

const getStoredPreference = (key) => {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
};

const setStoredPreference = (key, value) => {
  try {
    window.localStorage.setItem(key, value);
  } catch {
    // Local preferences are optional; restricted storage must not break the UI.
  }
};

const escapeHtml = (value) => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;');

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

    const safeType = {
      bar: 'bar',
      grouped_bar: 'bar',
      stacked_bar: 'bar',
      line: 'line',
      doughnut: 'doughnut',
    }[type] ?? 'bar';
    const stacked = type === 'stacked_bar' || parsedData.stacked === true;
    const scales = safeType === 'doughnut'
      ? {}
      : {
          x: {
            stacked,
          },
          y: {
            beginAtZero: true,
            stacked,
          },
        };

    new Chart(canvas, {
      type: safeType,
      data: parsedData,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales,
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

const commandIconLabel = (label) => {
  const parts = String(label).trim().split(/\s+/).filter(Boolean);
  const initials = parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');

  return initials || '>';
};

const safeCommandItems = (script) => {
  if (!(script instanceof HTMLScriptElement)) {
    return [];
  }

  try {
    const parsed = JSON.parse(script.textContent || '[]');
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.filter((item) => item
      && typeof item.label === 'string'
      && typeof item.description === 'string'
      && typeof item.url === 'string'
      && item.url !== '');
  } catch {
    return [];
  }
};

const syncDrawerTriggers = (drawer, expanded) => {
  if (!(drawer instanceof HTMLElement)) {
    return;
  }

  const drawerId = drawer.getAttribute('id');
  if (!drawerId) {
    return;
  }

  document.querySelectorAll('[data-sf-drawer-target]').forEach((trigger) => {
    if (trigger instanceof HTMLElement && trigger.getAttribute('data-sf-drawer-target') === drawerId) {
      trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  });
};

const initShell = () => {
  const shell = document.querySelector('[data-sf-shell]');
  if (!(shell instanceof HTMLElement) || shell.dataset.sfShellInitialized === '1') {
    return;
  }

  const body = document.body;
  const overlay = shell.querySelector('[data-sf-overlay]');
  const sidebarToggle = shell.querySelector('[data-sf-sidebar-toggle]');
  const mobileMenu = shell.querySelector('[data-sf-mobile-menu]');
  const commandOpen = shell.querySelector('[data-sf-command-open]');
  const commandPalette = shell.querySelector('[data-sf-command-palette]');
  const commandInput = shell.querySelector('[data-sf-command-input]');
  const commandResults = shell.querySelector('[data-sf-command-results]');
  const commandEmpty = shell.querySelector('[data-sf-command-empty]');
  const commandItems = safeCommandItems(shell.querySelector('[data-sf-command-items]'));
  let activeCommandIndex = 0;
  let visibleCommandItems = commandItems;

  const closeOverlayIfIdle = () => {
    const commandOpenState = commandPalette instanceof HTMLElement && !commandPalette.hidden;
    const drawerOpenState = document.querySelector('[data-sf-drawer][aria-hidden="false"]') !== null;
    if (!body.classList.contains('sf-mobile-nav-open') && !commandOpenState && !drawerOpenState && overlay instanceof HTMLElement) {
      overlay.hidden = true;
      overlay.classList.remove('sf-backdrop-visible');
      body.classList.remove('sf-overlay-open');
    }
  };

  const showOverlay = () => {
    if (!(overlay instanceof HTMLElement)) {
      return;
    }

    overlay.hidden = false;
    overlay.classList.add('sf-backdrop-visible');
    body.classList.add('sf-overlay-open');
  };

  const closeMobileNavigation = () => {
    body.classList.remove('sf-mobile-nav-open');
    if (mobileMenu instanceof HTMLElement) {
      mobileMenu.setAttribute('aria-expanded', 'false');
    }
    closeOverlayIfIdle();
  };

  const closeOpenDrawers = () => {
    document.querySelectorAll('[data-sf-drawer][aria-hidden="false"]').forEach((drawer) => {
      if (drawer instanceof HTMLElement) {
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
        syncDrawerTriggers(drawer, false);
      }
    });
  };

  const setSidebarCollapsed = (collapsed, persist = true) => {
    if (window.matchMedia('(max-width: 1023px)').matches) {
      body.classList.remove('sf-sidebar-collapsed');
      if (sidebarToggle instanceof HTMLElement) {
        sidebarToggle.setAttribute('aria-expanded', 'true');
        sidebarToggle.setAttribute('aria-label', 'Close navigation');
      }

      return;
    }

    body.classList.toggle('sf-sidebar-collapsed', collapsed);
    if (sidebarToggle instanceof HTMLElement) {
      sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
    }
    if (persist) {
      setStoredPreference(sidebarPreferenceKey, collapsed ? '1' : '0');
    }
  };

  const renderCommands = (query = '') => {
    if (!(commandResults instanceof HTMLElement) || !(commandEmpty instanceof HTMLElement)) {
      return;
    }

    const terms = query.trim().toLowerCase().split(/\s+/).filter(Boolean);
    visibleCommandItems = commandItems.filter((item) => {
      const haystack = `${item.label} ${item.description}`.toLowerCase();

      return terms.every((term) => haystack.includes(term));
    });

    activeCommandIndex = Math.min(activeCommandIndex, Math.max(visibleCommandItems.length - 1, 0));
    commandEmpty.hidden = visibleCommandItems.length > 0;
    commandResults.innerHTML = visibleCommandItems.map((item, index) => `
      <a
        class="sf-command-result ${index === activeCommandIndex ? 'sf-command-result-active' : ''}"
        href="${escapeHtml(item.url)}"
        role="option"
        aria-selected="${index === activeCommandIndex ? 'true' : 'false'}"
        data-sf-command-result
      >
        <span class="sf-command-result-icon" aria-hidden="true">${escapeHtml(commandIconLabel(item.label))}</span>
        <span class="sf-command-result-copy">
          <strong>${escapeHtml(item.label)}</strong>
          <span>${escapeHtml(item.description)}</span>
        </span>
      </a>
    `).join('');
  };

  const closeCommandPalette = () => {
    if (!(commandPalette instanceof HTMLElement) || commandPalette.hidden) {
      return;
    }

    commandPalette.hidden = true;
    if (commandOpen instanceof HTMLElement) {
      commandOpen.setAttribute('aria-expanded', 'false');
    }
    if (commandInput instanceof HTMLInputElement) {
      commandInput.value = '';
    }
    renderCommands('');
    closeOverlayIfIdle();
  };

  const openCommandPalette = () => {
    if (!(commandPalette instanceof HTMLElement)) {
      return;
    }

    showOverlay();
    commandPalette.hidden = false;
    if (commandOpen instanceof HTMLElement) {
      commandOpen.setAttribute('aria-expanded', 'true');
    }
    activeCommandIndex = 0;
    renderCommands(commandInput instanceof HTMLInputElement ? commandInput.value : '');
    window.setTimeout(() => {
      if (commandInput instanceof HTMLInputElement) {
        commandInput.focus();
        commandInput.select();
      }
    }, 20);
  };

  setSidebarCollapsed(getStoredPreference(sidebarPreferenceKey) === '1', false);
  renderCommands('');

  sidebarToggle?.addEventListener('click', () => {
    if (window.matchMedia('(max-width: 1023px)').matches) {
      closeMobileNavigation();
      return;
    }

    setSidebarCollapsed(!body.classList.contains('sf-sidebar-collapsed'));
  });

  mobileMenu?.addEventListener('click', () => {
    body.classList.add('sf-mobile-nav-open');
    if (mobileMenu instanceof HTMLElement) {
      mobileMenu.setAttribute('aria-expanded', 'true');
    }
    showOverlay();
  });

  shell.querySelectorAll('[data-sf-nav-link]').forEach((link) => {
    link.addEventListener('click', closeMobileNavigation);
  });

  commandOpen?.addEventListener('click', openCommandPalette);
  overlay?.addEventListener('click', () => {
    closeCommandPalette();
    closeMobileNavigation();
    closeOpenDrawers();
    closeOverlayIfIdle();
  });

  commandInput?.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement) {
      activeCommandIndex = 0;
      renderCommands(event.target.value);
    }
  });

  commandPalette?.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('[data-sf-command-result]')) {
      closeCommandPalette();
    }
  });

  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      openCommandPalette();
      return;
    }

    if (event.key === 'Escape') {
      closeCommandPalette();
      closeMobileNavigation();
      closeOpenDrawers();
      closeOverlayIfIdle();
      return;
    }

    if (!(commandPalette instanceof HTMLElement) || commandPalette.hidden) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      activeCommandIndex = Math.min(activeCommandIndex + 1, Math.max(visibleCommandItems.length - 1, 0));
      renderCommands(commandInput instanceof HTMLInputElement ? commandInput.value : '');
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      activeCommandIndex = Math.max(activeCommandIndex - 1, 0);
      renderCommands(commandInput instanceof HTMLInputElement ? commandInput.value : '');
    }

    if (event.key === 'Enter' && visibleCommandItems[activeCommandIndex]) {
      event.preventDefault();
      window.location.href = visibleCommandItems[activeCommandIndex].url;
    }
  });

  window.addEventListener('resize', () => {
    setSidebarCollapsed(body.classList.contains('sf-sidebar-collapsed'), false);
    if (!window.matchMedia('(max-width: 1023px)').matches) {
      closeMobileNavigation();
    }
  });

  shell.dataset.sfShellInitialized = '1';
};

const initDrawers = () => {
  document.querySelectorAll('[data-sf-drawer-target]').forEach((trigger) => {
    if (!(trigger instanceof HTMLElement) || trigger.dataset.sfDrawerTriggerInitialized === '1') {
      return;
    }

    trigger.addEventListener('click', () => {
      const drawerId = trigger.getAttribute('data-sf-drawer-target') || '';
      const drawer = document.getElementById(drawerId);
      const shell = document.querySelector('[data-sf-shell]');
      const overlay = shell?.querySelector('[data-sf-overlay]');

      if (!(drawer instanceof HTMLElement)) {
        return;
      }

      drawer.hidden = false;
      drawer.setAttribute('aria-hidden', 'false');
      syncDrawerTriggers(drawer, true);
      if (overlay instanceof HTMLElement) {
        overlay.hidden = false;
        overlay.classList.add('sf-backdrop-visible');
      }
      document.body.classList.add('sf-overlay-open');
      drawer.querySelector('input, select, textarea, button')?.focus();
    });

    trigger.dataset.sfDrawerTriggerInitialized = '1';
  });

  document.querySelectorAll('[data-sf-drawer-close]').forEach((button) => {
    if (!(button instanceof HTMLElement) || button.dataset.sfDrawerCloseInitialized === '1') {
      return;
    }

    button.addEventListener('click', () => {
      const drawer = button.closest('[data-sf-drawer]');
      const shell = document.querySelector('[data-sf-shell]');
      const overlay = shell?.querySelector('[data-sf-overlay]');

      if (drawer instanceof HTMLElement) {
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
        syncDrawerTriggers(drawer, false);
      }

      const commandOpenState = document.querySelector('[data-sf-command-palette]:not([hidden])') !== null;
      const mobileOpenState = document.body.classList.contains('sf-mobile-nav-open');
      if (document.querySelector('[data-sf-drawer][aria-hidden="false"]') === null && !commandOpenState && !mobileOpenState && overlay instanceof HTMLElement) {
        overlay.hidden = true;
        overlay.classList.remove('sf-backdrop-visible');
        document.body.classList.remove('sf-overlay-open');
      }
    });

    button.dataset.sfDrawerCloseInitialized = '1';
  });
};

const initTableDensity = () => {
  const body = document.body;
  const preferredDensity = getStoredPreference(tableDensityPreferenceKey) || 'comfortable';
  body.dataset.sfTableDensity = preferredDensity === 'compact' ? 'compact' : 'comfortable';

  document.querySelectorAll('[data-sf-density]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement) || button.dataset.sfDensityInitialized === '1') {
      return;
    }

    const sync = () => {
      button.setAttribute('aria-pressed', body.dataset.sfTableDensity === button.dataset.sfDensity ? 'true' : 'false');
    };

    button.addEventListener('click', () => {
      const density = button.dataset.sfDensity === 'compact' ? 'compact' : 'comfortable';
      body.dataset.sfTableDensity = density;
      setStoredPreference(tableDensityPreferenceKey, density);
      document.querySelectorAll('[data-sf-density]').forEach((candidate) => {
        if (candidate instanceof HTMLButtonElement) {
          candidate.setAttribute('aria-pressed', body.dataset.sfTableDensity === candidate.dataset.sfDensity ? 'true' : 'false');
        }
      });
    });

    button.dataset.sfDensityInitialized = '1';
    sync();
  });
};

const initSeviaFlowUi = () => {
  initDashboardCharts();
  initDashboardLayoutEditor();
  initShell();
  initDrawers();
  initTableDensity();
};

document.addEventListener('DOMContentLoaded', initSeviaFlowUi);
document.addEventListener('livewire:navigated', initSeviaFlowUi);
