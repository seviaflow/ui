import test from 'node:test';
import assert from 'node:assert/strict';
import { confirmFormExit, uiMessage } from '../../resources/js/messages.js';

for (const [locale, leave, loadError] of [
  ['en', 'Leave this form? Unsaved changes will be lost.', 'The editor could not be loaded.'],
  ['pl', 'Opuścić formularz? Niezapisane zmiany zostaną utracone.', 'Nie udało się wczytać edytora.'],
]) {
  test(`${locale}: cancelling navigation keeps unsaved form changes`, () => {
    const root = { dataset: { sfFormLeaveMessage: leave, sfFormDirty: 'true' } };
    let prevented = false;
    const event = { preventDefault() { prevented = true; } };

    assert.equal(confirmFormExit(root, event, (message) => {
      assert.equal(message, leave);
      return false;
    }), false);
    assert.equal(prevented, true);
    assert.equal(root.dataset.sfFormDirty, 'true');
    assert.equal(root.dataset.sfFormExiting, undefined);

    assert.equal(confirmFormExit(root, event, () => true), true);
    assert.equal(root.dataset.sfFormExiting, 'true');
  });

  test(`${locale}: editor messages use current element translations`, () => {
    const root = { dataset: { sfRichtextLoadError: loadError } };
    assert.equal(uiMessage(root, 'richTextLoadError'), loadError);
    root.dataset.sfRichtextLoadError = 'Changed after Livewire navigation';
    assert.equal(uiMessage(root, 'richTextLoadError'), 'Changed after Livewire navigation');
  });
}

test('previously published views retain English defaults', () => {
  assert.equal(uiMessage({ dataset: {} }, 'formLeave'), 'Leave this form? Unsaved changes will be lost.');
});
