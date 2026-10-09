import { useEffect, useState } from '@wordpress/element';
import {
  Card,
  CardBody,
  CardHeader,
  ComboboxControl,
  RadioControl,
  SelectControl,
  Spinner,
  TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api, config } from '../api.js';

const SEPARATOR = '::';

/** "Which AI model?": automatic, or one model from the connected providers. */
export function ModelSection({ provider, model, onChange }) {
  const [catalog, setCatalog] = useState(null);

  useEffect(() => {
    api('models')
      .then(setCatalog)
      .catch(() => setCatalog({ providers: [] }));
  }, []);

  const current = model ? provider + SEPARATOR + model : '';
  const known =
    current === '' ||
    (catalog &&
      catalog.providers.some((p) =>
        p.models.some((m) => p.id + SEPARATOR + m.id === current),
      ));

  const choose = (value) => {
    const [nextProvider, nextModel] = value.includes(SEPARATOR)
      ? value.split(SEPARATOR)
      : ['', ''];
    onChange({ provider: nextProvider, model: nextModel });
  };

  return (
    <Card>
      <CardHeader>
        <div>
          <h2>{__('Which AI model should it use?', 'agent-studio')}</h2>
          <p className="agent-studio-card-intro">
            {__(
              'The model reads the instructions and decides what to do. “Automatic” suits most agents: WordPress picks one of your connected models that can use abilities.',
              'agent-studio',
            )}
          </p>
        </div>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        {catalog === null && <Spinner />}
        {catalog && catalog.providers.length === 0 && (
          <p className="agent-studio-warning-text">
            {__(
              'No AI provider is connected, so agents cannot run yet.',
              'agent-studio',
            )}{' '}
            <a href={config.urls.connectors}>
              {__('Connect one in Settings → Connectors', 'agent-studio')}
            </a>
          </p>
        )}
        {catalog && (
          <SelectControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={__('AI model', 'agent-studio')}
            help={__(
              'Choose a specific model only if this agent should always use it, for example a faster, cheaper one for simple jobs. If it is unavailable, WordPress uses another connected model.',
              'agent-studio',
            )}
            value={current}
            onChange={choose}
          >
            <option value="">
              {__('Automatic (recommended)', 'agent-studio')}
            </option>
            {catalog.providers.map((p) => (
              <optgroup key={p.id} label={p.name}>
                {p.models.map((m) => (
                  <option key={m.id} value={p.id + SEPARATOR + m.id}>
                    {m.tools
                      ? m.name
                      : sprintf(
                          /* translators: %s: model name */
                          __('%s (cannot use abilities)', 'agent-studio'),
                          m.name,
                        )}
                  </option>
                ))}
              </optgroup>
            ))}
            {!known && (
              <option value={current}>
                {sprintf(
                  /* translators: %s: model ID */
                  __('%s (not available right now)', 'agent-studio'),
                  model,
                )}
              </option>
            )}
          </SelectControl>
        )}
      </CardBody>
    </Card>
  );
}

const APPROVALS = () => [
  {
    value: 'writes',
    label: __(
      'Ask me before it changes anything (recommended)',
      'agent-studio',
    ),
    description: __(
      'Reading is always allowed. Anything that creates, changes, deletes or sends waits for your approval.',
      'agent-studio',
    ),
  },
  {
    value: 'destructive',
    label: __(
      'Ask only before deleting or acting outside the site',
      'agent-studio',
    ),
    description: __(
      'It can change content on its own, but deleting things, sending email or using outside services waits for approval.',
      'agent-studio',
    ),
  },
  {
    value: 'never',
    label: __('Never ask', 'agent-studio'),
    description: __(
      'It acts on its own. Only for agents with a narrow job and abilities you fully trust.',
      'agent-studio',
    ),
  },
];

/** "Safety and limits": approval, whose permissions it uses, and how much it may do. */
export function SafetySection({ form, limits, runAsUser, onChange }) {
  const [options, setOptions] = useState(
    runAsUser ? [{ value: String(runAsUser.id), label: runAsUser.name }] : [],
  );
  const [loadingUsers, setLoadingUsers] = useState(false);

  const findUsers = (search) => {
    setLoadingUsers(true);
    api('users?' + new URLSearchParams({ search }))
      .then((users) => {
        const found = users.map((user) => ({
          value: String(user.id),
          label: user.name + ' (' + user.login + ')',
        }));
        const keep = options.filter(
          (option) => option.value === String(form.runAs),
        );
        setOptions([
          ...keep,
          ...found.filter((user) => user.value !== String(form.runAs)),
        ]);
      })
      .catch(() => {})
      .finally(() => setLoadingUsers(false));
  };

  useEffect(() => {
    // The first page of people; typing searches again.
    findUsers('');
  }, []);

  // Kept as typed so the box can be cleared and retyped; the server keeps it within the limits.
  const number = (key) => (value) => onChange({ [key]: value });

  return (
    <Card>
      <CardHeader>
        <div>
          <h2>{__('Safety and limits', 'agent-studio')}</h2>
          <p className="agent-studio-card-intro">
            {__(
              'Decide when the agent needs your go-ahead, whose permissions it uses, and how much it may do.',
              'agent-studio',
            )}
          </p>
        </div>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        <RadioControl
          label={__('When should it ask for approval?', 'agent-studio')}
          help={__(
            'A run that needs approval pauses until someone approves or rejects it. You are told by email, and in any notification channels you set up.',
            'agent-studio',
          )}
          selected={form.approval}
          options={APPROVALS()}
          onChange={(value) => onChange({ approval: value })}
        />
        <ComboboxControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          label={__('Acts as', 'agent-studio')}
          help={__(
            'The agent can do only what this person is allowed to do. Choose someone with just the access the job needs; an Editor or Author account is often safer than an Administrator.',
            'agent-studio',
          )}
          value={form.runAs ? String(form.runAs) : null}
          options={options}
          isLoading={loadingUsers}
          onFilterValueChange={(search) =>
            search.length > 1 && findUsers(search)
          }
          onChange={(value) =>
            value && onChange({ runAs: parseInt(value, 10) })
          }
        />
        <div className="agent-studio-inline-fields">
          <TextControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            type="number"
            min={1}
            max={limits.maxSteps}
            label={__('Steps per run', 'agent-studio')}
            help={__(
              'The most times it can think and act in one run. Stops a confused agent going round in circles.',
              'agent-studio',
            )}
            value={String(form.maxSteps)}
            onChange={number('maxSteps')}
          />
          <TextControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            type="number"
            min={1}
            max={limits.maxRunsPerHour}
            label={__('Runs per hour', 'agent-studio')}
            help={__(
              'Events beyond this are skipped, which protects your AI budget if something triggers it over and over.',
              'agent-studio',
            )}
            value={String(form.maxRunsPerHour)}
            onChange={number('maxRunsPerHour')}
          />
        </div>
      </CardBody>
    </Card>
  );
}
