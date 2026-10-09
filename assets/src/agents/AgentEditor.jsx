import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Notice,
  Spinner,
  TextControl,
  TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import ConfirmDialog from '../ui/ConfirmDialog.jsx';
import StatusPill from '../ui/StatusPill.jsx';
import { useNotify } from '../ui/notices.jsx';
import { statusOf } from './status.js';
import TriggersSection from './TriggersSection.jsx';
import AbilitiesSection from './AbilitiesSection.jsx';
import SkillsSection from './SkillsSection.jsx';
import { ModelSection, SafetySection } from './SettingsSections.jsx';
import { RecentRunsCard, StatusCard, TryItCard } from './SidePanels.jsx';

/** The parts of an agent the editor changes, in the shape the API takes. */
function formOf(agent) {
  return {
    name: agent.name,
    instructions: agent.instructions,
    status: agent.status,
    triggers: agent.triggers,
    abilities: agent.abilities,
    skills: agent.skills,
    approval: agent.approval,
    runAs: agent.runAs ? agent.runAs.id : 0,
    maxSteps: agent.maxSteps,
    maxRunsPerHour: agent.maxRunsPerHour,
    provider: agent.provider,
    model: agent.model,
  };
}

function byKey(list, key) {
  return Object.fromEntries(list.map((item) => [item[key], item]));
}

/**
 * Create or change one agent. The sections run from what people change most
 * (what it does, when it runs) to what they set once (model, limits).
 */
export default function AgentEditor({ id, template = '', navigate }) {
  const notify = useNotify();
  const isNew = !id;
  const [agent, setAgent] = useState(undefined);
  const [form, setForm] = useState(null);
  const [saved, setSaved] = useState(null);
  const [types, setTypes] = useState({});
  const [abilityDetails, setAbilityDetails] = useState({});
  const [skillDetails, setSkillDetails] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState('');
  const [leaving, setLeaving] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const load = (data) => {
    setAgent(data);
    setForm(formOf(data));
    setSaved(isNew && !data.id ? null : formOf(data));
    setTypes({ ...data.triggerTypes });
    setAbilityDetails(byKey(data.abilityDetails, 'name'));
    setSkillDetails(byKey(data.skillDetails, 'slug'));
  };

  useEffect(() => {
    api(
      isNew
        ? 'agents/new?' + new URLSearchParams({ template })
        : 'agents/' + id,
    )
      .then(load)
      .catch((e) => {
        setAgent(null);
        setError(e.message);
      });
    // Load once per agent; the screen remounts for another one.
  }, [id, template]);

  const dirty = form !== null && JSON.stringify(form) !== JSON.stringify(saved);

  // Warn before closing the tab with unsaved changes.
  useEffect(() => {
    if (!dirty) {
      return;
    }
    const warn = (event) => {
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  const set = (changes) => setForm({ ...form, ...changes });

  const save = (status = form.status) => {
    setBusy(status === form.status ? 'save' : status);
    setError('');
    const data = { ...form, status };
    api(isNew ? 'agents' : 'agents/' + id, {
      method: isNew ? 'POST' : 'PUT',
      data,
    })
      .then((result) => {
        load(result);
        setSaved(formOf(result));
        notify(
          status === form.status
            ? __('Agent saved.', 'agent-studio')
            : status === 'active'
              ? __('Agent saved and turned on.', 'agent-studio')
              : status === 'paused'
                ? __('Agent saved and paused.', 'agent-studio')
                : __('Agent saved as a draft.', 'agent-studio'),
        );
        if (isNew) {
          navigate(String(result.id));
        }
      })
      .catch((e) => {
        setError(e.message);
        window.scrollTo(0, 0);
      })
      .finally(() => setBusy(''));
  };

  const remove = () => {
    setBusy('delete');
    api('agents/' + id, { method: 'DELETE' })
      .then(() => {
        notify(
          sprintf(
            /* translators: %s: agent name */
            __('Deleted “%s”.', 'agent-studio'),
            agent.name,
          ),
        );
        setSaved(form);
        navigate('');
      })
      .catch((e) => {
        setError(e.message);
        setDeleting(false);
      })
      .finally(() => setBusy(''));
  };

  const newSecret = () =>
    api('agents/' + id + '/webhook-secret', { method: 'POST' })
      .then((data) => {
        setAgent({ ...agent, webhook: data.webhook });
        notify(
          __('New secret made. The old one no longer works.', 'agent-studio'),
        );
      })
      .catch((e) => setError(e.message));

  const back = () => (dirty ? setLeaving(true) : navigate(''));

  if (agent === undefined) {
    return <Spinner />;
  }

  if (agent === null) {
    return (
      <Page
        title={__('Agent not found', 'agent-studio')}
        back={{ label: __('All agents', 'agent-studio'), onClick: back }}
      >
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      </Page>
    );
  }

  const status = statusOf(form.status);
  const title = isNew
    ? __('New agent', 'agent-studio')
    : (saved && saved.name) || __('Agent', 'agent-studio');

  return (
    <Page
      title={
        <>
          {title}{' '}
          {!isNew && (
            <StatusPill tone={status.tone}>{status.label()}</StatusPill>
          )}
        </>
      }
      back={{ label: __('All agents', 'agent-studio'), onClick: back }}
      actions={
        <>
          {isNew && (
            <Button
              variant="primary"
              isBusy={busy === 'save'}
              disabled={!!busy}
              onClick={() => save('draft')}
            >
              {__('Save draft', 'agent-studio')}
            </Button>
          )}
          {!isNew && (
            <Button
              variant="primary"
              isBusy={busy === 'save'}
              disabled={!!busy || !dirty}
              onClick={() => save()}
            >
              {dirty
                ? __('Save changes', 'agent-studio')
                : __('Saved', 'agent-studio')}
            </Button>
          )}
        </>
      }
    >
      {error && (
        <Notice status="error" onRemove={() => setError('')}>
          {error}
        </Notice>
      )}

      <div className="agent-studio-columns">
        <div className="agent-studio-columns__main">
          <Card>
            <CardHeader>
              <div>
                <h2>{__('What should it do?', 'agent-studio')}</h2>
                <p className="agent-studio-card-intro">
                  {__(
                    'Describe the job the way you would brief a new colleague. The agent reads this every time it runs, along with the details of whatever started it.',
                    'agent-studio',
                  )}
                </p>
              </div>
            </CardHeader>
            <CardBody className="agent-studio-fields">
              <TextControl
                __nextHasNoMarginBottom
                __next40pxDefaultSize
                label={__('Name', 'agent-studio')}
                help={__(
                  'A short name you will recognise in lists and notifications, like “Comment moderator”.',
                  'agent-studio',
                )}
                value={form.name}
                onChange={(value) => set({ name: value })}
              />
              <TextareaControl
                __nextHasNoMarginBottom
                label={__('Instructions', 'agent-studio')}
                help={__(
                  'Say what to look at, what to decide, and what to do, step by step. Name the abilities it should use. Say what it must never do. Plain sentences work best.',
                  'agent-studio',
                )}
                rows={12}
                value={form.instructions}
                onChange={(value) => set({ instructions: value })}
              />
            </CardBody>
          </Card>

          <TriggersSection
            triggers={form.triggers}
            types={types}
            onChange={(triggers) => set({ triggers })}
            onTypes={(more) => setTypes({ ...types, ...more })}
            webhook={agent.webhook}
            onNewSecret={newSecret}
          />

          <AbilitiesSection
            chosen={form.abilities}
            details={abilityDetails}
            onChange={(abilities, item) => {
              set({ abilities });
              setAbilityDetails({ ...abilityDetails, [item.name]: item });
            }}
          />

          <SkillsSection
            chosen={form.skills}
            details={skillDetails}
            onChange={(skills, item) => {
              set({ skills });
              setSkillDetails({ ...skillDetails, [item.slug]: item });
            }}
          />

          <ModelSection
            provider={form.provider}
            model={form.model}
            onChange={set}
          />

          <SafetySection
            form={form}
            limits={agent.limits}
            runAsUser={agent.runAs}
            onChange={set}
          />
        </div>

        <div className="agent-studio-columns__side">
          <StatusCard
            isNew={isNew}
            status={form.status}
            dirty={dirty}
            busy={busy}
            hasTriggers={form.triggers.length > 0}
            onSave={save}
          />
          <TryItCard
            agentId={agent.id}
            disabledReason={
              isNew
                ? __('Save the agent first.', 'agent-studio')
                : dirty
                  ? __(
                      'Save your changes first, so the run uses them.',
                      'agent-studio',
                    )
                  : ''
            }
          />
          {!isNew && (
            <>
              <RecentRunsCard
                runs={agent.recentRuns}
                count={agent.runCount}
                url={agent.runsUrl}
              />
              <Card>
                <CardHeader>
                  <h2>{__('More', 'agent-studio')}</h2>
                </CardHeader>
                <CardBody>
                  {agent.owner && (
                    <p className="agent-studio-muted">
                      {sprintf(
                        /* translators: %s: person's name */
                        __(
                          'Set up by %s, who can also approve what it asks to do.',
                          'agent-studio',
                        ),
                        agent.owner.name,
                      )}
                    </p>
                  )}
                  <Button
                    variant="tertiary"
                    isDestructive
                    onClick={() => setDeleting(true)}
                  >
                    {__('Delete agent', 'agent-studio')}
                  </Button>
                </CardBody>
              </Card>
            </>
          )}
        </div>
      </div>

      {deleting && (
        <ConfirmDialog
          title={__('Delete this agent?', 'agent-studio')}
          confirmLabel={__('Delete agent', 'agent-studio')}
          isDestructive
          isBusy={busy === 'delete'}
          onConfirm={remove}
          onCancel={() => setDeleting(false)}
        >
          <p>
            {__(
              'It stops running and its settings are removed for good. Its past runs stay in the run log. To stop it for a while instead, pause it.',
              'agent-studio',
            )}
          </p>
        </ConfirmDialog>
      )}

      {leaving && (
        <ConfirmDialog
          title={__('Leave without saving?', 'agent-studio')}
          confirmLabel={__('Leave', 'agent-studio')}
          isDestructive
          onConfirm={() => navigate('')}
          onCancel={() => setLeaving(false)}
        >
          <p>
            {__('Your changes to this agent will be lost.', 'agent-studio')}
          </p>
        </ConfirmDialog>
      )}
    </Page>
  );
}
