import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Notice,
  Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api, config } from '../api.js';
import Page from '../ui/Page.jsx';
import Linkify from '../ui/Linkify.jsx';
import SetupFields from '../ui/SetupFields.jsx';
import { useNotify } from '../ui/notices.jsx';
import TypeCards from './TypeCards.jsx';
import BasicsFields from './BasicsFields.jsx';

function defaults(type) {
  const settings = {};
  type.steps.forEach((step) =>
    step.fields.forEach((field) => {
      if (field.type !== 'secret' && field.default) {
        settings[field.key] = field.default;
      }
    }),
  );
  return settings;
}

/**
 * Pick a kind of channel, then follow its numbered steps on one page.
 */
export default function AddChannel({ typeKey, navigate }) {
  const notify = useNotify();
  const [types, setTypes] = useState(null);
  const [agents, setAgents] = useState([]);
  const [basics, setBasics] = useState({ name: '', agent_id: 0, notify: true });
  const [settings, setSettings] = useState({});
  const [secrets, setSecrets] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    Promise.all([api('channel-types'), api('agents')])
      .then(([kinds, list]) => {
        setTypes(kinds);
        setAgents(list);
        const picked = kinds.find((t) => t.key === typeKey);
        if (picked) {
          setSettings(defaults(picked));
          setBasics((b) => ({ ...b, name: b.name || picked.label }));
        }
        if (list.length === 1) {
          setBasics((b) => ({ ...b, agent_id: list[0].id }));
        }
      })
      .catch((e) => setError(e.message));
  }, [typeKey]);

  const type = (types || []).find((t) => t.key === typeKey);

  const submit = (event) => {
    event.preventDefault();
    setBusy(true);
    setError('');
    api('channels', {
      method: 'POST',
      data: { type: typeKey, ...basics, settings, secrets },
    })
      .then((channel) => {
        if (channel.status === 'active') {
          notify(__('Channel switched on.', 'agent-studio'));
        }
        navigate(String(channel.id));
      })
      .catch((e) => {
        setError(e.message);
        setBusy(false);
      });
  };

  const back = {
    label: __('All channels', 'agent-studio'),
    onClick: () => navigate(''),
  };

  if (types === null) {
    return error ? (
      <Notice status="error" isDismissible={false}>
        {error}
      </Notice>
    ) : (
      <Spinner />
    );
  }

  if (!type) {
    return (
      <Page title={__('Add a channel', 'agent-studio')} back={back}>
        <TypeCards types={types} onPick={(key) => navigate('new-' + key)} />
      </Page>
    );
  }

  let number = 1;

  return (
    <Page
      title={type.label}
      description={type.description}
      back={{
        label: __('Choose another kind', 'agent-studio'),
        onClick: () => navigate('new'),
      }}
    >
      <form
        onSubmit={submit}
        className="agent-studio-narrow agent-studio-steps-form"
      >
        <Card>
          <CardHeader>
            <h2>
              <span className="agent-studio-step-number">{number++}</span>
              {__('Name it', 'agent-studio')}
            </h2>
          </CardHeader>
          <CardBody className="agent-studio-fields">
            {type.capabilities.receive && agents.length === 0 ? (
              <Notice status="warning" isDismissible={false}>
                {__(
                  'Create an agent first: people need an agent to talk to here.',
                  'agent-studio',
                )}{' '}
                <a href={config.urls.newAgent}>
                  {__('Create an agent', 'agent-studio')}
                </a>
              </Notice>
            ) : (
              <BasicsFields
                type={type}
                agents={agents}
                values={basics}
                onChange={setBasics}
              />
            )}
          </CardBody>
        </Card>

        {type.steps.map((step) => (
          <Card key={step.title}>
            <CardHeader>
              <h2>
                <span className="agent-studio-step-number">{number++}</span>
                {step.title}
              </h2>
            </CardHeader>
            <CardBody className="agent-studio-fields">
              {step.instructions.map((line) => (
                <p key={line} className="agent-studio-instruction">
                  <Linkify text={line} />
                </p>
              ))}
              {step.fields.length > 0 && (
                <SetupFields
                  fields={step.fields}
                  values={{ ...settings, ...secrets }}
                  onChange={(next) => {
                    const nextSettings = { ...settings };
                    const nextSecrets = { ...secrets };
                    step.fields.forEach((field) => {
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
              )}
            </CardBody>
          </Card>
        ))}

        {error && (
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        )}

        <div className="agent-studio-form-actions">
          <Button variant="primary" type="submit" isBusy={busy} disabled={busy}>
            {busy
              ? __('Switching on…', 'agent-studio')
              : __('Save and switch on', 'agent-studio')}
          </Button>
        </div>
      </form>
    </Page>
  );
}
