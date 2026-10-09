import { __ } from '@wordpress/i18n';

/** "3 days ago" from a Unix timestamp in seconds. */
export function timeAgo(seconds) {
  if (!seconds) {
    return '';
  }
  const diff = seconds - Date.now() / 1000;
  const units = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ];
  const format = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  for (const [unit, size] of units) {
    if (Math.abs(diff) >= size) {
      return format.format(Math.round(diff / size), unit);
    }
  }
  return __('just now', 'agent-studio');
}

/** A full local date and time from a Unix timestamp in seconds. */
export function dateTime(seconds) {
  return new Date(seconds * 1000).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

/** Saves text as a file on the person's computer. */
export function download(filename, text) {
  const url = URL.createObjectURL(new Blob([text], { type: 'text/markdown' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
