import { useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  FormTokenField,
  SelectControl,
  TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api, config } from '../api.js';
import ConfirmDialog from '../ui/ConfirmDialog.jsx';
import CopyField from '../ui/CopyField.jsx';
import Picker from '../ui/Picker.jsx';
import SetupFields from '../ui/SetupFields.jsx';

const DEFAULTS = { schedule: { every: 'daily', at: '09:00' } };

/**
 * "When should it run?": the triggers this agent has, each with its own
 * filters, and a searchable list to add more from.
 */
export default function TriggersSection({
  triggers,
  types,
  onChange,
  onTypes,
  webhook,
  onNewSecret,
}) {
  const [adding, setAdding] = useState(false);

  const update = (index, value) =>
    onChange(
      triggers.map((trigger, i) =>
        i === index ? { ...trigger, config: value } : trigger,
      ),
    );
  const remove = (index) => onChange(triggers.filter((_, i) => i !== index));
  const add = (type) => {
    onTypes({ [type.key]: type });
    onChange([
      ...triggers,
      { key: type.key, config: { ...(DEFAULTS[type.key] || {}) } },
    ]);
  };

  return (
    <Card>
      <CardHeader>
        <div>
          <h2>{__('When should it run?', 'agent-studio')}</h2>
          <p className="agent-studio-card-intro">
            {__(
              'Add the events that start this agent. Each one can have filters, so the agent only runs for what matters. With no triggers, it only runs when you start it by hand.',
              'agent-studio',
            )}
          </p>
        </div>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        {triggers.length === 0 && (
          <p className="agent-studio-muted">
            {__('Nothing starts this agent yet.', 'agent-studio')}
          </p>
        )}

        {triggers.map((trigger, index) => {
          const type = types[trigger.key] || {
            key: trigger.key,
            label: trigger.key,
            description: '',
            fields: [],
          };
          return (
            <div key={trigger.key} className="agent-studio-item-card">
              <div className="agent-studio-item-card__head">
                <div>
                  <strong>{type.label}</strong>
                  {type.description && (
                    <p className="agent-studio-muted">{type.description}</p>
                  )}
                </div>
                <Button
                  variant="tertiary"
                  size="compact"
                  isDestructive
                  onClick={() => remove(index)}
                  label={sprintf(
                    /* translators: %s: trigger name */
                    __('Remove “%s”', 'agent-studio'),
                    type.label,
                  )}
                >
                  {__('Remove', 'agent-studio')}
                </Button>
              </div>
              {type.missing && (
                <p className="agent-studio-warning-text">{type.description}</p>
              )}
              <TriggerFields
                type={type}
                value={trigger.config}
                onChange={(value) => update(index, value)}
                webhook={webhook}
                onNewSecret={onNewSecret}
              />
            </div>
          );
        })}

        <div>
          <Button variant="secondary" onClick={() => setAdding(true)}>
            {__('Add a trigger', 'agent-studio')}
          </Button>
        </div>
      </CardBody>

      {adding && (
        <Picker
          title={__('Add a trigger', 'agent-studio')}
          description={__(
            'Choose what should start this agent. Plugins you install can add more.',
            'agent-studio',
          )}
          searchLabel={__('Search triggers', 'agent-studio')}
          load={({ search, filters, page }) =>
            api(
              'triggers?' +
                new URLSearchParams({
                  search,
                  group: filters.group || '',
                  page,
                }),
            )
          }
          renderFilters={(result, filters, setFilter) =>
            result.groups.length > 1 && (
              <SelectControl
                __nextHasNoMarginBottom
                __next40pxDefaultSize
                label={__('Show', 'agent-studio')}
                value={filters.group || ''}
                onChange={(value) => setFilter('group', value)}
                options={[
                  { value: '', label: __('All kinds', 'agent-studio') },
                  ...result.groups.map((group) => ({
                    value: group,
                    label: group,
                  })),
                ]}
              />
            )
          }
          itemKey={(item) => item.key}
          selected={triggers.map((trigger) => trigger.key)}
          renderItem={(item) => (
            <span className="agent-studio-picker__text">
              <strong>{item.label}</strong>
              <span className="agent-studio-chip">{item.group}</span>
              <span className="agent-studio-muted">{item.description}</span>
            </span>
          )}
          onPick={add}
          onClose={() => setAdding(false)}
        />
      )}
    </Card>
  );
}

function TriggerFields({ type, value, onChange, webhook, onNewSecret }) {
  const special = (type.fields || []).find(
    (field) => field.type === 'schedule' || field.type === 'webhook',
  );
  if (special && special.type === 'schedule') {
    return <ScheduleFields value={value} onChange={onChange} />;
  }
  if (special && special.type === 'webhook') {
    return <WebhookDetails webhook={webhook} onNewSecret={onNewSecret} />;
  }

  const lists = (type.fields || []).filter((field) => field.type === 'list');
  const others = (type.fields || []).filter((field) => field.type !== 'list');
  if (lists.length === 0 && others.length === 0) {
    return null;
  }

  return (
    <div className="agent-studio-item-card__fields">
      {lists.map((field) => (
        <ListField
          key={field.key}
          field={field}
          value={value[field.key] || []}
          onChange={(list) => onChange({ ...value, [field.key]: list })}
        />
      ))}
      {others.length > 0 && (
        <SetupFields fields={others} values={value} onChange={onChange} />
      )}
    </div>
  );
}

/** Several values, typed or picked from suggestions. Shows names; stores IDs. */
function ListField({ field, value, onChange }) {
  const suggestions = field.suggestions || [];
  const labelOf = (id) =>
    (suggestions.find((s) => s.value === id) || { label: id }).label;
  const idOf = (text) => {
    const lower = String(text).trim().toLowerCase();
    const match = suggestions.find(
      (s) => s.label.toLowerCase() === lower || s.value === lower,
    );
    return match
      ? match.value
      : lower.replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
  };

  return (
    <div>
      <FormTokenField
        __nextHasNoMarginBottom
        __next40pxDefaultSize
        label={field.label}
        value={(Array.isArray(value) ? value : []).map(labelOf)}
        suggestions={suggestions.map((s) => s.label)}
        onChange={(tokens) =>
          onChange(
            [
              ...new Set(
                tokens.map((token) =>
                  idOf(typeof token === 'string' ? token : token.value),
                ),
              ),
            ].filter(Boolean),
          )
        }
        __experimentalExpandOnFocus
        __experimentalShowHowTo={false}
      />
      {field.help && <p className="agent-studio-help">{field.help}</p>}
    </div>
  );
}

const FREQUENCIES = () => [
  { value: 'hourly', label: __('Every hour', 'agent-studio') },
  { value: 'daily', label: __('Every day', 'agent-studio') },
  { value: 'weekly', label: __('Every week', 'agent-studio') },
];

const DAYS = () => [
  { value: 'monday', label: __('Monday', 'agent-studio') },
  { value: 'tuesday', label: __('Tuesday', 'agent-studio') },
  { value: 'wednesday', label: __('Wednesday', 'agent-studio') },
  { value: 'thursday', label: __('Thursday', 'agent-studio') },
  { value: 'friday', label: __('Friday', 'agent-studio') },
  { value: 'saturday', label: __('Saturday', 'agent-studio') },
  { value: 'sunday', label: __('Sunday', 'agent-studio') },
];

function ScheduleFields({ value, onChange }) {
  const every = value.every || 'daily';
  const set = (key) => (next) => onChange({ ...value, [key]: next });

  return (
    <div className="agent-studio-item-card__fields">
      <div className="agent-studio-inline-fields">
        <SelectControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          label={__('How often', 'agent-studio')}
          value={every}
          options={FREQUENCIES()}
          onChange={set('every')}
        />
        {every === 'weekly' && (
          <SelectControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={__('On', 'agent-studio')}
            value={value.day || 'monday'}
            options={DAYS()}
            onChange={set('day')}
          />
        )}
        <TextControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          type="time"
          label={
            every === 'hourly'
              ? __('At minutes past the hour', 'agent-studio')
              : __('At', 'agent-studio')
          }
          value={value.at || '09:00'}
          onChange={set('at')}
        />
      </div>
      <p className="agent-studio-help">
        {sprintf(
          /* translators: %s: the site's timezone, like "Europe/London" */
          __(
            'Times use your site’s timezone (%s). For “Every hour”, only the minutes count: 9:15 means a quarter past every hour. On quiet sites a run can start a few minutes late.',
            'agent-studio',
          ),
          config.timezone || 'UTC',
        )}{' '}
        <a href={config.urls.timezone}>
          {__('Change the timezone', 'agent-studio')}
        </a>
      </p>
    </div>
  );
}

function WebhookDetails({ webhook, onNewSecret }) {
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);

  if (!webhook) {
    return (
      <p className="agent-studio-help">
        {__(
          'Save the agent to get its webhook address and secret.',
          'agent-studio',
        )}
      </p>
    );
  }

  return (
    <div className="agent-studio-item-card__fields">
      <CopyField
        label={__('Webhook address', 'agent-studio')}
        value={webhook.url}
        help={__(
          'Other systems send a POST request here to start the agent. Whatever they send, the agent receives as the event details.',
          'agent-studio',
        )}
      />
      <CopyField
        label={__('Secret', 'agent-studio')}
        value={webhook.secret}
        help={__(
          'Keep this private. It proves a request really comes from your system.',
          'agent-studio',
        )}
      />
      <details className="agent-studio-details-toggle">
        <summary>{__('How to sign requests', 'agent-studio')}</summary>
        <p>
          {__(
            'Send the header X-Agent-Studio-Timestamp with the current time in Unix seconds, and X-Agent-Studio-Signature with “sha256=” followed by the HMAC-SHA256 of “timestamp.body” using the secret. Add an Idempotency-Key header so a retried request does not start the agent twice.',
            'agent-studio',
          )}
        </p>
      </details>
      <div>
        <Button
          variant="secondary"
          size="compact"
          onClick={() => setConfirming(true)}
        >
          {__('Make a new secret', 'agent-studio')}
        </Button>
      </div>
      {confirming && (
        <ConfirmDialog
          title={__('Make a new secret?', 'agent-studio')}
          confirmLabel={__('Make a new secret', 'agent-studio')}
          isDestructive
          isBusy={busy}
          onCancel={() => setConfirming(false)}
          onConfirm={() => {
            setBusy(true);
            onNewSecret().finally(() => {
              setBusy(false);
              setConfirming(false);
            });
          }}
        >
          <p>
            {__(
              'The current secret stops working straight away. Anything that calls this webhook needs the new one.',
              'agent-studio',
            )}
          </p>
        </ConfirmDialog>
      )}
    </div>
  );
}
