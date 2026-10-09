import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Modal,
  Notice,
  Spinner,
  TextControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import ConfirmDialog from '../ui/ConfirmDialog.jsx';
import CopyField from '../ui/CopyField.jsx';
import RiskBadge from '../ui/RiskBadge.jsx';
import SetupFields from '../ui/SetupFields.jsx';
import StatusPill from '../ui/StatusPill.jsx';
import { useNotify } from '../ui/notices.jsx';
import { timeAgo } from '../ui/format.js';
import { connectionStatus, hostOf } from './status.js';

/**
 * One connection: whether it works, how it signs in, which tools it offers,
 * and which agents use it.
 */
export default function ConnectionDetail({ id, navigate }) {
  const notify = useNotify();
  const [connection, setConnection] = useState(undefined);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState('');
  const [showKey, setShowKey] = useState(false);
  const [manual, setManual] = useState(null);
  const [editing, setEditing] = useState(false);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    api('connections/' + id)
      .then(setConnection)
      .catch((e) => {
        setConnection(null);
        setError(e.message);
      });
  }, [id]);

  const run = (label, request, onDone) => {
    setBusy(label);
    setError('');
    return request
      .then(onDone)
      .catch((e) => {
        if (e.code === 'agent_studio_manual_client') {
          setManual({
            message: e.message,
            redirectUri:
              (e.data && e.data.redirect_uri) || connection.redirectUri,
            server: (e.data && e.data.server) || '',
            docs: (e.data && e.data.docs) || '',
          });
        } else {
          setError(e.message);
        }
      })
      .finally(() => setBusy(''));
  };

  const updated = (data) => {
    setConnection(data);
    if (data.status === 'connected') {
      setShowKey(false);
      setManual(null);
    }
  };

  const check = () =>
    run(
      'check',
      api('connections/' + id + '/check', { method: 'POST' }),
      (data) => {
        updated(data);
        notify(
          data.status === 'connected'
            ? __('Checked. The connection works.', 'agent-studio')
            : __('Checked. See the message at the top.', 'agent-studio'),
        );
      },
    );

  const signIn = (path = 'sign-in', data = {}) =>
    run(
      'sign-in',
      api('connections/' + id + '/' + path, { method: 'POST', data }),
      (result) => {
        if (result.url) {
          window.location.assign(result.url);
          return;
        }
        updated(result.connection);
        notify(
          __(
            'This service does not need a sign-in. It is connected.',
            'agent-studio',
          ),
        );
      },
    );

  if (connection === undefined) {
    return <Spinner />;
  }

  if (connection === null) {
    return (
      <Page
        title={__('Connection not found', 'agent-studio')}
        back={{
          label: __('All connections', 'agent-studio'),
          onClick: () => navigate(''),
        }}
      >
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      </Page>
    );
  }

  const status = connectionStatus(connection);
  const server = connection.signIn
    ? connection.signIn.server
    : hostOf(connection.address);

  return (
    <Page
      title={
        <>
          {connection.name}{' '}
          <StatusPill tone={status.tone}>{status.label}</StatusPill>
        </>
      }
      back={{
        label: __('All connections', 'agent-studio'),
        onClick: () => navigate(''),
      }}
      actions={
        <Button
          variant="secondary"
          isBusy={busy === 'check'}
          disabled={busy !== ''}
          onClick={check}
        >
          {__('Check again', 'agent-studio')}
        </Button>
      }
    >
      {error && (
        <Notice status="error" onRemove={() => setError('')}>
          {error}
        </Notice>
      )}

      <div className="agent-studio-columns">
        <div className="agent-studio-columns__main">
          <StatusCard
            connection={connection}
            server={server}
            busy={busy}
            onSignIn={() => signIn()}
            onUseKey={() => setShowKey(true)}
            onEdit={() => setEditing(true)}
            onCheck={check}
          />

          {manual && (
            <ManualClientForm
              manual={manual}
              server={server}
              busy={busy === 'sign-in'}
              onSubmit={(clientId, clientSecret) =>
                signIn('client', {
                  client_id: clientId,
                  client_secret: clientSecret,
                })
              }
              onCancel={() => setManual(null)}
            />
          )}

          {showKey && (
            <KeyForm
              connection={connection}
              busy={busy === 'key'}
              onCancel={() => setShowKey(false)}
              onSubmit={(key, header) =>
                run(
                  'key',
                  api('connections/' + id + '/key', {
                    method: 'POST',
                    data: { key, header },
                  }),
                  (data) => {
                    updated(data);
                    if (data.status === 'connected') {
                      notify(
                        __('Key saved. The connection works.', 'agent-studio'),
                      );
                    } else {
                      setError(
                        data.message ||
                          __(
                            'The service did not accept that key.',
                            'agent-studio',
                          ),
                      );
                    }
                  },
                )
              }
            />
          )}

          <ToolsCard connection={connection} />
        </div>

        <div className="agent-studio-columns__side">
          <Card>
            <CardHeader>
              <h2>{__('Used by', 'agent-studio')}</h2>
            </CardHeader>
            <CardBody>
              {connection.usedBy.length === 0 ? (
                <p className="agent-studio-muted">
                  {__(
                    'No agent uses this connection yet. Open an agent and tick the tools you want under “What can it do?”.',
                    'agent-studio',
                  )}
                </p>
              ) : (
                <ul className="agent-studio-plain-list">
                  {connection.usedBy.map((agent) => (
                    <li key={agent.id}>
                      <a href={agent.url}>{agent.name}</a>
                    </li>
                  ))}
                </ul>
              )}
            </CardBody>
          </Card>
          <Card>
            <CardHeader>
              <h2>{__('Details', 'agent-studio')}</h2>
            </CardHeader>
            <CardBody>
              <dl className="agent-studio-details">
                <dt>{__('Kind', 'agent-studio')}</dt>
                <dd>{connection.typeLabel}</dd>
                {connection.address && (
                  <>
                    <dt>{__('Address', 'agent-studio')}</dt>
                    <dd className="agent-studio-break">{connection.address}</dd>
                  </>
                )}
                {connection.checkedAt > 0 && (
                  <>
                    <dt>{__('Last checked', 'agent-studio')}</dt>
                    <dd>{timeAgo(connection.checkedAt)}</dd>
                  </>
                )}
                <dt>{__('Tool names start with', 'agent-studio')}</dt>
                <dd>
                  <code>{'mcp-' + connection.slug + '/'}</code>
                </dd>
              </dl>
              <div className="agent-studio-stack">
                <Button variant="secondary" onClick={() => setEditing(true)}>
                  {__('Edit name or address', 'agent-studio')}
                </Button>
                <Button
                  variant="tertiary"
                  isDestructive
                  onClick={() => setDeleting(true)}
                >
                  {__('Delete connection', 'agent-studio')}
                </Button>
              </div>
            </CardBody>
          </Card>
        </div>
      </div>

      {editing && (
        <EditModal
          connection={connection}
          onClose={() => setEditing(false)}
          onSaved={(data) => {
            updated(data);
            setEditing(false);
            notify(__('Connection saved.', 'agent-studio'));
          }}
        />
      )}

      {deleting && (
        <ConfirmDialog
          title={__('Delete this connection?', 'agent-studio')}
          confirmLabel={__('Delete connection', 'agent-studio')}
          isDestructive
          isBusy={busy === 'delete'}
          onCancel={() => setDeleting(false)}
          onConfirm={() =>
            run(
              'delete',
              api('connections/' + id, { method: 'DELETE' }),
              () => {
                notify(
                  sprintf(
                    /* translators: %s: connection name */ __(
                      'Deleted “%s”.',
                      'agent-studio',
                    ),
                    connection.name,
                  ),
                );
                navigate('');
              },
            )
          }
        >
          <p>
            {connection.usedBy.length > 0
              ? sprintf(
                  /* translators: %d: number of agents */
                  _n(
                    '%d agent uses its tools and will no longer be able to.',
                    '%d agents use its tools and will no longer be able to.',
                    connection.usedBy.length,
                    'agent-studio',
                  ),
                  connection.usedBy.length,
                )
              : __('No agents use it.', 'agent-studio')}
          </p>
          <p>
            {__(
              'Saved keys and sign-ins for it are removed too.',
              'agent-studio',
            )}
          </p>
        </ConfirmDialog>
      )}
    </Page>
  );
}

function StatusCard({
  connection,
  server,
  busy,
  onSignIn,
  onUseKey,
  onEdit,
  onCheck,
}) {
  if (connection.status === 'connected') {
    return (
      <Card className="agent-studio-status-card is-success">
        <CardBody>
          <h2>{__('Connected', 'agent-studio')}</h2>
          <p>
            {__(
              'Agents can use this service’s tools. Choose which ones each agent may use in that agent’s “What can it do?” list.',
              'agent-studio',
            )}
          </p>
          <p className="agent-studio-muted">
            {connection.authMode === 'oauth' &&
              sprintf(
                /* translators: %s: server name */ __(
                  'Signed in at %s.',
                  'agent-studio',
                ),
                server,
              )}
            {connection.authMode === 'key' &&
              __('Uses an API key.', 'agent-studio')}
            {connection.authMode === 'none' &&
              __(
                'This service is open, so no sign-in is needed.',
                'agent-studio',
              )}{' '}
            {connection.authMode === 'oauth' && (
              <Button variant="link" onClick={onSignIn} disabled={busy !== ''}>
                {__('Sign in again', 'agent-studio')}
              </Button>
            )}
            {connection.authMode === 'key' && (
              <Button variant="link" onClick={onUseKey}>
                {__('Change key', 'agent-studio')}
              </Button>
            )}
          </p>
        </CardBody>
      </Card>
    );
  }

  if (connection.status === 'needs_sign_in') {
    return (
      <Card className="agent-studio-status-card is-warning">
        <CardBody>
          <h2>{__('Sign in to finish connecting', 'agent-studio')}</h2>
          <p>
            {__(
              'This service only works for people it knows. The easiest way is to sign in: you will go to the service, approve access, and come straight back here.',
              'agent-studio',
            )}
          </p>
          {connection.message && (
            <p className="agent-studio-muted">{connection.message}</p>
          )}
          <div className="agent-studio-button-row">
            <Button
              variant="primary"
              onClick={onSignIn}
              isBusy={busy === 'sign-in'}
              disabled={busy !== ''}
            >
              {sprintf(
                /* translators: %s: connection name, like GitHub */ __(
                  'Sign in to %s',
                  'agent-studio',
                ),
                connection.name,
              )}
            </Button>
            <Button
              variant="secondary"
              onClick={onUseKey}
              disabled={busy !== ''}
            >
              {__('Use an API key instead', 'agent-studio')}
            </Button>
          </div>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card
      className={
        'agent-studio-status-card ' +
        (connection.status === 'problem' ? 'is-error' : '')
      }
    >
      <CardBody>
        <h2>
          {connection.status === 'problem'
            ? __('This connection is not working', 'agent-studio')
            : __('Not checked yet', 'agent-studio')}
        </h2>
        {connection.message && <p>{connection.message}</p>}
        <div className="agent-studio-button-row">
          <Button
            variant="primary"
            onClick={onCheck}
            isBusy={busy === 'check'}
            disabled={busy !== ''}
          >
            {__('Check again', 'agent-studio')}
          </Button>
          <Button variant="secondary" onClick={onEdit}>
            {__('Change the address', 'agent-studio')}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

function KeyForm({ connection, busy, onSubmit, onCancel }) {
  const [key, setKey] = useState('');
  const [header, setHeader] = useState(connection.keyHeader || 'Authorization');
  const [advanced, setAdvanced] = useState(header !== 'Authorization');

  return (
    <Card>
      <CardHeader>
        <h2>{__('Use an API key', 'agent-studio')}</h2>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        <p>
          {__(
            'Create a key (sometimes called a token or personal access token) in the service’s settings, usually under Developer, API or Security, and paste it here. Agent Studio keeps it encrypted and never shows it again.',
            'agent-studio',
          )}
        </p>
        <TextControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          type="password"
          autoComplete="off"
          label={__('API key or token', 'agent-studio')}
          placeholder={
            connection.hasKey
              ? __('Saved. Leave empty to keep it.', 'agent-studio')
              : ''
          }
          value={key}
          onChange={setKey}
        />
        {advanced ? (
          <TextControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={__('Send it in this header', 'agent-studio')}
            help={__(
              'Most services expect “Authorization”, and the key is sent as “Bearer <key>”. Change this only if the service’s documentation names another header, such as X-API-Key.',
              'agent-studio',
            )}
            value={header}
            onChange={setHeader}
          />
        ) : (
          <Button variant="link" onClick={() => setAdvanced(true)}>
            {__(
              'The service wants the key in a different header',
              'agent-studio',
            )}
          </Button>
        )}
        <div className="agent-studio-button-row">
          <Button
            variant="primary"
            isBusy={busy}
            disabled={busy || (key.trim() === '' && !connection.hasKey)}
            onClick={() =>
              onSubmit(key.trim(), header.trim() || 'Authorization')
            }
          >
            {__('Save key and connect', 'agent-studio')}
          </Button>
          <Button variant="tertiary" onClick={onCancel}>
            {__('Cancel', 'agent-studio')}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

function ManualClientForm({ manual, server, busy, onSubmit, onCancel }) {
  const [clientId, setClientId] = useState('');
  const [clientSecret, setClientSecret] = useState('');

  return (
    <Card>
      <CardHeader>
        <h2>
          {__(
            'One more step: register Agent Studio with the service',
            'agent-studio',
          )}
        </h2>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        <p>{manual.message}</p>
        <ol className="agent-studio-steps">
          <li>
            {sprintf(
              /* translators: %s: server name */
              __(
                'At %s, open the developer or API settings and create a new app (it may be called an OAuth app or client).',
                'agent-studio',
              ),
              manual.server || server,
            )}
            {manual.docs && (
              <>
                {' '}
                <a href={manual.docs} target="_blank" rel="noreferrer">
                  {__('Open the service’s instructions', 'agent-studio')}
                </a>
              </>
            )}
          </li>
          <li>
            <CopyField
              label={__(
                'When it asks for a callback or redirect address, paste this:',
                'agent-studio',
              )}
              value={manual.redirectUri}
            />
          </li>
          <li>
            {__(
              'Copy the client ID it gives you (and the client secret, if there is one) into the boxes below.',
              'agent-studio',
            )}
          </li>
        </ol>
        <TextControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          label={__('Client ID', 'agent-studio')}
          value={clientId}
          onChange={setClientId}
        />
        <TextControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          type="password"
          autoComplete="off"
          label={__('Client secret (optional)', 'agent-studio')}
          help={__(
            'Only if the service gave you one. It is stored encrypted.',
            'agent-studio',
          )}
          value={clientSecret}
          onChange={setClientSecret}
        />
        <div className="agent-studio-button-row">
          <Button
            variant="primary"
            isBusy={busy}
            disabled={busy || clientId.trim() === ''}
            onClick={() => onSubmit(clientId.trim(), clientSecret.trim())}
          >
            {__('Save and sign in', 'agent-studio')}
          </Button>
          <Button variant="tertiary" onClick={onCancel}>
            {__('Cancel', 'agent-studio')}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

function ToolsCard({ connection }) {
  return (
    <Card>
      <CardHeader>
        <h2>{__('What it can do', 'agent-studio')}</h2>
      </CardHeader>
      <CardBody>
        {connection.tools.length === 0 ? (
          <p className="agent-studio-muted">
            {connection.status === 'connected'
              ? __('The service did not offer any tools.', 'agent-studio')
              : __(
                  'Its tools appear here once the connection works.',
                  'agent-studio',
                )}
          </p>
        ) : (
          <>
            <p className="agent-studio-muted">
              {__(
                'Each tool is labelled by what it says it does. Agents ask for approval before anything other than reading, unless you change that agent’s safety settings. These labels come from the service itself.',
                'agent-studio',
              )}
            </p>
            <ul className="agent-studio-tools">
              {connection.tools.map((tool) => (
                <li key={tool.name}>
                  <div className="agent-studio-tools__head">
                    <strong>{tool.title}</strong> <RiskBadge risk={tool.risk} />
                  </div>
                  {tool.description && (
                    <p className="agent-studio-tools__text">
                      {tool.description}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          </>
        )}
      </CardBody>
    </Card>
  );
}

function EditModal({ connection, onClose, onSaved }) {
  const [name, setName] = useState(connection.name);
  const [settings, setSettings] = useState(connection.settings);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const save = () => {
    setBusy(true);
    api('connections/' + connection.id, {
      method: 'PUT',
      data: { name, settings },
    })
      .then(onSaved)
      .catch((e) => {
        setError(e.message);
        setBusy(false);
      });
  };

  return (
    <Modal
      title={__('Edit connection', 'agent-studio')}
      onRequestClose={onClose}
      size="medium"
    >
      <div className="agent-studio-fields">
        {error && (
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        )}
        <TextControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          label={__('Name', 'agent-studio')}
          value={name}
          onChange={setName}
        />
        <SetupFields
          fields={connection.fields}
          values={settings}
          onChange={setSettings}
        />
        <p className="agent-studio-muted">
          {__(
            'Changing the address connects to a different server, so you will need to sign in or add a key again.',
            'agent-studio',
          )}
        </p>
      </div>
      <div className="agent-studio-modal-actions">
        <Button variant="tertiary" onClick={onClose}>
          {__('Cancel', 'agent-studio')}
        </Button>
        <Button variant="primary" isBusy={busy} disabled={busy} onClick={save}>
          {__('Save', 'agent-studio')}
        </Button>
      </div>
    </Modal>
  );
}
