import { useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api.js';
import Linkify from '../ui/Linkify.jsx';
import SetupFields from '../ui/SetupFields.jsx';
import { useNotify } from '../ui/notices.jsx';

/**
 * The rest of a guided setup (for example Slack), on the channel's own page:
 * numbered steps with links, fields to save, and a Connect button.
 */
export default function FollowUpSteps({ channel, onChange }) {
  return (
    <div className="agent-studio-steps-form">
      <h2 className="agent-studio-section-title">
        {__('Finish setting up', 'agent-studio')}
      </h2>
      {channel.followUp.map((step, index) => (
        <Step
          key={step.title}
          number={index + 1}
          step={step}
          channel={channel}
          onChange={onChange}
        />
      ))}
    </div>
  );
}

function Step({ number, step, channel, onChange }) {
  const notify = useNotify();
  const [values, setValues] = useState(() => {
    const initial = {};
    step.fields.forEach((field) => {
      if (field.type !== 'secret') {
        initial[field.key] = channel.settings[field.key] ?? field.default ?? '';
      }
    });
    return initial;
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const save = () => {
    const settings = {};
    const secrets = {};
    step.fields.forEach((field) => {
      (field.type === 'secret' ? secrets : settings)[field.key] =
        values[field.key] ?? '';
    });
    setBusy(true);
    setError('');
    api('channels/' + channel.id, {
      method: 'PUT',
      data: { settings, secrets },
    })
      .then((data) => {
        onChange(data);
        notify(__('Saved.', 'agent-studio'));
        setValues((current) => {
          const next = { ...current };
          step.fields.forEach((field) => {
            if (field.type === 'secret') {
              next[field.key] = '';
            }
          });
          return next;
        });
      })
      .catch((e) => setError(e.message))
      .finally(() => setBusy(false));
  };

  const authorize = () => {
    setBusy(true);
    setError('');
    api('channels/' + channel.id + '/authorize', { method: 'POST' })
      .then((result) => window.location.assign(result.url))
      .catch((e) => {
        setError(e.message);
        setBusy(false);
      });
  };

  return (
    <Card
      className={step.done ? 'agent-studio-step is-done' : 'agent-studio-step'}
    >
      <CardHeader>
        <h2>
          <span className="agent-studio-step-number">
            {step.done ? '✓' : number}
          </span>
          {step.title}
        </h2>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        {step.instructions.map((line) => (
          <p key={line} className="agent-studio-instruction">
            <Linkify text={line} />
          </p>
        ))}
        {step.links.length > 0 && (
          <div className="agent-studio-button-row">
            {step.links.map((link) => (
              <Button
                key={link.url}
                variant={step.done ? 'secondary' : 'primary'}
                href={link.url}
                target="_blank"
                rel="noreferrer"
              >
                {link.label} ↗
              </Button>
            ))}
          </div>
        )}
        {step.fields.length > 0 && (
          <>
            <SetupFields
              fields={step.fields}
              values={values}
              onChange={setValues}
              savedSecrets={channel.savedSecrets}
            />
            <div className="agent-studio-button-row">
              <Button
                variant="secondary"
                isBusy={busy}
                disabled={busy}
                onClick={save}
              >
                {__('Save', 'agent-studio')}
              </Button>
            </div>
          </>
        )}
        {step.action === 'authorize' && (
          <div className="agent-studio-button-row">
            <Button
              variant={step.done ? 'secondary' : 'primary'}
              isBusy={busy}
              disabled={busy}
              onClick={authorize}
            >
              {step.actionLabel}
            </Button>
          </div>
        )}
        {error && (
          <Notice status="error" onRemove={() => setError('')}>
            {error}
          </Notice>
        )}
      </CardBody>
    </Card>
  );
}
