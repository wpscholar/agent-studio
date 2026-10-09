import { __ } from '@wordpress/i18n';

/** What each agent status is called, its colour, and what it means. */
export const STATUSES = {
  active: {
    label: () => __('Active', 'agent-studio'),
    tone: 'success',
    help: () =>
      __('Running whenever one of its triggers happens.', 'agent-studio'),
  },
  paused: {
    label: () => __('Paused', 'agent-studio'),
    tone: 'warning',
    help: () =>
      __(
        'Switched off for now. Its triggers are ignored until you switch it back on. You can still run it by hand.',
        'agent-studio',
      ),
  },
  draft: {
    label: () => __('Draft', 'agent-studio'),
    tone: 'neutral',
    help: () =>
      __(
        'Still being set up. It never runs on its own, but you can run it by hand to try it.',
        'agent-studio',
      ),
  },
};

export function statusOf(value) {
  return STATUSES[value] || STATUSES.draft;
}

/** Colours for run statuses. */
export function runTone(status) {
  switch (status) {
    case 'completed':
      return 'success';
    case 'failed':
      return 'error';
    case 'awaiting_approval':
      return 'warning';
    default:
      return 'neutral';
  }
}
