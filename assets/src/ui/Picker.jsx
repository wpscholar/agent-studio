import { useEffect, useRef, useState } from '@wordpress/element';
import {
  Button,
  CheckboxControl,
  Modal,
  Notice,
  SearchControl,
  Spinner,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

const SEARCH_DELAY = 250;

/**
 * Choose from a list that may hold thousands of items: the server searches
 * and returns one page at a time, and "Show more" fetches the next.
 *
 * load({ search, filters, page }) resolves to { items, total, pages, ...extra }.
 * renderFilters(extra, filters, setFilter) draws filter controls from the
 * extra data the server sends (for example categories with counts).
 *
 * With multiple, items have checkboxes and the dialog stays open. Without it,
 * choosing an item calls onPick and closes.
 */
export default function Picker({
  title,
  description,
  searchLabel,
  load,
  itemKey,
  renderItem,
  renderFilters,
  selected = [],
  multiple = false,
  onToggle,
  onPick,
  onClose,
  emptyText,
}) {
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState({});
  const [result, setResult] = useState(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const request = useRef(0);

  // A new search or filter starts again at the first page.
  useEffect(() => {
    const id = ++request.current;
    setLoading(true);
    const timer = setTimeout(
      () => {
        load({ search, filters, page: 1 })
          .then((data) => {
            if (id === request.current) {
              setResult(data);
              setPage(1);
              setError('');
            }
          })
          .catch((e) => id === request.current && setError(e.message))
          .finally(() => id === request.current && setLoading(false));
      },
      search ? SEARCH_DELAY : 0,
    );
    return () => clearTimeout(timer);
    // load is recreated on every render by callers; the inputs are what matter.
  }, [search, filters]);

  const more = () => {
    const id = ++request.current;
    const next = page + 1;
    setLoading(true);
    load({ search, filters, page: next })
      .then((data) => {
        if (id === request.current) {
          setResult({ ...data, items: [...result.items, ...data.items] });
          setPage(next);
        }
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  const setFilter = (key, value) => setFilters({ ...filters, [key]: value });
  const items = result ? result.items : [];
  const chosen = new Set(selected);

  return (
    <Modal
      title={title}
      onRequestClose={onClose}
      size="large"
      className="agent-studio-picker"
    >
      {description && (
        <p className="agent-studio-picker__intro">{description}</p>
      )}
      <div className="agent-studio-picker__controls">
        <SearchControl
          __nextHasNoMarginBottom
          label={searchLabel}
          placeholder={searchLabel}
          value={search}
          onChange={setSearch}
        />
        {result && renderFilters && (
          <div className="agent-studio-picker__filters">
            {renderFilters(result, filters, setFilter)}
          </div>
        )}
      </div>

      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      <p className="agent-studio-muted" aria-live="polite">
        {result &&
          sprintf(
            /* translators: 1: number shown, 2: number found */
            _n(
              'Showing %1$d of %2$d match',
              'Showing %1$d of %2$d matches',
              result.total,
              'agent-studio',
            ),
            items.length,
            result.total,
          )}
        {multiple &&
          selected.length > 0 &&
          ' · ' +
            sprintf(
              /* translators: %d: number of selected items */
              _n('%d chosen', '%d chosen', selected.length, 'agent-studio'),
              selected.length,
            )}
      </p>

      {result && items.length === 0 && !loading && (
        <p className="agent-studio-picker__empty">
          {emptyText || __('Nothing matches. Try other words.', 'agent-studio')}
        </p>
      )}

      {items.length > 0 && (
        <ul className="agent-studio-picker__list">
          {items.map((item) => {
            const key = itemKey(item);
            const isChosen = chosen.has(key);
            return (
              <li
                key={key}
                className={
                  'agent-studio-picker__item' + (isChosen ? ' is-chosen' : '')
                }
              >
                {multiple ? (
                  <CheckboxControl
                    __nextHasNoMarginBottom
                    checked={isChosen}
                    onChange={(checked) => onToggle(item, checked)}
                    label={renderItem(item)}
                  />
                ) : (
                  <button
                    type="button"
                    className="agent-studio-picker__choice"
                    disabled={isChosen}
                    onClick={() => {
                      onPick(item);
                      onClose();
                    }}
                  >
                    {renderItem(item)}
                    {isChosen && (
                      <span className="agent-studio-pill">
                        {__('Added', 'agent-studio')}
                      </span>
                    )}
                  </button>
                )}
              </li>
            );
          })}
        </ul>
      )}

      {loading && <Spinner />}

      <div className="agent-studio-modal-actions">
        {result && page < result.pages && !loading && (
          <Button variant="secondary" onClick={more}>
            {__('Show more', 'agent-studio')}
          </Button>
        )}
        <Button variant={multiple ? 'primary' : 'tertiary'} onClick={onClose}>
          {multiple ? __('Done', 'agent-studio') : __('Cancel', 'agent-studio')}
        </Button>
      </div>
    </Modal>
  );
}

/** Serves pages from a list already in the browser, for lists that stay short. */
export function pageLocally(list, { search, page }, perPage = 30) {
  const term = (search || '').trim().toLowerCase();
  const matches = list.filter(
    (item) =>
      term === '' ||
      Object.values(item).some(
        (value) =>
          typeof value === 'string' && value.toLowerCase().includes(term),
      ),
  );
  return Promise.resolve({
    items: matches.slice((page - 1) * perPage, page * perPage),
    total: matches.length,
    pages: Math.max(1, Math.ceil(matches.length / perPage)),
  });
}
