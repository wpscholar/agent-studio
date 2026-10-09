import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import StatusPill from '../ui/StatusPill.jsx';
import { timeAgo } from '../ui/format.js';
import { runTone, statusOf } from './status.js';

const FINISHED = ['completed', 'failed', 'cancelled', 'skipped'];
const POLL_EVERY = 2000;
const POLL_TIMES = 60;

/** The agent's status, what it means, and the buttons that change it. */
export function StatusCard({
  isNew,
  status,
  dirty,
  busy,
  hasTriggers,
  onSave,
}) {
  const current = statusOf(status);
  const action = (label, next, variant = 'secondary') => (
    <Button
      variant={variant}
      isBusy={busy === next}
      disabled={!!busy}
      onClick={() => onSave(next)}
    >
      {label}
    </Button>
  );

  return (
    <Card>
      <CardHeader>
        <h2>{__('Status', 'agent-studio')}</h2>
        <StatusPill tone={current.tone}>{current.label()}</StatusPill>
      </CardHeader>
      <CardBody>
        <p className="agent-studio-muted">{current.help()}</p>
        {status === 'active' && !hasTriggers && (
          <p className="agent-studio-warning-text">
            {__(
              'It has no triggers, so nothing starts it on its own yet.',
              'agent-studio',
            )}
          </p>
        )}
        <div className="agent-studio-stack">
          {status === 'draft' &&
            action(__('Turn on', 'agent-studio'), 'active', 'primary')}
          {status === 'paused' &&
            action(__('Turn back on', 'agent-studio'), 'active', 'primary')}
          {status === 'active' && action(__('Pause', 'agent-studio'), 'paused')}
          {!isNew &&
            status !== 'draft' &&
            action(
              __('Move back to drafts', 'agent-studio'),
              'draft',
              'tertiary',
            )}
        </div>
        {dirty && status !== 'draft' && (
          <p className="agent-studio-help">
            {__('These buttons also save your other changes.', 'agent-studio')}
          </p>
        )}
      </CardBody>
    </Card>
  );
}

/** Start the agent now and watch what happens. */
export function TryItCard({ agentId, disabledReason }) {
  const [run, setRun] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const timer = useRef(null);

  useEffect(() => () => clearTimeout(timer.current), []);

  const watch = (id, times) => {
    api('runs/' + id)
      .then((data) => {
        setRun(data);
        if (
          !FINISHED.includes(data.status) &&
          data.status !== 'awaiting_approval' &&
          times > 0
        ) {
          timer.current = setTimeout(() => watch(id, times - 1), POLL_EVERY);
        }
      })
      .catch((e) => setError(e.message));
  };

  const start = () => {
    setBusy(true);
    setError('');
    setRun(null);
    api('agents/' + agentId + '/run', { method: 'POST', data: {} })
      .then((data) => {
        setRun({
          id: data.run_id,
          status: 'queued',
          status_label: __('Queued', 'agent-studio'),
          url: data.url,
        });
        watch(data.run_id, POLL_TIMES);
      })
      .catch((e) => setError(e.message))
      .finally(() => setBusy(false));
  };

  return (
    <Card>
      <CardHeader>
        <h2>{__('Try it', 'agent-studio')}</h2>
      </CardHeader>
      <CardBody>
        <p className="agent-studio-muted">
          {__(
            'Run the agent once, now, without an event. Approval rules still apply, so nothing changes without your say-so.',
            'agent-studio',
          )}
        </p>
        <Button
          variant="secondary"
          isBusy={busy}
          disabled={busy || !!disabledReason}
          onClick={start}
        >
          {__('Run now', 'agent-studio')}
        </Button>
        {disabledReason && (
          <p className="agent-studio-help">{disabledReason}</p>
        )}
        {error && <p className="agent-studio-warning-text">{error}</p>}
        {run && (
          <p className="agent-studio-gap-top" aria-live="polite">
            <StatusPill tone={runTone(run.status)}>
              {run.status_label}
            </StatusPill>{' '}
            {!FINISHED.includes(run.status) &&
            run.status !== 'awaiting_approval'
              ? __('Working…', 'agent-studio')
              : ''}{' '}
            <a href={run.url}>{__('See what it did', 'agent-studio')}</a>
          </p>
        )}
        {run && run.output && (
          <p className="agent-studio-run-output">{run.output}</p>
        )}
      </CardBody>
    </Card>
  );
}

export function RecentRunsCard({ runs, count, url }) {
  return (
    <Card>
      <CardHeader>
        <h2>{__('Recent runs', 'agent-studio')}</h2>
      </CardHeader>
      <CardBody>
        {runs.length === 0 ? (
          <p className="agent-studio-muted">
            {__('It has not run yet.', 'agent-studio')}
          </p>
        ) : (
          <ul className="agent-studio-plain-list agent-studio-runs">
            {runs.map((run) => (
              <li key={run.id}>
                <a href={run.url}>
                  <StatusPill tone={runTone(run.status)}>
                    {run.statusLabel}
                  </StatusPill>
                </a>{' '}
                <span className="agent-studio-muted">
                  {run.trigger} · {timeAgo(run.at)}
                </span>
              </li>
            ))}
          </ul>
        )}
        {count > runs.length && (
          <a href={url}>
            {sprintf(
              /* translators: %d: number of runs */
              _n('See the %d run', 'See all %d runs', count, 'agent-studio'),
              count,
            )}
          </a>
        )}
      </CardBody>
    </Card>
  );
}
