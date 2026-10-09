import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import Page from '../ui/Page.jsx';
import Readiness from '../ui/Readiness.jsx';
import RiskBadge from '../ui/RiskBadge.jsx';
import { config } from '../api.js';
import {
  AbilityFilters,
  searchAbilities,
} from '../agents/AbilitiesSection.jsx';

const SEARCH_DELAY = 250;

function usedByText(count) {
  return count > 0
    ? sprintf(
        /* translators: %d: number of agents */
        _n('Used by %d agent', 'Used by %d agents', count, 'agent-studio'),
        count,
      )
    : __('Not used by any agent', 'agent-studio');
}

function sourceText(item) {
  return item.source === 'connection'
    ? sprintf(
        /* translators: %s: name of a connected service */
        __('From the connected service “%s”', 'agent-studio'),
        item.categoryLabel,
      )
    : __('From this site', 'agent-studio');
}

/**
 * Agents → Abilities: whether this site can run agents, and every ability
 * agents can be given, searched and filtered on the server so it stays quick
 * with thousands of them.
 */
export default function AbilitiesScreen() {
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState({});
  const [result, setResult] = useState(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [open, setOpen] = useState('');
  const request = useRef(0);

  useEffect(() => {
    const id = ++request.current;
    setLoading(true);
    const timer = setTimeout(
      () =>
        searchAbilities({ search, filters, page: 1 })
          .then((data) => {
            if (id === request.current) {
              setResult(data);
              setPage(1);
              setError('');
            }
          })
          .catch((e) => id === request.current && setError(e.message))
          .finally(() => id === request.current && setLoading(false)),
      search ? SEARCH_DELAY : 0,
    );
    return () => clearTimeout(timer);
  }, [search, filters]);

  const more = () => {
    const id = ++request.current;
    setLoading(true);
    searchAbilities({ search, filters, page: page + 1 })
      .then((data) => {
        if (id === request.current) {
          setResult({ ...data, items: [...result.items, ...data.items] });
          setPage(page + 1);
        }
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  const setFilter = (key, value) => setFilters({ ...filters, [key]: value });

  return (
    <Page
      title={__('Abilities', 'agent-studio')}
      description={__(
        'Abilities are the actions agents can take, like reading a post or sending an email. WordPress, your plugins and the services you connect all add them. An agent can only use the ones you choose for it.',
        'agent-studio',
      )}
    >
      <Readiness />

      <h2 className="agent-studio-section-title">
        {__('Every ability agents can use', 'agent-studio')}
      </h2>
      <div className="agent-studio-filter-bar">
        <SearchControl
          __nextHasNoMarginBottom
          label={__('Search abilities', 'agent-studio')}
          placeholder={__('Search abilities', 'agent-studio')}
          value={search}
          onChange={setSearch}
        />
        {result && (
          <AbilityFilters
            categories={result.categories}
            filters={filters}
            setFilter={setFilter}
          />
        )}
      </div>

      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      {result && (
        <p className="agent-studio-muted" aria-live="polite">
          {sprintf(
            /* translators: 1: number shown, 2: number found */
            _n(
              'Showing %1$d of %2$d ability',
              'Showing %1$d of %2$d abilities',
              result.total,
              'agent-studio',
            ),
            result.items.length,
            result.total,
          )}
        </p>
      )}

      {result && result.items.length === 0 && !loading && (
        <p>
          {search || Object.values(filters).some(Boolean)
            ? __(
                'No abilities match. Try other words or filters.',
                'agent-studio',
              )
            : __(
                'No abilities are registered yet. Plugins add them, and so do the services you connect.',
                'agent-studio',
              )}{' '}
          <a href={config.urls.connections}>
            {__('Connect a service', 'agent-studio')}
          </a>
        </p>
      )}

      {result && result.items.length > 0 && (
        <ul className="agent-studio-list">
          {result.items.map((item) => (
            <li key={item.name} className="agent-studio-list__item">
              <button
                type="button"
                className="agent-studio-list__link agent-studio-list__button"
                aria-expanded={open === item.name}
                onClick={() => setOpen(open === item.name ? '' : item.name)}
              >
                <span className="agent-studio-list__title">
                  {item.label} <RiskBadge risk={item.risk} />
                </span>
                <span
                  className={
                    'agent-studio-list__text' +
                    (open === item.name ? '' : ' agent-studio-clamp')
                  }
                >
                  {item.description}
                </span>
                <span className="agent-studio-list__meta">
                  {item.categoryLabel} · {usedByText(item.usedBy)}
                </span>
              </button>
              {open === item.name && (
                <dl className="agent-studio-details agent-studio-list__details">
                  <dt>{__('ID', 'agent-studio')}</dt>
                  <dd>
                    <code>{item.name}</code>
                  </dd>
                  <dt>{__('Comes from', 'agent-studio')}</dt>
                  <dd>{sourceText(item)}</dd>
                  <dt>{__('Approval', 'agent-studio')}</dt>
                  <dd>
                    {item.risk === 'read'
                      ? __(
                          'Only reads, so it never needs approval.',
                          'agent-studio',
                        )
                      : __(
                          'Can change things, so agents set to ask before changes wait for your approval before using it.',
                          'agent-studio',
                        )}
                  </dd>
                </dl>
              )}
            </li>
          ))}
        </ul>
      )}

      {loading && <Spinner />}

      {result && page < result.pages && !loading && (
        <div className="agent-studio-gap-top">
          <Button variant="secondary" onClick={more}>
            {__('Show more', 'agent-studio')}
          </Button>
        </div>
      )}
    </Page>
  );
}
