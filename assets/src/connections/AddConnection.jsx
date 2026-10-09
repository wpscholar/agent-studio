import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  Notice,
  RadioControl,
  Spinner,
  TextControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import SetupFields from '../ui/SetupFields.jsx';
import { useNotify } from '../ui/notices.jsx';

/**
 * Step one: what to connect to. Agent Studio then checks the address and the
 * next screen says whether a sign-in or key is needed.
 */
export default function AddConnection({ navigate }) {
  const notify = useNotify();
  const [types, setTypes] = useState(null);
  const [typeKey, setTypeKey] = useState('');
  const [name, setName] = useState('');
  const [settings, setSettings] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api('connection-types')
      .then((list) => {
        setTypes(list);
        setTypeKey(list.length > 0 ? list[0].key : '');
      })
      .catch((e) => setError(e.message));
  }, []);

  const type = (types || []).find((t) => t.key === typeKey);

  const submit = (event) => {
    event.preventDefault();
    setBusy(true);
    setError('');
    api('connections', {
      method: 'POST',
      data: { type: typeKey, name, settings },
    })
      .then((connection) => {
        if (connection.status === 'connected') {
          notify(
            sprintf(
              /* translators: 1: connection name, 2: number of tools */
              _n(
                'Connected to %1$s. It offers %2$d tool.',
                'Connected to %1$s. It offers %2$d tools.',
                connection.toolCount,
                'agent-studio',
              ),
              connection.name,
              connection.toolCount,
            ),
          );
        }
        navigate(String(connection.id));
      })
      .catch((e) => {
        setError(e.message);
        setBusy(false);
      });
  };

  return (
    <Page
      title={__('Add a connection', 'agent-studio')}
      back={{
        label: __('All connections', 'agent-studio'),
        onClick: () => navigate(''),
      }}
    >
      {types === null && !error && <Spinner />}
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      {type && (
        <form onSubmit={submit} className="agent-studio-narrow">
          <Card>
            <CardBody className="agent-studio-fields">
              {types.length > 1 && (
                <RadioControl
                  label={__('What do you want to connect?', 'agent-studio')}
                  selected={typeKey}
                  options={types.map((t) => ({
                    label: t.label,
                    value: t.key,
                    description: t.description,
                  }))}
                  onChange={(value) => {
                    setTypeKey(value);
                    setSettings({});
                  }}
                />
              )}
              {types.length === 1 && (
                <p className="agent-studio-lead">{type.description}</p>
              )}
              <TextControl
                __nextHasNoMarginBottom
                __next40pxDefaultSize
                label={__('Name', 'agent-studio')}
                help={__(
                  'What you call this service, like “GitHub”. Agents see it next to each tool.',
                  'agent-studio',
                )}
                value={name}
                onChange={setName}
              />
              <SetupFields
                fields={type.fields}
                values={settings}
                onChange={setSettings}
              />
            </CardBody>
          </Card>
          <div className="agent-studio-form-actions">
            <Button
              variant="primary"
              type="submit"
              isBusy={busy}
              disabled={busy}
            >
              {busy
                ? __('Checking the service…', 'agent-studio')
                : __('Connect', 'agent-studio')}
            </Button>
            <span className="agent-studio-muted">
              {__(
                'Next, Agent Studio checks whether the service needs you to sign in or give it a key.',
                'agent-studio',
              )}
            </span>
          </div>
        </form>
      )}
    </Page>
  );
}
