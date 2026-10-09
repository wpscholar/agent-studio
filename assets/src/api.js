import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Calls the plugin's REST API. Errors come back as an Error whose message is
 * ready to show to the person.
 */
export function api(path, options = {}) {
  return apiFetch({ path: '/agent-studio/v1/' + path, ...options }).catch(
    (error) => {
      const failure = new Error(
        (error && error.message) ||
          __('Something went wrong. Please try again.', 'agent-studio'),
      );
      failure.code = (error && error.code) || '';
      failure.data = (error && error.data) || {};
      throw failure;
    },
  );
}

export const config = window.agentStudioAdmin || { urls: {}, can: {} };
