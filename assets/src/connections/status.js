import { __, _n, sprintf } from '@wordpress/i18n';

/** How a connection's state reads to people, and its colour. */
export function connectionStatus(connection) {
  switch (connection.status) {
    case 'connected':
      return {
        tone: 'success',
        label: sprintf(
          /* translators: %d: number of tools */
          _n(
            'Connected · %d tool',
            'Connected · %d tools',
            connection.toolCount,
            'agent-studio',
          ),
          connection.toolCount,
        ),
      };
    case 'needs_sign_in':
      return { tone: 'warning', label: __('Needs sign-in', 'agent-studio') };
    case 'problem':
      return { tone: 'error', label: __('Problem', 'agent-studio') };
    default:
      return { tone: 'neutral', label: __('Not checked yet', 'agent-studio') };
  }
}

export function hostOf(url) {
  try {
    return new URL(url).host;
  } catch {
    return url;
  }
}
