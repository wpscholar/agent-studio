import { useCallback, useEffect, useState } from '@wordpress/element';
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
import StatusPill from './StatusPill.jsx';

const TONES = { ok: 'success', warning: 'warning', error: 'error' };

/** Asks the site, fresh each time, whether it can run agents. */
export function useReadiness() {
  const [readiness, setReadiness] = useState(null);
  const [error, setError] = useState('');
  const [checking, setChecking] = useState(false);

  const check = useCallback(() => {
    setChecking(true);
    return api('readiness')
      .then((data) => {
        setReadiness(data);
        setError('');
      })
      .catch((e) => setError(e.message))
      .finally(() => setChecking(false));
  }, []);

  useEffect(() => {
    check();
  }, [check]);

  return { readiness, error, checking, check };
}

function statusText(status) {
  if (status === 'ok') {
    return __('Ready', 'agent-studio');
  }
  return status === 'warning'
    ? __('Check', 'agent-studio')
    : __('Needs fixing', 'agent-studio');
}

/** The full checklist: what was checked, what is wrong, and where to fix it. */
export default function Readiness() {
  const { readiness, error, checking, check } = useReadiness();

  const problems = readiness
    ? readiness.checks.filter((item) => item.status !== 'ok')
    : [];
  const tone = !readiness
    ? ''
    : readiness.ready
      ? problems.length > 0
        ? ' is-warning'
        : ' is-success'
      : ' is-error';

  return (
    <Card className={'agent-studio-status-card' + tone}>
      <CardHeader>
        <h2>
          {!readiness
            ? __('Is this site ready to run agents?', 'agent-studio')
            : readiness.ready
              ? __('This site is ready to run agents', 'agent-studio')
              : __('Agents cannot run on this site yet', 'agent-studio')}
        </h2>
        <Button
          variant="secondary"
          size="compact"
          isBusy={checking}
          disabled={checking}
          onClick={check}
        >
          {__('Check again', 'agent-studio')}
        </Button>
      </CardHeader>
      <CardBody>
        {error && (
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        )}
        {!readiness && !error && <Spinner />}
        {readiness && (
          <ul className="agent-studio-checklist">
            {readiness.checks.map((item) => (
              <li
                key={item.key}
                className={'agent-studio-checklist__item is-' + item.status}
              >
                <span className="agent-studio-checklist__icon" aria-hidden>
                  {item.status === 'ok' ? '✓' : '!'}
                </span>
                <div>
                  <strong>{item.label}</strong>{' '}
                  <StatusPill tone={TONES[item.status]}>
                    {statusText(item.status)}
                  </StatusPill>
                  <p className="agent-studio-checklist__text">{item.message}</p>
                  {item.action && item.status !== 'ok' && (
                    <a href={item.action.url}>{item.action.label}</a>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  );
}

/**
 * A short warning for other screens: shows only when agents cannot run, and
 * says the first thing to fix.
 */
export function ReadinessBanner() {
  const { readiness } = useReadiness();
  if (!readiness || readiness.ready) {
    return null;
  }
  const first = readiness.checks.find((item) => item.status === 'error');
  return (
    <Notice status="warning" isDismissible={false}>
      <strong>{__('Agents cannot run yet.', 'agent-studio')}</strong>{' '}
      {first && first.message}{' '}
      {first && first.action && (
        <a href={first.action.url}>{first.action.label}</a>
      )}{' '}
      <a href={config.urls.abilities}>
        {__('See everything that was checked', 'agent-studio')}
      </a>
    </Notice>
  );
}
