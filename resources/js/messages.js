// Defaults keep previously published application views usable after an asset update.
// Current views supply these values through Laravel translations on each element.
const messages = {
  formLeave: ['sfFormLeaveMessage', 'Leave this form? Unsaved changes will be lost.'],
  richTextLoading: ['sfRichtextLoadingMessage', 'Loading rich text editor…'],
  richTextLoadError: ['sfRichtextLoadError', 'The rich text editor could not be loaded. You can use the text area or retry.'],
  richTextStartError: ['sfRichtextStartError', 'The rich text editor could not be started. You can use the text area or retry.'],
  richTextLinkPrompt: ['sfRichtextLinkPrompt', 'Enter a safe link URL'],
};

export const uiMessage = (root, name) => {
  const [attribute, fallback] = messages[name];

  return root.dataset[attribute] ?? fallback;
};

export const confirmFormExit = (root, event, confirm = (message) => window.confirm(message)) => {
  if (!confirm(uiMessage(root, 'formLeave'))) {
    event.preventDefault();

    return false;
  }

  root.dataset.sfFormExiting = 'true';

  return true;
};
