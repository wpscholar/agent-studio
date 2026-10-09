import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import EmptyState from '../ui/EmptyState.jsx';
import StatusPill from '../ui/StatusPill.jsx';
import { timeAgo } from '../ui/format.js';
import { connectionStatus, hostOf } from './status.js';

export default function ConnectionList({ navigate }) {
  const [connections, setConnections] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api('connections')
      .then(setConnections)
      .catch((e) => setError(e.message));
  }, []);

  const add = (
    <Button variant="primary" onClick={() => navigate('new')}>
      {__('Add connection', 'agent-studio')}
    </Button>
  );

  const open = (event, id) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    event.preventDefault();
    navigate(String(id));
  };

  return (
    <Page
      title={__('Connections', 'agent-studio')}
      description={__(
        'Connect other services so your agents can use them: for example GitHub to open issues, or your help desk to look up tickets. Each service’s tools then appear in every agent’s “What can it do?” list, where you choose which ones that agent may use.',
        'agent-studio',
      )}
      actions={connections && connections.length > 0 ? add : null}
    >
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      {connections === null && !error && <Spinner />}

      {connections && connections.length === 0 && (
        <EmptyState
          title={__('No connections yet', 'agent-studio')}
          actions={add}
        >
          <p>
            {__(
              'Many services offer an “MCP server”: a web address made for AI agents. You will find it in the service’s documentation, often under “MCP” or “AI integrations”.',
              'agent-studio',
            )}
          </p>
          <p>
            {__(
              'Paste that address here and Agent Studio does the rest: it works out whether you need to sign in or use an API key, and lists what the service can do.',
              'agent-studio',
            )}
          </p>
        </EmptyState>
      )}

      {connections && connections.length > 0 && (
        <ul className="agent-studio-list">
          {connections.map((connection) => {
            const status = connectionStatus(connection);
            const params = new URLSearchParams(window.location.search);
            params.set('connection', connection.id);
            return (
              <li key={connection.id} className="agent-studio-list__item">
                <a
                  className="agent-studio-list__link"
                  href={'?' + params.toString()}
                  onClick={(event) => open(event, connection.id)}
                >
                  <span className="agent-studio-list__title">
                    {connection.name}{' '}
                    <StatusPill tone={status.tone}>{status.label}</StatusPill>
                  </span>
                  <span className="agent-studio-list__text">
                    {connection.status === 'problem' && connection.message
                      ? connection.message
                      : hostOf(connection.address) || connection.typeLabel}
                  </span>
                  <span className="agent-studio-list__meta">
                    {connection.usedBy.length === 0
                      ? __('Not used by any agent yet', 'agent-studio')
                      : sprintf(
                          /* translators: %d: number of agents */
                          _n(
                            'Used by %d agent',
                            'Used by %d agents',
                            connection.usedBy.length,
                            'agent-studio',
                          ),
                          connection.usedBy.length,
                        )}
                    {connection.checkedAt > 0 &&
                      ' · ' +
                        sprintf(
                          /* translators: %s: relative time */
                          __('Checked %s', 'agent-studio'),
                          timeAgo(connection.checkedAt),
                        )}
                  </span>
                </a>
              </li>
            );
          })}
        </ul>
      )}
    </Page>
  );
}
