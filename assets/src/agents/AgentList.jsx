import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import EmptyState from '../ui/EmptyState.jsx';
import StatusPill from '../ui/StatusPill.jsx';
import { ReadinessBanner } from '../ui/Readiness.jsx';
import { timeAgo } from '../ui/format.js';
import { statusOf } from './status.js';

const VIEWS = [
  ['', () => __('All', 'agent-studio')],
  ['active', () => __('Active', 'agent-studio')],
  ['paused', () => __('Paused', 'agent-studio')],
  ['draft', () => __('Drafts', 'agent-studio')],
];

function runsWhen(agent) {
  return agent.triggers.length === 0
    ? __('Only when run by hand', 'agent-studio')
    : sprintf(
        /* translators: %s: list of triggers, like "A post changes status, On a schedule" */
        __('Runs when: %s', 'agent-studio'),
        agent.triggers.join(', '),
      );
}

function lastRunText(run) {
  return run
    ? sprintf(
        /* translators: 1: run status, like "Completed", 2: relative time */
        __('Last run: %1$s, %2$s', 'agent-studio'),
        run.statusLabel,
        timeAgo(run.at),
      )
    : __('Never run', 'agent-studio');
}

export default function AgentList({ navigate }) {
  const [agents, setAgents] = useState(null);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [view, setView] = useState('');

  useEffect(() => {
    api('agents')
      .then(setAgents)
      .catch((e) => setError(e.message));
  }, []);

  const term = search.trim().toLowerCase();
  const shown = (agents || []).filter(
    (agent) =>
      (view === '' || agent.status === view) &&
      (term === '' ||
        agent.name.toLowerCase().includes(term) ||
        agent.summary.toLowerCase().includes(term)),
  );
  const count = (status) =>
    (agents || []).filter((agent) => status === '' || agent.status === status)
      .length;

  const open = (event, id) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    event.preventDefault();
    navigate(String(id));
  };

  const addButton = (
    <Button variant="primary" onClick={() => navigate('new')}>
      {__('New agent', 'agent-studio')}
    </Button>
  );

  return (
    <Page
      title={__('Agents', 'agent-studio')}
      description={__(
        'An agent is an AI helper that does one job on your site, like reviewing posts before they go live or welcoming new members. You decide what starts it, what it is allowed to do, and when it needs your approval.',
        'agent-studio',
      )}
      actions={agents && agents.length > 0 ? addButton : null}
    >
      <ReadinessBanner />

      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      {agents === null && !error && <Spinner />}

      {agents && agents.length === 0 && (
        <EmptyState
          title={__('No agents yet', 'agent-studio')}
          actions={addButton}
        >
          <p>
            {__(
              'Start from one of the examples, like a reviewer that reads posts submitted for review and leaves suggestions, or write your own.',
              'agent-studio',
            )}
          </p>
        </EmptyState>
      )}

      {agents && agents.length > 0 && (
        <>
          <div className="agent-studio-list-header">
            <ul className="subsubsub agent-studio-views">
              {VIEWS.map(([value, label]) => (
                <li key={value}>
                  <Button
                    variant="link"
                    className={view === value ? 'current' : ''}
                    aria-current={view === value ? 'true' : undefined}
                    onClick={() => setView(value)}
                  >
                    {label()} <span className="count">({count(value)})</span>
                  </Button>
                </li>
              ))}
            </ul>
            <div className="agent-studio-toolbar">
              <SearchControl
                __nextHasNoMarginBottom
                value={search}
                onChange={setSearch}
                label={__('Search agents', 'agent-studio')}
                placeholder={__('Search agents', 'agent-studio')}
              />
            </div>
          </div>
          {shown.length === 0 ? (
            <p>{__('No agents match.', 'agent-studio')}</p>
          ) : (
            <ul className="agent-studio-list">
              {shown.map((agent) => {
                const status = statusOf(agent.status);
                return (
                  <li key={agent.id} className="agent-studio-list__item">
                    <a
                      className="agent-studio-list__link"
                      href={'?' + linkQuery(agent.id)}
                      onClick={(event) => open(event, agent.id)}
                    >
                      <span className="agent-studio-list__title">
                        {agent.name}{' '}
                        <StatusPill tone={status.tone}>
                          {status.label()}
                        </StatusPill>
                      </span>
                      {agent.summary && (
                        <span className="agent-studio-list__text">
                          {agent.summary}
                        </span>
                      )}
                      <span className="agent-studio-list__meta">
                        {runsWhen(agent)} · {lastRunText(agent.lastRun)}
                      </span>
                    </a>
                  </li>
                );
              })}
            </ul>
          )}
        </>
      )}
    </Page>
  );
}

function linkQuery(id) {
  const params = new URLSearchParams(window.location.search);
  params.set('agent', id);
  return params.toString();
}
