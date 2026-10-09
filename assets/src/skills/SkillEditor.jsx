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
  TextareaControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import ConfirmDialog from '../ui/ConfirmDialog.jsx';
import { useNotify } from '../ui/notices.jsx';
import { dateTime, download, timeAgo } from '../ui/format.js';

const DESCRIPTION_MAX = 1024;
const EMPTY = { name: '', description: '', instructions: '' };

function fieldsOf(skill) {
  return {
    name: skill.name,
    description: skill.description,
    instructions: skill.instructions,
  };
}

/**
 * Create or change one skill. With no slug it is a new skill.
 */
export default function SkillEditor({ slug, navigate }) {
  const notify = useNotify();
  const isNew = !slug;
  const [skill, setSkill] = useState(isNew ? null : undefined);
  const [form, setForm] = useState(EMPTY);
  const [saved, setSaved] = useState(EMPTY);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [leaving, setLeaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [viewing, setViewing] = useState(null);

  const dirty =
    form.name !== saved.name ||
    form.description !== saved.description ||
    form.instructions !== saved.instructions;

  useEffect(() => {
    if (isNew) {
      return;
    }
    api('skills/' + slug)
      .then((data) => {
        setSkill(data);
        setForm(fieldsOf(data));
        setSaved(fieldsOf(data));
      })
      .catch((e) => {
        setSkill(null);
        setError(e.message);
      });
  }, [slug, isNew]);

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

  const set = (key) => (value) => setForm({ ...form, [key]: value });

  const save = () => {
    setBusy(true);
    setError('');
    api(isNew ? 'skills' : 'skills/' + slug, {
      method: isNew ? 'POST' : 'PUT',
      data: form,
    })
      .then((data) => {
        setSkill(data);
        setForm(fieldsOf(data));
        setSaved(fieldsOf(data));
        notify(__('Skill saved.', 'agent-studio'));
        if (isNew) {
          navigate(data.slug);
        }
      })
      .catch((e) => setError(e.message))
      .finally(() => setBusy(false));
  };

  const remove = () => {
    setBusy(true);
    api('skills/' + slug, { method: 'DELETE' })
      .then(() => {
        notify(
          sprintf(
            /* translators: %s: skill name */
            __('Deleted “%s”.', 'agent-studio'),
            saved.name,
          ),
        );
        setSaved(form);
        navigate('');
      })
      .catch((e) => {
        setError(e.message);
        setDeleting(false);
      })
      .finally(() => setBusy(false));
  };

  const exportSkill = () => {
    api('skills/' + slug + '/export')
      .then((file) => download(file.filename, file.markdown))
      .catch((e) => setError(e.message));
  };

  const back = () => (dirty ? setLeaving(true) : navigate(''));

  if (skill === undefined) {
    return <Spinner />;
  }

  if (skill === null && !isNew) {
    return (
      <Page
        title={__('Skill not found', 'agent-studio')}
        back={{ label: __('All skills', 'agent-studio'), onClick: back }}
      >
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      </Page>
    );
  }

  const title = isNew
    ? __('New skill', 'agent-studio')
    : saved.name || __('Skill', 'agent-studio');

  return (
    <Page
      title={title}
      back={{ label: __('All skills', 'agent-studio'), onClick: back }}
      actions={
        <Button
          variant="primary"
          isBusy={busy}
          disabled={busy || (!dirty && !isNew)}
          onClick={save}
        >
          {isNew
            ? __('Create skill', 'agent-studio')
            : dirty
              ? __('Save changes', 'agent-studio')
              : __('Saved', 'agent-studio')}
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
          <Card>
            <CardBody className="agent-studio-fields">
              <TextControl
                __nextHasNoMarginBottom
                __next40pxDefaultSize
                label={__('Name', 'agent-studio')}
                help={__(
                  'A short title, like “Release notes writer”.',
                  'agent-studio',
                )}
                value={form.name}
                onChange={set('name')}
              />
              <TextareaControl
                __nextHasNoMarginBottom
                label={__('When should an agent use this?', 'agent-studio')}
                help={
                  <>
                    {__(
                      'Agents always see this and use it to decide whether to open the skill, so be specific. For example: “Use when writing release notes from a list of changes.”',
                      'agent-studio',
                    )}{' '}
                    <span
                      className={
                        form.description.length > DESCRIPTION_MAX
                          ? 'agent-studio-count is-over'
                          : 'agent-studio-count'
                      }
                    >
                      {form.description.length} / {DESCRIPTION_MAX}
                    </span>
                  </>
                }
                rows={3}
                value={form.description}
                onChange={set('description')}
              />
              <TextareaControl
                __nextHasNoMarginBottom
                className="agent-studio-code-field"
                label={__('Instructions', 'agent-studio')}
                help={__(
                  'What the agent should do once it opens the skill: steps, rules, examples. Markdown formatting is fine. Agents only read this when the skill is relevant, so it can be as long as it needs to be.',
                  'agent-studio',
                )}
                rows={20}
                value={form.instructions}
                onChange={set('instructions')}
              />
            </CardBody>
          </Card>
        </div>

        <div className="agent-studio-columns__side">
          {isNew ? (
            <Card>
              <CardHeader>
                <h2>{__('Tips', 'agent-studio')}</h2>
              </CardHeader>
              <CardBody>
                <p>
                  {__(
                    'Keep one job per skill. Several small skills work better than one large one.',
                    'agent-studio',
                  )}
                </p>
                <p>
                  {__(
                    'After you create it, open an agent and tick the skill under “Skills” so that agent can use it.',
                    'agent-studio',
                  )}
                </p>
                <Button variant="link" onClick={() => navigate('import')}>
                  {__(
                    'Have a SKILL.md file? Import it instead.',
                    'agent-studio',
                  )}
                </Button>
              </CardBody>
            </Card>
          ) : (
            <>
              <UsedBy usedBy={skill.usedBy} />
              <History
                revisions={skill.revisions}
                onView={(revision) => setViewing(revision)}
              />
              <Card>
                <CardHeader>
                  <h2>{__('More', 'agent-studio')}</h2>
                </CardHeader>
                <CardBody>
                  <p className="agent-studio-muted">
                    {__('Skill ID:', 'agent-studio')} <code>{skill.slug}</code>
                    <br />
                    {__(
                      'Agents refer to the skill by this ID. It stays the same if you rename the skill.',
                      'agent-studio',
                    )}
                  </p>
                  <div className="agent-studio-stack">
                    <Button variant="secondary" onClick={exportSkill}>
                      {__('Download as SKILL.md', 'agent-studio')}
                    </Button>
                    <Button
                      variant="tertiary"
                      isDestructive
                      onClick={() => setDeleting(true)}
                    >
                      {__('Delete skill', 'agent-studio')}
                    </Button>
                  </div>
                </CardBody>
              </Card>
            </>
          )}
        </div>
      </div>

      {viewing && (
        <RevisionModal
          slug={slug}
          revision={viewing}
          onClose={() => setViewing(null)}
          onRestored={(data) => {
            setSkill(data);
            setForm(fieldsOf(data));
            setSaved(fieldsOf(data));
            setViewing(null);
            notify(__('Earlier version restored.', 'agent-studio'));
          }}
        />
      )}

      {deleting && (
        <ConfirmDialog
          title={__('Delete this skill?', 'agent-studio')}
          confirmLabel={__('Delete skill', 'agent-studio')}
          isDestructive
          isBusy={busy}
          onConfirm={remove}
          onCancel={() => setDeleting(false)}
        >
          <p>
            {skill.usedBy.length > 0
              ? sprintf(
                  /* translators: %d: number of agents */
                  _n(
                    '%d agent uses this skill. It will stop seeing it.',
                    '%d agents use this skill. They will stop seeing it.',
                    skill.usedBy.length,
                    'agent-studio',
                  ),
                  skill.usedBy.length,
                )
              : __('No agents use this skill.', 'agent-studio')}
          </p>
          <p>
            {__(
              'The skill and its history are removed for good. Download it first if you might want it back.',
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
            {__('Your changes to this skill will be lost.', 'agent-studio')}
          </p>
        </ConfirmDialog>
      )}
    </Page>
  );
}

function UsedBy({ usedBy }) {
  return (
    <Card>
      <CardHeader>
        <h2>{__('Used by', 'agent-studio')}</h2>
      </CardHeader>
      <CardBody>
        {usedBy.length === 0 ? (
          <p className="agent-studio-muted">
            {__(
              'No agents use this skill yet. Open an agent and tick it under “Skills”.',
              'agent-studio',
            )}
          </p>
        ) : (
          <ul className="agent-studio-plain-list">
            {usedBy.map((agent) => (
              <li key={agent.id}>
                <a href={agent.url}>{agent.name}</a>
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  );
}

function History({ revisions, onView }) {
  return (
    <Card>
      <CardHeader>
        <h2>{__('History', 'agent-studio')}</h2>
      </CardHeader>
      <CardBody>
        {revisions.length === 0 ? (
          <p className="agent-studio-muted">
            {__(
              'Each time you save, the earlier version is kept here.',
              'agent-studio',
            )}
          </p>
        ) : (
          <ul className="agent-studio-plain-list agent-studio-history">
            {revisions.map((revision) => (
              <li key={revision.id}>
                <span title={dateTime(revision.date)}>
                  {timeAgo(revision.date)}
                </span>
                <span className="agent-studio-muted"> · {revision.author}</span>
                {revision.current ? (
                  <span className="agent-studio-pill">
                    {__('Current', 'agent-studio')}
                  </span>
                ) : (
                  <Button variant="link" onClick={() => onView(revision)}>
                    {__('View', 'agent-studio')}
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  );
}

function RevisionModal({ slug, revision, onClose, onRestored }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const path = 'skills/' + slug + '/revisions/' + revision.id;

  useEffect(() => {
    api(path)
      .then(setData)
      .catch((e) => setError(e.message));
  }, [path]);

  const restore = () => {
    setBusy(true);
    api(path + '/restore', { method: 'POST' })
      .then(onRestored)
      .catch((e) => {
        setError(e.message);
        setBusy(false);
      });
  };

  return (
    <Modal
      title={sprintf(
        /* translators: %s: date and time */
        __('Version from %s', 'agent-studio'),
        dateTime(revision.date),
      )}
      onRequestClose={onClose}
      size="large"
    >
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      {!data && !error && <Spinner />}
      {data && (
        <dl className="agent-studio-import-preview">
          <dt>{__('Name', 'agent-studio')}</dt>
          <dd>{data.name}</dd>
          <dt>{__('When to use it', 'agent-studio')}</dt>
          <dd>{data.description}</dd>
          <dt>{__('Instructions', 'agent-studio')}</dt>
          <dd>
            <pre className="agent-studio-pre">{data.instructions}</pre>
          </dd>
        </dl>
      )}
      <div className="agent-studio-modal-actions">
        <Button variant="tertiary" onClick={onClose}>
          {__('Close', 'agent-studio')}
        </Button>
        <Button
          variant="primary"
          isBusy={busy}
          disabled={!data || busy}
          onClick={restore}
        >
          {__('Restore this version', 'agent-studio')}
        </Button>
      </div>
    </Modal>
  );
}
