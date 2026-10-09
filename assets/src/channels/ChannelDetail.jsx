import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Modal,
  Notice,
  Spinner,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import ConfirmDialog from '../ui/ConfirmDialog.jsx';
import SetupFields from '../ui/SetupFields.jsx';
import StatusPill from '../ui/StatusPill.jsx';
import { useNotify } from '../ui/notices.jsx';
import { timeAgo } from '../ui/format.js';
import BasicsFields from './BasicsFields.jsx';
import FollowUpSteps from './FollowUpSteps.jsx';
import { channelStatus } from './status.js';

/**
 * One channel: whether it is on, linking your own account, who may talk to
 * the agent, and its settings.
 */
export default function ChannelDetail({ id, navigate }) {
  const notify = useNotify();
  const [channel, setChannel] = useState(undefined);
  const [agents, setAgents] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState('');
  const [pairing, setPairing] = useState(null);
  const [editing, setEditing] = useState(false);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    Promise.all([api('channels/' + id), api('agents')])
      .then(([data, list]) => {
        setChannel(data);
        setAgents(list);
      })
      .catch((e) => {
        setChannel(null);
        setError(e.message);
      });
  }, [id]);

  const run = (label, request, onDone) => {
    setBusy(label);
    setError('');
    return request
      .then(onDone)
      .catch((e) => setError(e.message))
      .finally(() => setBusy(''));
  };

  const reload = () => api('channels/' + id).then(setChannel);

  if (channel === undefined) {
    return <Spinner />;
  }
  if (channel === null) {
    return (
      <Page
        title={__('Channel not found', 'agent-studio')}
        back={{
          label: __('All channels', 'agent-studio'),
          onClick: () => navigate(''),
        }}
      >
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      </Page>
    );
  }

  const status = channelStatus(channel);
  const caps = channel.capabilities;
  const on = channel.status === 'active';

  return (
    <Page
      title={
        <>
          {channel.name}{' '}
          <StatusPill tone={status.tone}>{status.label}</StatusPill>
        </>
      }
      back={{
        label: __('All channels', 'agent-studio'),
        onClick: () => navigate(''),
      }}
      actions={
        channel.status === 'new' && channel.guided ? null : on ? (
          <Button
            variant="secondary"
            isBusy={busy === 'switch'}
            disabled={busy !== ''}
            onClick={() =>
              run(
                'switch',
                api('channels/' + id + '/deactivate', { method: 'POST' }),
                (data) => {
                  setChannel(data);
                  notify(
                    __(
                      'Switched off. Nothing is sent or received until you switch it on again.',
                      'agent-studio',
                    ),
                  );
                },
              )
            }
          >
            {__('Switch off', 'agent-studio')}
          </Button>
        ) : (
          <Button
            variant="primary"
            isBusy={busy === 'switch'}
            disabled={busy !== ''}
            onClick={() =>
              run(
                'switch',
                api('channels/' + id + '/activate', { method: 'POST' }),
                (data) => {
                  setChannel(data);
                  if (data.status === 'active') {
                    notify(__('Switched on.', 'agent-studio'));
                  }
                },
              )
            }
          >
            {__('Switch on', 'agent-studio')}
          </Button>
        )
      }
    >
      {error && (
        <Notice status="error" onRemove={() => setError('')}>
          {error}
        </Notice>
      )}

      <div className="agent-studio-columns">
        <div className="agent-studio-columns__main">
          <Card
            className={
              'agent-studio-status-card ' +
              (on
                ? 'is-success'
                : channel.status === 'problem'
                  ? 'is-error'
                  : '')
            }
          >
            <CardBody>
              {on && (
                <>
                  <h2>{__('On', 'agent-studio')}</h2>
                  <p>
                    {caps.receive && channel.agent
                      ? sprintf(
                          /* translators: 1: channel kind, 2: agent name */
                          __(
                            'People you let in can chat with “%2$s” in %1$s.',
                            'agent-studio',
                          ),
                          channel.typeLabel,
                          channel.agent.name,
                        )
                      : sprintf(
                          /* translators: %s: channel kind */
                          __(
                            'Agent Studio sends notifications to %s.',
                            'agent-studio',
                          ),
                          channel.typeLabel,
                        )}
                    {caps.receive && channel.agent && !channel.agent.active && (
                      <>
                        {' '}
                        <strong>
                          {__(
                            'That agent is paused, so it will not answer.',
                            'agent-studio',
                          )}
                        </strong>
                      </>
                    )}
                  </p>
                </>
              )}
              {channel.status === 'problem' && (
                <>
                  <h2>{__('This channel is not working', 'agent-studio')}</h2>
                  <p>{channel.message}</p>
                  <div className="agent-studio-button-row">
                    <Button
                      variant="secondary"
                      onClick={() => setEditing(true)}
                    >
                      {__('Change settings', 'agent-studio')}
                    </Button>
                  </div>
                </>
              )}
              {channel.status === 'new' && channel.guided && (
                <>
                  <h2>{__('Almost there', 'agent-studio')}</h2>
                  <p>
                    {__(
                      'Finish the steps below and the channel switches on by itself.',
                      'agent-studio',
                    )}
                  </p>
                </>
              )}
              {(channel.status === 'off' ||
                (channel.status === 'new' && !channel.guided)) && (
                <>
                  <h2>{__('Off', 'agent-studio')}</h2>
                  <p>
                    {__(
                      'Nothing is sent or received. Switch it on when you are ready.',
                      'agent-studio',
                    )}
                  </p>
                </>
              )}
              {on && channel.message && (
                <p className="agent-studio-muted">{channel.message}</p>
              )}
            </CardBody>
          </Card>

          {channel.guided &&
            channel.followUp.length > 0 &&
            !(on && channel.followUp.every((step) => step.done)) && (
              <FollowUpSteps channel={channel} onChange={setChannel} />
            )}

          {on && caps.receive && (
            <Card>
              <CardHeader>
                <h2>{__('Link your account', 'agent-studio')}</h2>
              </CardHeader>
              <CardBody>
                {channel.linkedToMe ? (
                  <p>
                    {sprintf(
                      /* translators: %s: channel kind */
                      __(
                        'Your %s account is linked. You can chat with the agent there and approve its actions.',
                        'agent-studio',
                      ),
                      channel.typeLabel,
                    )}
                  </p>
                ) : (
                  <>
                    <p>
                      {sprintf(
                        /* translators: %s: channel kind */
                        __(
                          'Link your %s account to chat with the agent, approve its actions with a tap, and get approval requests there.',
                          'agent-studio',
                        ),
                        channel.typeLabel,
                      )}
                    </p>
                    {pairing ? (
                      <div className="agent-studio-pairing">
                        {pairing.link && (
                          <Button
                            variant="primary"
                            href={pairing.link}
                            target="_blank"
                            rel="noreferrer"
                          >
                            {sprintf(
                              /* translators: %s: channel kind */ __(
                                'Open %s and link',
                                'agent-studio',
                              ),
                              channel.typeLabel,
                            )}
                          </Button>
                        )}
                        <p>
                          {pairing.link
                            ? __(
                                'Or send this message to the bot:',
                                'agent-studio',
                              )
                            : __(
                                'Send this message to the bot:',
                                'agent-studio',
                              )}
                        </p>
                        <code className="agent-studio-pairing__code">
                          {pairing.text || pairing.code}
                        </code>
                        <p className="agent-studio-muted">
                          {__(
                            'The code works once, for 30 minutes. Come back here afterwards and refresh.',
                            'agent-studio',
                          )}{' '}
                          <Button variant="link" onClick={() => reload()}>
                            {__('Refresh', 'agent-studio')}
                          </Button>
                        </p>
                      </div>
                    ) : (
                      <Button
                        variant="primary"
                        isBusy={busy === 'pair'}
                        disabled={busy !== ''}
                        onClick={() =>
                          run(
                            'pair',
                            api('channels/' + id + '/pairing', {
                              method: 'POST',
                            }),
                            setPairing,
                          )
                        }
                      >
                        {__('Link my account', 'agent-studio')}
                      </Button>
                    )}
                  </>
                )}
              </CardBody>
            </Card>
          )}

          {on && caps.notify && (
            <Card>
              <CardHeader>
                <h2>{__('Notifications', 'agent-studio')}</h2>
              </CardHeader>
              <CardBody>
                <p>
                  {channel.notify
                    ? __(
                        'Approval requests for agents you may approve come here, with Approve and Reject buttons.',
                        'agent-studio',
                      )
                    : __(
                        'Approval requests are switched off for this channel. Turn them on under Settings.',
                        'agent-studio',
                      )}
                </p>
                {!channel.notifyReady && (
                  <Notice status="warning" isDismissible={false}>
                    {__(
                      'Link your account first, so Agent Studio knows where to send them.',
                      'agent-studio',
                    )}
                  </Notice>
                )}
                <Button
                  variant="secondary"
                  isBusy={busy === 'test'}
                  disabled={busy !== '' || !channel.notifyReady}
                  onClick={() =>
                    run(
                      'test',
                      api('channels/' + id + '/test', { method: 'POST' }),
                      () =>
                        notify(
                          __(
                            'Test sent. Check that it arrived.',
                            'agent-studio',
                          ),
                        ),
                    )
                  }
                >
                  {__('Send a test notification', 'agent-studio')}
                </Button>
              </CardBody>
            </Card>
          )}

          {caps.receive && <People channel={channel} onChange={setChannel} />}
        </div>

        <div className="agent-studio-columns__side">
          <Card>
            <CardHeader>
              <h2>{__('Settings', 'agent-studio')}</h2>
            </CardHeader>
            <CardBody>
              <dl className="agent-studio-details">
                <dt>{__('Kind', 'agent-studio')}</dt>
                <dd>{channel.typeLabel}</dd>
                {channel.agent && (
                  <>
                    <dt>{__('Agent', 'agent-studio')}</dt>
                    <dd>{channel.agent.name}</dd>
                  </>
                )}
                {channel.settings.bot_username && (
                  <>
                    <dt>{__('Bot', 'agent-studio')}</dt>
                    <dd>@{channel.settings.bot_username}</dd>
                  </>
                )}
                {channel.settings.topic && (
                  <>
                    <dt>{__('Topic', 'agent-studio')}</dt>
                    <dd className="agent-studio-break">
                      {channel.settings.topic}
                    </dd>
                  </>
                )}
                {channel.owner && (
                  <>
                    <dt>{__('Set up by', 'agent-studio')}</dt>
                    <dd>{channel.owner.name}</dd>
                  </>
                )}
              </dl>
              <div className="agent-studio-stack">
                <Button variant="secondary" onClick={() => setEditing(true)}>
                  {__('Change settings', 'agent-studio')}
                </Button>
                <Button
                  variant="tertiary"
                  isDestructive
                  onClick={() => setDeleting(true)}
                >
                  {__('Delete channel', 'agent-studio')}
                </Button>
              </div>
            </CardBody>
          </Card>
        </div>
      </div>

      {editing && (
        <EditModal
          channel={channel}
          agents={agents}
          onClose={() => setEditing(false)}
          onSaved={(data) => {
            setChannel(data);
            setEditing(false);
            notify(__('Channel saved.', 'agent-studio'));
          }}
        />
      )}

      {deleting && (
        <ConfirmDialog
          title={__('Delete this channel?', 'agent-studio')}
          confirmLabel={__('Delete channel', 'agent-studio')}
          isDestructive
          isBusy={busy === 'delete'}
          onCancel={() => setDeleting(false)}
          onConfirm={() =>
            run('delete', api('channels/' + id, { method: 'DELETE' }), () => {
              notify(
                sprintf(
                  /* translators: %s: channel name */ __(
                    'Deleted “%s”.',
                    'agent-studio',
                  ),
                  channel.name,
                ),
              );
              navigate('');
            })
          }
        >
          <p>
            {__(
              'It is switched off, and its people, conversations and saved tokens are removed. Agents and their run history are kept.',
              'agent-studio',
            )}
          </p>
        </ConfirmDialog>
      )}
    </Page>
  );
}

function People({ channel, onChange }) {
  const [busy, setBusy] = useState(0);
  const [error, setError] = useState('');
  const people = [...channel.people].sort(
    (a, b) =>
      (a.status === 'pending' ? -1 : 0) - (b.status === 'pending' ? -1 : 0),
  );

  const change = (person, status) => {
    setBusy(person.id);
    setError('');
    api(
      'channels/' + channel.id + '/contacts/' + person.id,
      status ? { method: 'PUT', data: { status } } : { method: 'DELETE' },
    )
      .then(onChange)
      .catch((e) => setError(e.message))
      .finally(() => setBusy(0));
  };

  const label = {
    pending: __('Waiting', 'agent-studio'),
    allowed: __('Allowed', 'agent-studio'),
    blocked: __('Blocked', 'agent-studio'),
  };
  const tone = { pending: 'warning', allowed: 'success', blocked: 'neutral' };

  return (
    <Card>
      <CardHeader>
        <h2>{__('Who can chat', 'agent-studio')}</h2>
      </CardHeader>
      <CardBody>
        <p className="agent-studio-muted">
          {__(
            'Anyone can find a bot and write to it, so only people you let in get answers. They act with the agent’s permissions. People who link their own WordPress account can also approve actions, if their account may.',
            'agent-studio',
          )}
        </p>
        {error && (
          <Notice status="error" onRemove={() => setError('')}>
            {error}
          </Notice>
        )}
        {people.length === 0 ? (
          <p>
            {__(
              'Nobody has written yet. When someone does, they appear here so you can let them in.',
              'agent-studio',
            )}
          </p>
        ) : (
          <ul className="agent-studio-people">
            {people.map((person) => (
              <li
                key={person.id}
                className={person.status === 'pending' ? 'is-pending' : ''}
              >
                <div>
                  <strong>{person.name}</strong>{' '}
                  <StatusPill tone={tone[person.status]}>
                    {label[person.status]}
                  </StatusPill>
                  <div className="agent-studio-muted">
                    {person.user
                      ? sprintf(
                          /* translators: %s: WordPress user name */ __(
                            'Linked to %s',
                            'agent-studio',
                          ),
                          person.user.name,
                        )
                      : __('Not linked to a WordPress account', 'agent-studio')}
                    {person.lastSeen > 0 &&
                      ' · ' +
                        sprintf(
                          /* translators: %s: relative time */ __(
                            'last wrote %s',
                            'agent-studio',
                          ),
                          timeAgo(person.lastSeen),
                        )}
                  </div>
                </div>
                <div className="agent-studio-button-row">
                  {person.status !== 'allowed' && (
                    <Button
                      variant="primary"
                      size="compact"
                      isBusy={busy === person.id}
                      disabled={busy !== 0}
                      onClick={() => change(person, 'allowed')}
                    >
                      {__('Let in', 'agent-studio')}
                    </Button>
                  )}
                  {person.status !== 'blocked' && (
                    <Button
                      variant="secondary"
                      size="compact"
                      disabled={busy !== 0}
                      onClick={() => change(person, 'blocked')}
                    >
                      {__('Block', 'agent-studio')}
                    </Button>
                  )}
                  <Button
                    variant="tertiary"
                    size="compact"
                    disabled={busy !== 0}
                    onClick={() => change(person, '')}
                  >
                    {__('Forget', 'agent-studio')}
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}
        {people.length > 0 && (
          <p className="agent-studio-muted">
            {sprintf(
              /* translators: %d: number of people */
              _n('%d person', '%d people', people.length, 'agent-studio'),
              people.length,
            )}
          </p>
        )}
      </CardBody>
    </Card>
  );
}

function EditModal({ channel, agents, onClose, onSaved }) {
  const [basics, setBasics] = useState({
    name: channel.name,
    agent_id: channel.agent ? channel.agent.id : 0,
    notify: channel.notify,
  });
  const [settings, setSettings] = useState(channel.settings);
  const [secrets, setSecrets] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const fields = channel.steps
    .concat(channel.followUp || [])
    .flatMap((step) => step.fields);
  const type = { capabilities: channel.capabilities };

  const save = () => {
    setBusy(true);
    api('channels/' + channel.id, {
      method: 'PUT',
      data: { ...basics, settings, secrets },
    })
      .then(onSaved)
      .catch((e) => {
        setError(e.message);
        setBusy(false);
      });
  };

  return (
    <Modal
      title={__('Channel settings', 'agent-studio')}
      onRequestClose={onClose}
      size="medium"
    >
      <div className="agent-studio-fields">
        {error && (
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        )}
        <BasicsFields
          type={type}
          agents={agents}
          values={basics}
          onChange={setBasics}
        />
        <SetupFields
          fields={fields}
          values={{ ...settings, ...secrets }}
          savedSecrets={channel.savedSecrets}
          onChange={(next) => {
            const nextSettings = { ...settings };
            const nextSecrets = { ...secrets };
            fields.forEach((field) => {
              if (field.type === 'secret') {
                nextSecrets[field.key] = next[field.key] ?? '';
              } else {
                nextSettings[field.key] = next[field.key] ?? '';
              }
            });
            setSettings(nextSettings);
            setSecrets(nextSecrets);
          }}
        />
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
