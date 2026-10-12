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

const dashboardChartState = new WeakMap();
const richTextInstances = new WeakMap();
const richTextRetryControllers = new WeakMap();
let richTextModulePromise = null;
let livewireHooksRegistered = false;
let dashboardChartObserverRegistered = false;
let toolbarMenusInitialized = false;
let overlayControllerRegistered = false;
let overlayLivewireHookRegistered = false;
let tableSelectionLivewireHookRegistered = false;
let formUiRegistered = false;
let formLivewireHookRegistered = false;
let formFocusEventRegistered = false;
let focusedFormControlId = null;
let activeDialogId = null;
let activeDialogTrigger = null;
let pendingDialogId = null;
const inertBackgroundNodes = new Map();

const isSafeRichTextUrl = (value) => {
  const url = String(value ?? '').trim();
  if (url === '' || /[\u0000-\u0020\u007f]/.test(url)) {
    return false;
  }

  try {
    const parsed = new URL(url, window.location.origin);
    const protocol = parsed.protocol.toLowerCase();
    if (['http:', 'https:', 'mailto:'].includes(protocol)) {
      return true;
    }
  } catch {
    return false;
  }

  return url.startsWith('#') || (url.startsWith('/') && !url.startsWith('//'));
};

const normalizeRichTextHtml = (value) => {
  const html = String(value ?? '').trim();

  return html === '<p></p>' ? '' : html;
};

const loadRichTextModules = () => {
  if (__SEVIAFLOW_TEST_RICH_TEXT_FAILURE__ && window.__seviaflowTestRichTextFailure === true) {
    return Promise.reject(new Error('Controlled Rich Text initialization failure.'));
  }

  if (richTextModulePromise === null) {
    richTextModulePromise = Promise.all([
      import('@tiptap/core'),
      import('@tiptap/starter-kit'),
    ]).then(([coreModule, starterKitModule]) => ({
      Editor: coreModule.Editor,
      StarterKit: starterKitModule.default,
    })).catch((error) => {
      richTextModulePromise = null;
      throw error;
    });
  }

  return richTextModulePromise;
};

const setRichTextStatus = (root, state, message = '') => {
  root.dataset.sfRichtextState = state;
  const status = root.querySelector('[data-sf-richtext-status]');
  const retry = root.querySelector('[data-sf-richtext-retry]');
  const editorElement = root.querySelector('[data-sf-richtext-editor]');
  const input = root.querySelector('[data-sf-richtext-input]');
  const ready = state === 'ready';
  const fallback = state === 'error';

  if (status instanceof HTMLElement) {
    status.dataset.state = state;
    status.textContent = message;
    status.hidden = ready;
  }
  if (retry instanceof HTMLButtonElement) {
    retry.hidden = !fallback;
  }
  if (editorElement instanceof HTMLElement) {
    editorElement.hidden = !ready;
    editorElement.setAttribute('aria-hidden', ready ? 'false' : 'true');
  }
  if (input instanceof HTMLTextAreaElement) {
    input.dataset.sfRichtextFallback = fallback ? 'true' : 'false';
    input.setAttribute('aria-hidden', fallback ? 'false' : 'true');
    input.tabIndex = fallback ? 0 : -1;
  }
};

const updateRichTextToolbar = (root, editor) => {
  const disabled = root.dataset.sfRichtextDisabled === 'true'
    || root.dataset.sfRichtextState !== 'ready';
  root.querySelectorAll('[data-sf-richtext-command]').forEach((button) => {
    if (!(button instanceof HTMLElement)) {
      return;
    }

    if (button instanceof HTMLButtonElement) {
      button.disabled = disabled;
    }
    button.setAttribute('aria-disabled', disabled ? 'true' : 'false');

    const command = button.dataset.sfRichtextCommand;
    const active = command === 'bold'
      ? editor.isActive('bold')
      : command === 'italic'
        ? editor.isActive('italic')
        : command === 'heading-2'
          ? editor.isActive('heading', { level: 2 })
          : command === 'heading-3'
            ? editor.isActive('heading', { level: 3 })
            : command === 'bullet-list'
              ? editor.isActive('bulletList')
              : command === 'ordered-list'
                ? editor.isActive('orderedList')
                : false;

    button.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
};

const updateRichTextInteractivity = (root, editor) => {
  const disabled = root.dataset.sfRichtextDisabled === 'true';
  const ready = root.dataset.sfRichtextState === 'ready';
  const toolbar = root.querySelector('[role="toolbar"]');
  const editorElement = root.querySelector('[data-sf-richtext-editor]');
  const input = root.querySelector('[data-sf-richtext-input]');

  if (toolbar instanceof HTMLElement) {
    toolbar.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }
  if (input instanceof HTMLTextAreaElement) {
    input.disabled = disabled;
  }
  if (editorElement instanceof HTMLElement) {
    editorElement.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    editorElement.tabIndex = disabled || !ready ? -1 : 0;
  }
  if (editor && editor.isEditable !== !disabled) {
    editor.setEditable(!disabled);
  }

  if (editor) {
    updateRichTextToolbar(root, editor);
  }
};

const bindRichTextRetry = (root) => {
  const retry = root.querySelector('[data-sf-richtext-retry]');
  if (!(retry instanceof HTMLButtonElement) || richTextRetryControllers.has(root)) {
    return;
  }

  const controller = new AbortController();
  retry.addEventListener('click', async () => {
    retry.disabled = true;
    richTextModulePromise = null;
    try {
      await initRichTextEditor(root, { forceReload: true });
    } finally {
      retry.disabled = false;
    }
  }, { signal: controller.signal });
  richTextRetryControllers.set(root, controller);
};

const destroyRichTextEditorsWithin = (element) => {
  if (!(element instanceof Element)) {
    return;
  }

  const roots = [];
  if (element.matches('[data-sf-richtext]')) {
    roots.push(element);
  }
  element.querySelectorAll('[data-sf-richtext]').forEach((root) => roots.push(root));

  roots.forEach((root) => {
    const instance = richTextInstances.get(root);
    if (instance) {
      instance.editor.off('update', instance.syncInput);
      instance.editor.off('selectionUpdate', instance.syncSelection);
      instance.controller.abort();
      instance.editor.destroy();
      richTextInstances.delete(root);
    }
    const retryController = richTextRetryControllers.get(root);
    retryController?.abort();
    richTextRetryControllers.delete(root);
    delete root.dataset.sfRichtextLoading;
  });
};

const initRichTextEditor = async (root, { forceReload = false } = {}) => {
  if (!(root instanceof HTMLElement) || root.dataset.sfRichtextReadonly === 'true') {
    return;
  }

  const editorElement = root.querySelector('[data-sf-richtext-editor]');
  const input = root.querySelector('[data-sf-richtext-input]');
  if (!(editorElement instanceof HTMLElement) || !(input instanceof HTMLTextAreaElement)) {
    return;
  }

  const existing = richTextInstances.get(root);
  if (existing) {
    if (forceReload) {
      destroyRichTextEditorsWithin(root);
    } else {
      if (!existing.editor.isFocused && normalizeRichTextHtml(input.value) !== existing.syncedValue) {
        existing.editor.commands.setContent(input.value || '<p></p>', { emitUpdate: false });
        existing.syncedValue = normalizeRichTextHtml(input.value);
      }
      setRichTextStatus(root, 'ready');
      updateRichTextInteractivity(root, existing.editor);
      return;
    }
  }

  if (!forceReload && root.dataset.sfRichtextState === 'error') {
    bindRichTextRetry(root);
    return;
  }

  if (root.dataset.sfRichtextLoading === '1') {
    return;
  }

  bindRichTextRetry(root);
  root.dataset.sfRichtextLoading = '1';
  setRichTextStatus(root, 'loading', 'Loading rich text editor…');
  updateRichTextInteractivity(root);
  let modules;
  try {
    modules = await loadRichTextModules();
  } catch {
    delete root.dataset.sfRichtextLoading;
    setRichTextStatus(root, 'error', 'The rich text editor could not be loaded. You can use the text area or retry.');
    updateRichTextInteractivity(root);
    return;
  }

  if (!root.isConnected) {
    delete root.dataset.sfRichtextLoading;
    return;
  }

  delete root.dataset.sfRichtextLoading;
  const { Editor, StarterKit } = modules;
  let editor;
  try {
    editor = new Editor({
      element: editorElement,
      extensions: [StarterKit.configure({
        blockquote: false,
        code: false,
        codeBlock: false,
        heading: { levels: [2, 3] },
        horizontalRule: false,
        link: {
          autolink: false,
          linkOnPaste: false,
          openOnClick: false,
        },
        strike: false,
        underline: false,
      })],
      content: input.value || '<p></p>',
      editable: root.dataset.sfRichtextDisabled !== 'true',
    });
  } catch {
    setRichTextStatus(root, 'error', 'The rich text editor could not be started. You can use the text area or retry.');
    updateRichTextInteractivity(root);
    return;
  }

  const controller = new AbortController();
  const instance = { editor, input, controller, syncedValue: normalizeRichTextHtml(input.value) };
  richTextInstances.set(root, instance);

  const syncInput = () => {
    const html = normalizeRichTextHtml(editor.getHTML());
    if (input.value !== html) {
      input.value = html;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    instance.syncedValue = html;
    updateRichTextToolbar(root, editor);
  };
  const syncSelection = () => updateRichTextToolbar(root, editor);
  instance.syncInput = syncInput;
  instance.syncSelection = syncSelection;

  editor.on('update', syncInput);
  editor.on('selectionUpdate', syncSelection);

  const form = root.closest('form');
  form?.addEventListener('submit', syncInput, { capture: true, signal: controller.signal });

  root.querySelectorAll('[data-sf-richtext-command]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    button.addEventListener('mousedown', (event) => event.preventDefault(), { signal: controller.signal });
    button.addEventListener('click', () => {
      if (button.disabled) {
        return;
      }
      const command = button.dataset.sfRichtextCommand;
      const chain = editor.chain().focus();

      switch (command) {
        case 'paragraph':
          chain.setParagraph().run();
          break;
        case 'heading-2':
          chain.toggleHeading({ level: 2 }).run();
          break;
        case 'heading-3':
          chain.toggleHeading({ level: 3 }).run();
          break;
        case 'bold':
          chain.toggleBold().run();
          break;
        case 'italic':
          chain.toggleItalic().run();
          break;
        case 'bullet-list':
          chain.toggleBulletList().run();
          break;
        case 'ordered-list':
          chain.toggleOrderedList().run();
          break;
        case 'link': {
          if (editor.isActive('link')) {
            chain.unsetLink().run();
            break;
          }

          const url = window.prompt('Enter a safe link URL');
          if (url && isSafeRichTextUrl(url)) {
            chain.setLink({ href: url.trim() }).run();
          }
          break;
        }
        default:
          break;
      }
    }, { signal: controller.signal });
  });

  setRichTextStatus(root, 'ready');
  updateRichTextInteractivity(root, editor);
};

const initRichTextEditors = () => {
  document.querySelectorAll('[data-sf-richtext]').forEach((root) => initRichTextEditor(root));
};

const focusableSelector = [
  'a[href]',
  'area[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  'iframe',
  '[contenteditable="true"]',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

const getFocusableElements = (container) => Array.from(container.querySelectorAll(focusableSelector))
  .filter((element) => element instanceof HTMLElement
    && !element.hidden
    && element.getAttribute('aria-hidden') !== 'true'
    && !element.closest('[inert]'));

const getOverlay = () => document.querySelector('[data-sf-overlay]');

const setOverlayVisible = (visible) => {
  const overlay = getOverlay();
  if (!(overlay instanceof HTMLElement)) {
    return;
  }

  overlay.hidden = !visible;
  overlay.classList.toggle('sf-backdrop-visible', visible);
  document.body.classList.toggle('sf-overlay-open', visible);
};

const syncDialogTriggers = (dialog, expanded) => {
  if (!(dialog instanceof HTMLElement) || !dialog.id) {
    return;
  }

  document.querySelectorAll('[data-sf-drawer-target], [data-sf-modal-target]').forEach((trigger) => {
    if (trigger instanceof HTMLElement
      && (trigger.dataset.sfDrawerTarget === dialog.id || trigger.dataset.sfModalTarget === dialog.id)) {
      trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  });
};

const findDialogTrigger = (dialogId) => {
  const triggers = document.querySelectorAll('[data-sf-drawer-target], [data-sf-modal-target]');
  for (const trigger of triggers) {
    if (trigger instanceof HTMLElement && (trigger.dataset.sfDrawerTarget === dialogId || trigger.dataset.sfModalTarget === dialogId)) {
      return trigger;
    }
  }

  return null;
};

const clearInertBackground = () => {
  inertBackgroundNodes.forEach((previous, node) => {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    if (previous.hadInertAttribute) {
      node.setAttribute('inert', '');
    } else {
      node.removeAttribute('inert');
    }

    if ('inert' in node) {
      node.inert = previous.inert;
    }
  });
  inertBackgroundNodes.clear();
};

const inertDialogBackground = (dialog) => {
  clearInertBackground();

  let current = dialog;
  while (current instanceof HTMLElement && current !== document.body) {
    const parent = current.parentElement;
    if (!(parent instanceof HTMLElement)) {
      break;
    }

    Array.from(parent.children).forEach((sibling) => {
      if (!(sibling instanceof HTMLElement) || sibling === current || sibling.matches('[data-sf-overlay]')) {
        return;
      }

      inertBackgroundNodes.set(sibling, {
        hadInertAttribute: sibling.hasAttribute('inert'),
        inert: 'inert' in sibling ? sibling.inert : false,
      });
      sibling.setAttribute('inert', '');
      if ('inert' in sibling) {
        sibling.inert = true;
      }
    });

    current = parent;
  }
};

const restoreDialogFocus = () => {
  if (activeDialogTrigger instanceof HTMLElement && document.contains(activeDialogTrigger) && !activeDialogTrigger.matches('[disabled]')) {
    const menu = activeDialogTrigger.closest('[data-sf-toolbar-menu]');
    const focusTarget = menu instanceof HTMLDetailsElement && !menu.open
      ? menu.querySelector('summary')
      : activeDialogTrigger;
    focusTarget?.focus();
  }
};

const closeActiveDialog = (restoreFocus = true) => {
  const dialog = activeDialogId ? document.getElementById(activeDialogId) : null;
  if (dialog instanceof HTMLElement) {
    dialog.hidden = true;
    dialog.setAttribute('aria-hidden', 'true');
    syncDialogTriggers(dialog, false);
  }

  clearInertBackground();
  activeDialogId = null;
  pendingDialogId = null;
  if (restoreFocus) {
    restoreDialogFocus();
  }
  activeDialogTrigger = null;

  if (!document.body.classList.contains('sf-mobile-nav-open')) {
    setOverlayVisible(false);
  }
};

const openDialog = (dialog, trigger = null, focus = true) => {
  if (!(dialog instanceof HTMLElement) || !dialog.id) {
    return;
  }

  if (activeDialogId !== null && activeDialogId !== dialog.id) {
    closeActiveDialog(false);
  }

  activeDialogId = dialog.id;
  activeDialogTrigger = trigger instanceof HTMLElement
    ? trigger
    : (activeDialogTrigger instanceof HTMLElement ? activeDialogTrigger : findDialogTrigger(dialog.id));
  pendingDialogId = null;
  dialog.hidden = false;
  dialog.setAttribute('aria-hidden', 'false');
  syncDialogTriggers(dialog, true);
  inertDialogBackground(dialog);
  setOverlayVisible(true);

  if (!focus) {
    return;
  }

  const focusable = getFocusableElements(dialog);
  const target = focusable[0];
  if (target instanceof HTMLElement) {
    target.focus();
  } else {
    dialog.setAttribute('tabindex', '-1');
    dialog.focus();
  }
};

const refreshDialogState = () => {
  const visibleDialog = document.querySelector('[data-sf-modal][aria-hidden="false"], [data-sf-drawer][aria-hidden="false"]');
  const dialogId = activeDialogId ?? pendingDialogId ?? visibleDialog?.id ?? null;
  if (dialogId === null) {
    return;
  }

  const dialog = document.getElementById(dialogId);
  if (!(dialog instanceof HTMLElement)) {
    if (activeDialogId !== null) {
      closeActiveDialog();
    }

    return;
  }

  const trigger = activeDialogTrigger instanceof HTMLElement && document.contains(activeDialogTrigger)
    ? activeDialogTrigger
    : findDialogTrigger(dialogId);
  openDialog(dialog, trigger, activeDialogId === null);
};

const closeMobileNavigationFromController = () => {
  document.body.classList.remove('sf-mobile-nav-open');
  const mobileMenu = document.querySelector('[data-sf-mobile-menu]');
  if (mobileMenu instanceof HTMLElement) {
    mobileMenu.setAttribute('aria-expanded', 'false');
    mobileMenu.setAttribute('aria-label', mobileMenu.dataset.sfLabelCollapsed ?? 'Open navigation');
  }
  if (activeDialogId === null) {
    setOverlayVisible(false);
  }
};

const registerOverlayController = () => {
  if (!overlayControllerRegistered) {
    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const dialogTrigger = event.target.closest('[data-sf-drawer-target], [data-sf-modal-target]');
      if (dialogTrigger instanceof HTMLElement) {
        const dialogId = dialogTrigger.dataset.sfDrawerTarget ?? dialogTrigger.dataset.sfModalTarget ?? '';
        if (dialogId !== '') {
          pendingDialogId = dialogId;
          openDialog(document.getElementById(dialogId), dialogTrigger);
        }
        return;
      }

      if (event.target.closest('[data-sf-drawer-close], [data-sf-modal-close]')) {
        closeActiveDialog();
        return;
      }

      if (event.target.closest('[data-sf-overlay]')) {
        closeActiveDialog();
        closeMobileNavigationFromController();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        if (activeDialogId !== null) {
          event.preventDefault();
          closeActiveDialog();
          return;
        }

        if (document.body.classList.contains('sf-mobile-nav-open')) {
          event.preventDefault();
          closeMobileNavigationFromController();
        }

        return;
      }

      if (event.key !== 'Tab' || activeDialogId === null) {
        return;
      }

      const dialog = document.getElementById(activeDialogId);
      if (!(dialog instanceof HTMLElement)) {
        return;
      }

      const focusable = getFocusableElements(dialog);
      if (focusable.length === 0) {
        event.preventDefault();
        dialog.focus();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
        event.preventDefault();
        first.focus();
      }
    });
    overlayControllerRegistered = true;
  }

  if (!overlayLivewireHookRegistered
    && typeof window.Livewire !== 'undefined'
    && typeof window.Livewire.hook === 'function') {
    window.Livewire.hook('morphed', () => {
      window.requestAnimationFrame(refreshDialogState);
    });
    overlayLivewireHookRegistered = true;
  }
};

const scheduleDashboardCharts = () => {
  window.requestAnimationFrame(() => initDashboardCharts());
};

const initDashboardCharts = () => {
  document.querySelectorAll('[data-sf-chart="1"]').forEach((canvas) => {
    if (!(canvas instanceof HTMLCanvasElement)) {
      return;
    }

    const type = canvas.dataset.chartType ?? 'bar';
    const dataRaw = canvas.dataset.chartData ?? '{}';
    const signature = `${type}:${dataRaw}`;
    const existingState = dashboardChartState.get(canvas);
    const existingChart = existingState?.chart;

    if (existingState?.signature === signature && existingChart) {
      return;
    }

    if (existingChart) {
      existingChart.destroy();
    }

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

    const chart = new Chart(canvas, {
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

    dashboardChartState.set(canvas, { chart, signature });
    canvas.dataset.sfChartInitialized = '1';
    canvas.dataset.sfChartSignature = signature;
  });
};

const registerLivewireChartHooks = () => {
  if (livewireHooksRegistered || typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
    return;
  }

  window.Livewire.hook('morphed', () => {
    scheduleDashboardCharts();
    initTableDensity();
  });
  window.Livewire.hook('morph.added', scheduleDashboardCharts);
  livewireHooksRegistered = true;
};

const registerDashboardChartObserver = () => {
  if (dashboardChartObserverRegistered || typeof window.MutationObserver === 'undefined' || !(document.body instanceof HTMLElement)) {
    return;
  }

  const observer = new MutationObserver((mutations) => {
    const hasNewChart = mutations.some((mutation) => Array.from(mutation.addedNodes).some((node) => {
      if (!(node instanceof HTMLElement)) {
        return false;
      }

      return node.matches('[data-sf-chart="1"]') || node.querySelector('[data-sf-chart="1"]') !== null;
    }));

    if (hasNewChart) {
      scheduleDashboardCharts();
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });
  dashboardChartObserverRegistered = true;
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
      mobileMenu.setAttribute('aria-label', mobileMenu.dataset.sfLabelCollapsed ?? 'Open navigation');
    }
    closeOverlayIfIdle();
  };

  const closeOpenDrawers = () => {
    if (activeDialogId !== null) {
      closeActiveDialog();

      return;
    }

    document.querySelectorAll('[data-sf-drawer][aria-hidden="false"]').forEach((drawer) => {
      if (drawer instanceof HTMLElement) {
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
        syncDialogTriggers(drawer, false);
      }
    });
    document.querySelectorAll('[data-sf-modal][aria-hidden="false"]').forEach((modal) => {
      if (modal instanceof HTMLElement) {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        syncDialogTriggers(modal, false);
      }
    });
    clearInertBackground();
  };

  const setSidebarCollapsed = (collapsed, persist = true) => {
    if (window.matchMedia('(max-width: 1023px)').matches) {
      body.classList.remove('sf-sidebar-collapsed');
      if (sidebarToggle instanceof HTMLElement) {
        sidebarToggle.setAttribute('aria-expanded', 'true');
        sidebarToggle.setAttribute('aria-label', sidebarToggle.dataset.sfLabelExpanded ?? 'Collapse navigation');
      }

      return;
    }

    body.classList.toggle('sf-sidebar-collapsed', collapsed);
    if (sidebarToggle instanceof HTMLElement) {
      sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      sidebarToggle.setAttribute('aria-label', collapsed
        ? (sidebarToggle.dataset.sfLabelCollapsed ?? 'Expand navigation')
        : (sidebarToggle.dataset.sfLabelExpanded ?? 'Collapse navigation'));
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

    if (activeDialogId === commandPalette.id) {
      closeActiveDialog();
    } else {
      commandPalette.hidden = true;
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

    openDialog(commandPalette, commandOpen);
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
      mobileMenu.setAttribute('aria-label', mobileMenu.dataset.sfLabelExpanded ?? 'Close navigation');
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
  refreshDialogState();
};

const initTableDensity = () => {
  const body = document.body;
  const preferredDensity = getStoredPreference(tableDensityPreferenceKey) || 'comfortable';
  body.dataset.sfTableDensity = preferredDensity === 'compact' ? 'compact' : 'comfortable';

  document.querySelectorAll('[data-sf-density]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    button.setAttribute('aria-pressed', body.dataset.sfTableDensity === button.dataset.sfDensity ? 'true' : 'false');
    if (button.dataset.sfDensityInitialized === '1') {
      return;
    }

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
  });
};

const syncPartialTableSelection = () => {
  document.querySelectorAll('[data-sf-partial-selection]').forEach((input) => {
    if (!(input instanceof HTMLInputElement) || input.type !== 'checkbox') {
      return;
    }

    input.indeterminate = input.dataset.sfPartialSelection === 'true';
  });

  if (!tableSelectionLivewireHookRegistered
    && typeof window.Livewire !== 'undefined'
    && typeof window.Livewire.hook === 'function') {
    window.Livewire.hook('morphed', syncPartialTableSelection);
    tableSelectionLivewireHookRegistered = true;
  }
};

const openFormValidationTarget = (target) => {
  if (!(target instanceof HTMLElement)) {
    return;
  }

  let focusTarget = target;
  const richTextRoot = target.closest('[data-sf-richtext]');
  if (richTextRoot instanceof HTMLElement) {
    const fallback = richTextRoot.querySelector('[data-sf-richtext-input]');
    const editor = richTextRoot.querySelector('[data-sf-richtext-editor]');
    if (richTextRoot.dataset.sfRichtextState === 'error'
      && fallback instanceof HTMLTextAreaElement
      && !fallback.disabled) {
      focusTarget = fallback;
    } else if (editor instanceof HTMLElement
      && !editor.hidden
      && editor.getAttribute('aria-disabled') !== 'true') {
      focusTarget = editor;
    }
  }

  const panel = focusTarget.closest('[data-sf-tab-panel]');
  if (panel instanceof HTMLElement) {
    const tab = panel.dataset.sfTabPanel;
    document.querySelectorAll('[data-sf-tab-panel]').forEach((candidate) => {
      if (candidate instanceof HTMLElement) {
        candidate.hidden = candidate !== panel;
      }
    });
    document.querySelectorAll('[data-sf-tab-trigger]').forEach((trigger) => {
      if (trigger instanceof HTMLElement) {
        trigger.setAttribute('aria-selected', trigger.dataset.sfTabTrigger === tab ? 'true' : 'false');
      }
    });
  }

  window.requestAnimationFrame(() => {
    focusTarget.focus({ preventScroll: false });
  });
};

const initComboboxUi = () => {
  document.querySelectorAll('[data-sf-combobox]').forEach((root) => {
    if (!(root instanceof HTMLElement)) {
      return;
    }

    const input = root.querySelector('[role="combobox"]');
    const options = root.querySelector('[role="listbox"]');
    if (!(input instanceof HTMLInputElement) || !(options instanceof HTMLElement)) {
      return;
    }

    const syncSearchResponse = () => {
      if (root.dataset.sfComboboxPending !== '1') {
        return;
      }

      const serverQuery = root.dataset.sfComboboxQuery ?? '';
      if (serverQuery === input.value) {
        delete root.dataset.sfComboboxPending;
        options.hidden = false;
      } else {
        options.hidden = true;
      }
    };

    if (root.dataset.sfComboboxInitialized !== '1') {
      input.addEventListener('input', () => {
        root.dataset.sfComboboxPending = '1';
        options.hidden = true;
      });
      input.addEventListener('keydown', (event) => {
        const optionButtons = Array.from(options.querySelectorAll('button:not([disabled])'));
        if (event.key === 'ArrowDown' && optionButtons[0] instanceof HTMLElement) {
          event.preventDefault();
          optionButtons[0].focus();
        }
      });
      options.addEventListener('keydown', (event) => {
        const optionButtons = Array.from(options.querySelectorAll('button:not([disabled])'));
        const currentIndex = optionButtons.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' && currentIndex < optionButtons.length - 1) {
          event.preventDefault();
          optionButtons[currentIndex + 1].focus();
        } else if (event.key === 'ArrowUp' && currentIndex > 0) {
          event.preventDefault();
          optionButtons[currentIndex - 1].focus();
        } else if (event.key === 'Escape') {
          event.preventDefault();
          input.focus();
        }
      });
      root.dataset.sfComboboxInitialized = '1';
    }

    syncSearchResponse();
  });
};

const initUploadProgress = () => {
  document.querySelectorAll('input[type="file"][data-sf-upload-initialized], input[type="file"]')
    .forEach((input) => {
      if (!(input instanceof HTMLInputElement) || input.dataset.sfUploadInitialized === '1') {
        return;
      }

      const container = input.closest('.sf-field');
      const progress = container?.querySelector('[data-sf-upload-progress]');
      if (!(progress instanceof HTMLProgressElement)) {
        return;
      }

      input.addEventListener('livewire-upload-start', () => {
        progress.hidden = false;
        progress.value = 0;
      });
      input.addEventListener('livewire-upload-progress', (event) => {
        const value = Number(event.detail?.progress ?? 0);
        progress.hidden = false;
        progress.value = Number.isFinite(value) ? value : 0;
      });
      input.addEventListener('livewire-upload-finish', () => {
        progress.value = 100;
        window.setTimeout(() => { progress.hidden = true; }, 250);
      });
      input.addEventListener('livewire-upload-error', () => {
        progress.hidden = true;
      });
      progress.hidden = true;
      input.dataset.sfUploadInitialized = '1';
    });
};

const initFormUi = () => {
  initComboboxUi();
  initUploadProgress();
  initRichTextEditors();

  document.querySelectorAll('[data-sf-resource-form]').forEach((formRoot) => {
    if (!(formRoot instanceof HTMLElement) || formRoot.dataset.sfFormInitialized === '1') {
      return;
    }

    formRoot.querySelectorAll('[data-sf-validation-target]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const targetId = link.dataset.sfValidationTarget;
        const target = targetId ? document.getElementById(targetId) : null;
        openFormValidationTarget(target);
      });
    });

    formRoot.querySelector('form')?.addEventListener('submit', () => {
      formRoot.dataset.sfFormSubmitting = 'true';
    });

    formRoot.dataset.sfFormInitialized = '1';
  });

  const summaryTarget = document.querySelector('[data-sf-validation-summary] [data-sf-validation-target]');
  if (summaryTarget instanceof HTMLElement && summaryTarget.dataset.sfValidationHandled !== '1') {
    summaryTarget.dataset.sfValidationHandled = '1';
    const targetId = summaryTarget.dataset.sfValidationTarget;
    const target = targetId ? document.getElementById(targetId) : null;
    openFormValidationTarget(target);
  }
};

const registerFormUi = () => {
  if (!formUiRegistered) {
    window.addEventListener('beforeunload', (event) => {
      const formRoot = document.querySelector('[data-sf-resource-form][data-sf-form-dirty="true"]');
      if (!(formRoot instanceof HTMLElement)
        || formRoot.dataset.sfFormSubmitting === 'true'
        || formRoot.dataset.sfFormExiting === 'true') {
        return;
      }

      event.preventDefault();
      event.returnValue = '';
    });

    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const exit = event.target.closest('[data-sf-form-exit]');
      const formRoot = exit?.closest('[data-sf-resource-form]');
      if (!(exit instanceof HTMLElement) || !(formRoot instanceof HTMLElement)) {
        return;
      }

      if (formRoot.dataset.sfFormDirty !== 'true') {
        return;
      }

      if (!window.confirm('Leave this form? Unsaved changes will be lost.')) {
        event.preventDefault();

        return;
      }

      formRoot.dataset.sfFormExiting = 'true';
    });

    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)
        || event.defaultPrevented
        || event.button !== 0
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey) {
        return;
      }

      const link = event.target.closest('a[href]');
      const formRoot = document.querySelector('[data-sf-resource-form][data-sf-form-dirty="true"]');
      if (!(link instanceof HTMLAnchorElement) || !(formRoot instanceof HTMLElement)
        || link.matches('[data-sf-form-exit], [data-sf-validation-target]')
        || link.hasAttribute('download')
        || (link.target !== '' && link.target !== '_self')
        || link.href === window.location.href
        || formRoot.dataset.sfFormDirty !== 'true') {
        return;
      }

      if (!window.confirm('Leave this form? Unsaved changes will be lost.')) {
        event.preventDefault();

        return;
      }

      formRoot.dataset.sfFormExiting = 'true';
    });

    document.addEventListener('livewire:navigating', (event) => {
      const formRoot = document.querySelector('[data-sf-resource-form][data-sf-form-dirty="true"]');
      if (!(formRoot instanceof HTMLElement)) {
        return;
      }

      if (!window.confirm('Leave this form? Unsaved changes will be lost.')) {
        event.preventDefault();

        return;
      }

      formRoot.dataset.sfFormExiting = 'true';
    });

    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const trigger = event.target.closest('[data-sf-validation-target]');
      if (!(trigger instanceof HTMLElement)) {
        return;
      }

      const targetId = trigger.dataset.sfValidationTarget;
      const target = targetId ? document.getElementById(targetId) : null;
      if (target instanceof HTMLElement) {
        event.preventDefault();
        openFormValidationTarget(target);
      }
    });

    formUiRegistered = true;
  }

  if (!formLivewireHookRegistered
    && typeof window.Livewire !== 'undefined'
    && typeof window.Livewire.hook === 'function') {
    window.Livewire.hook('morph', () => {
      const active = document.activeElement;
      const richTextRoot = active instanceof HTMLElement
        ? active.closest('[data-sf-richtext]')
        : null;
      if (richTextRoot instanceof HTMLElement) {
        const editor = richTextRoot.querySelector('[data-sf-richtext-editor]');
        focusedFormControlId = editor instanceof HTMLElement ? editor.id : null;
      } else {
        focusedFormControlId = active instanceof HTMLElement
          && active.closest('[data-sf-resource-form]')
          ? active.id
          : null;
      }
    });
    window.Livewire.hook('morph.removing', ({ el }) => {
      destroyRichTextEditorsWithin(el);
    });
    window.Livewire.hook('morphed', () => {
      initFormUi();
      if (focusedFormControlId) {
      const control = document.getElementById(focusedFormControlId);
        if (control instanceof HTMLElement
          && !control.disabled
          && !control.hidden
          && control.getAttribute('aria-disabled') !== 'true') {
          window.requestAnimationFrame(() => control.focus());
        }
      }
      focusedFormControlId = null;
    });
    formLivewireHookRegistered = true;
  }

  if (!formFocusEventRegistered
    && typeof window.Livewire !== 'undefined'
    && typeof window.Livewire.on === 'function') {
    window.Livewire.on('seviaflow:focus-field', ({ fieldId }) => {
      if (typeof fieldId !== 'string' || fieldId === '') {
        return;
      }

      window.requestAnimationFrame(() => {
        const control = document.getElementById(fieldId);
        if (control instanceof HTMLElement
          && !control.disabled
          && !control.hidden
          && control.getAttribute('aria-disabled') !== 'true') {
          control.focus();
        }
      });
    });
    formFocusEventRegistered = true;
  }

  initFormUi();
};

const initToolbarMenus = () => {
  if (toolbarMenusInitialized) {
    return;
  }

  const openMenus = () => document.querySelectorAll('[data-sf-toolbar-menu][open]');
  const closeMenu = (menu, restoreFocus = false) => {
    menu.open = false;
    if (restoreFocus) {
      menu.querySelector('summary')?.focus();
    }
  };

  // Delegate events so menus added or replaced by Livewire work immediately.
  document.addEventListener('toggle', (event) => {
    const menu = event.target;
    if (!(menu instanceof HTMLDetailsElement) || !menu.matches('[data-sf-toolbar-menu]') || !menu.open) {
      return;
    }
    openMenus().forEach((other) => {
      if (other !== menu) {
        closeMenu(other);
      }
    });
  }, true);

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }
    const action = event.target.closest('[data-sf-menu-close]');
    openMenus().forEach((menu) => {
      if (!menu.contains(event.target)) {
        closeMenu(menu);
      } else if (action) {
        closeMenu(menu, !action.matches('[data-sf-drawer-target], [data-sf-modal-target]'));
      }
    });
  });

  document.addEventListener('focusin', (event) => {
    openMenus().forEach((menu) => {
      if (!menu.contains(event.target)) {
        closeMenu(menu);
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && openMenus().length > 0) {
      event.preventDefault();
      openMenus().forEach((menu) => closeMenu(menu, true));
    }
  });

  toolbarMenusInitialized = true;
};

const initSeviaFlowUi = () => {
  registerOverlayController();
  registerLivewireChartHooks();
  registerDashboardChartObserver();
  initDashboardCharts();
  initDashboardLayoutEditor();
  initShell();
  initDrawers();
  initTableDensity();
  initToolbarMenus();
  syncPartialTableSelection();
  registerFormUi();
};

document.addEventListener('DOMContentLoaded', initSeviaFlowUi);
document.addEventListener('livewire:initialized', initSeviaFlowUi);
document.addEventListener('livewire:navigated', initSeviaFlowUi);
