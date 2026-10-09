import { useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  SearchControl,
  SelectControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api, config } from '../api.js';
import Picker from '../ui/Picker.jsx';
import RiskBadge from '../ui/RiskBadge.jsx';

/** Above this many, the chosen list gets its own filter box. */
const FILTER_FROM = 8;

export const RISKS = () => [
  { value: '', label: __('Any effect', 'agent-studio') },
  { value: 'read', label: __('Only reads', 'agent-studio') },
  { value: 'write', label: __('Changes site data', 'agent-studio') },
  { value: 'external', label: __('Acts outside the site', 'agent-studio') },
  {
    value: 'destructive',
    label: __('Deletes or hard to undo', 'agent-studio'),
  },
];

export const SOURCES = () => [
  { value: '', label: __('From anywhere', 'agent-studio') },
  { value: 'site', label: __('This site and its plugins', 'agent-studio') },
  { value: 'connection', label: __('Connected services', 'agent-studio') },
];

/** Filters for searching abilities, used here and on the Abilities screen. */
export function AbilityFilters({ categories, filters, setFilter }) {
  return (
    <>
      <SelectControl
        __nextHasNoMarginBottom
        __next40pxDefaultSize
        label={__('Category', 'agent-studio')}
        value={filters.category || ''}
        onChange={(value) => setFilter('category', value)}
        options={[
          { value: '', label: __('All categories', 'agent-studio') },
          ...categories.map((category) => ({
            value: category.value,
            label: category.label + ' (' + category.count + ')',
          })),
        ]}
      />
      <SelectControl
        __nextHasNoMarginBottom
        __next40pxDefaultSize
        label={__('What it does', 'agent-studio')}
        value={filters.risk || ''}
        onChange={(value) => setFilter('risk', value)}
        options={RISKS()}
      />
      <SelectControl
        __nextHasNoMarginBottom
        __next40pxDefaultSize
        label={__('Comes from', 'agent-studio')}
        value={filters.source || ''}
        onChange={(value) => setFilter('source', value)}
        options={SOURCES()}
      />
    </>
  );
}

export function searchAbilities({ search, filters, page }) {
  return api(
    'abilities?' +
      new URLSearchParams({
        search,
        category: filters.category || '',
        risk: filters.risk || '',
        source: filters.source || '',
        page,
      }),
  );
}

/**
 * "What can it do?": the abilities this agent may use, and a searchable,
 * paged list of every ability on the site to choose more from.
 */
export default function AbilitiesSection({ chosen, details, onChange }) {
  const [picking, setPicking] = useState(false);
  const [filter, setFilter] = useState('');

  const toggle = (item, on) =>
    onChange(
      on ? [...chosen, item.name] : chosen.filter((name) => name !== item.name),
      item,
    );

  const term = filter.trim().toLowerCase();
  const shown = chosen
    .map((name) => details[name] || { name, label: name, missing: true })
    .filter(
      (item) =>
        term === '' ||
        (item.label + ' ' + item.name + ' ' + (item.description || ''))
          .toLowerCase()
          .includes(term),
    );
  const changes = chosen.filter(
    (name) =>
      details[name] && details[name].risk && details[name].risk !== 'read',
  ).length;

  return (
    <Card>
      <CardHeader>
        <div>
          <h2>{__('What can it do?', 'agent-studio')}</h2>
          <p className="agent-studio-card-intro">
            {__(
              'Abilities are the actions an agent may take, like reading a post or sending an email. It can only use the ones listed here, and only with the permissions of the person it acts as.',
              'agent-studio',
            )}
          </p>
        </div>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        {chosen.length === 0 ? (
          <p className="agent-studio-muted">
            {__(
              'No abilities yet. Without any, the agent can only think and write a reply.',
              'agent-studio',
            )}
          </p>
        ) : (
          <>
            <p className="agent-studio-muted">
              {sprintf(
                /* translators: %d: number of abilities */
                _n(
                  '%d ability.',
                  '%d abilities.',
                  chosen.length,
                  'agent-studio',
                ),
                chosen.length,
              )}{' '}
              {changes > 0 &&
                sprintf(
                  /* translators: %d: number of abilities */
                  _n(
                    '%d of them can change things, so check the approval setting below.',
                    '%d of them can change things, so check the approval setting below.',
                    changes,
                    'agent-studio',
                  ),
                  changes,
                )}
            </p>
            {chosen.length >= FILTER_FROM && (
              <SearchControl
                __nextHasNoMarginBottom
                label={__('Filter this list', 'agent-studio')}
                placeholder={__('Filter this list', 'agent-studio')}
                value={filter}
                onChange={setFilter}
              />
            )}
            <ul className="agent-studio-chosen">
              {shown.map((item) => (
                <li
                  key={item.name}
                  className={
                    'agent-studio-chosen__item' +
                    (item.missing ? ' is-missing' : '')
                  }
                >
                  <div className="agent-studio-chosen__text">
                    <span>
                      <strong>{item.label}</strong>{' '}
                      {item.risk && <RiskBadge risk={item.risk} />}
                    </span>
                    {item.missing ? (
                      <span className="agent-studio-warning-text">
                        {__(
                          'Not available right now: the plugin or connection that provides it is off. Remove it, or turn that back on.',
                          'agent-studio',
                        )}
                      </span>
                    ) : (
                      item.description && (
                        <span className="agent-studio-muted agent-studio-clamp">
                          {item.description}
                        </span>
                      )
                    )}
                    <code className="agent-studio-chosen__id">{item.name}</code>
                  </div>
                  <Button
                    variant="tertiary"
                    size="compact"
                    isDestructive
                    onClick={() => toggle(item, false)}
                    label={sprintf(
                      /* translators: %s: ability name */
                      __('Remove “%s”', 'agent-studio'),
                      item.label,
                    )}
                  >
                    {__('Remove', 'agent-studio')}
                  </Button>
                </li>
              ))}
            </ul>
          </>
        )}
        <div className="agent-studio-button-row">
          <Button variant="secondary" onClick={() => setPicking(true)}>
            {__('Choose abilities', 'agent-studio')}
          </Button>
          <a href={config.urls.connections}>
            {__('Connect another service for more', 'agent-studio')}
          </a>
        </div>
      </CardBody>

      {picking && (
        <Picker
          multiple
          title={__('Choose abilities', 'agent-studio')}
          description={__(
            'Tick what this agent may do. Give it only what its job needs: fewer abilities make it safer and more focused.',
            'agent-studio',
          )}
          searchLabel={__('Search abilities', 'agent-studio')}
          load={searchAbilities}
          renderFilters={(result, filters, set) => (
            <AbilityFilters
              categories={result.categories}
              filters={filters}
              setFilter={set}
            />
          )}
          itemKey={(item) => item.name}
          selected={chosen}
          onToggle={toggle}
          renderItem={(item) => (
            <span className="agent-studio-picker__text">
              <span>
                <strong>{item.label}</strong> <RiskBadge risk={item.risk} />{' '}
                <span className="agent-studio-chip">{item.categoryLabel}</span>
              </span>
              <span className="agent-studio-muted agent-studio-clamp">
                {item.description}
              </span>
            </span>
          )}
          onClose={() => setPicking(false)}
        />
      )}
    </Card>
  );
}
