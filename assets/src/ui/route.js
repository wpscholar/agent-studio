import { useEffect, useState } from '@wordpress/element';

/**
 * One query-string value that drives which view a screen shows, kept in sync
 * with the address bar so the back button and bookmarks work.
 */
export function useQueryParam(name) {
  const read = () =>
    new URLSearchParams(window.location.search).get(name) || '';
  const [value, setValue] = useState(read);

  useEffect(() => {
    const onPop = () => setValue(read());
    window.addEventListener('popstate', onPop);
    return () => window.removeEventListener('popstate', onPop);
  });

  const navigate = (next) => {
    const url = new URL(window.location.href);
    if (next) {
      url.searchParams.set(name, next);
    } else {
      url.searchParams.delete(name);
    }
    window.history.pushState({}, '', url);
    setValue(next || '');
    window.scrollTo(0, 0);
  };

  return [value, navigate];
}
