import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import StatusPill from '../ui/StatusPill.jsx';
import TypeCards from './TypeCards.jsx';
import { channelPurpose, channelStatus } from './status.js';

export default function ChannelList({ navigate }) {
  const [channels, setChannels] = useState(null);
  const [types, setTypes] = useState([]);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([api('channels'), api('channel-types')])
      .then(([list, kinds]) => {
        setChannels(list);
        setTypes(kinds);
      })
      .catch((e) => setError(e.message));
  }, []);

  const open = (event, id) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    event.preventDefault();
    navigate(String(id));
  };

  return (
    <Page
      title={__('Channels', 'agent-studio')}
      description={__(
        'Channels let people reach your agents outside WordPress, for example by chatting with an agent in Telegram, and let Agent Studio reach you, with approval requests on your phone. Each channel is set up step by step.',
        'agent-studio',
      )}
      actions={
        channels && channels.length > 0 ? (
          <Button variant="primary" onClick={() => navigate('new')}>
            {__('Add channel', 'agent-studio')}
          </Button>
        ) : null
      }
    >
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      {channels === null && !error && <Spinner />}

      {channels && channels.length === 0 && (
        <>
          <h2 className="agent-studio-section-title">
            {__('Pick one to start', 'agent-studio')}
          </h2>
          <TypeCards types={types} onPick={(key) => navigate('new-' + key)} />
        </>
      )}

      {channels && channels.length > 0 && (
        <ul className="agent-studio-list">
          {channels.map((channel) => {
            const status = channelStatus(channel);
            const params = new URLSearchParams(window.location.search);
            params.set('channel', channel.id);
            return (
              <li key={channel.id} className="agent-studio-list__item">
                <a
                  className="agent-studio-list__link"
                  href={'?' + params.toString()}
                  onClick={(event) => open(event, channel.id)}
                >
                  <span className="agent-studio-list__title">
                    {channel.name}{' '}
                    <StatusPill tone={status.tone}>{status.label}</StatusPill>
                    {channel.pendingCount > 0 && (
                      <StatusPill tone="warning">
                        {sprintf(
                          /* translators: %d: number of people */
                          _n(
                            '%d waiting to be let in',
                            '%d waiting to be let in',
                            channel.pendingCount,
                            'agent-studio',
                          ),
                          channel.pendingCount,
                        )}
                      </StatusPill>
                    )}
                  </span>
                  <span className="agent-studio-list__text">
                    {channel.typeLabel} · {channelPurpose(channel)}
                  </span>
                  <span className="agent-studio-list__meta">
                    {channel.status === 'problem' && channel.message
                      ? channel.message
                      : channel.capabilities.receive
                        ? sprintf(
                            /* translators: %d: number of people */
                            _n(
                              '%d person can chat',
                              '%d people can chat',
                              channel.allowedCount,
                              'agent-studio',
                            ),
                            channel.allowedCount,
                          )
                        : channel.notify
                          ? __('Sends approval requests', 'agent-studio')
                          : __('Approval requests are off', 'agent-studio')}
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
