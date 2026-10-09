import { useState } from '@wordpress/element';
import {
  Button,
  FormFileUpload,
  Modal,
  Notice,
  TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import { useNotify } from '../ui/notices.jsx';

const EXAMPLE = `---
name: Release notes writer
description: Use when turning a list of changes into release notes.
---

Write the notes in plain language…`;

/**
 * Brings in a SKILL.md: choose a file or paste it, check what it contains, then import.
 */
export default function ImportModal({ onClose, onImported }) {
  const notify = useNotify();
  const [markdown, setMarkdown] = useState('');
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const readFile = (event) => {
    const file = event.target.files && event.target.files[0];
    if (!file) {
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      setMarkdown(String(reader.result || ''));
      setPreview(null);
      setError('');
    };
    reader.readAsText(file);
  };

  const send = (dryRun) => {
    setBusy(true);
    setError('');
    return api('skills/import', {
      method: 'POST',
      data: { markdown, dry_run: dryRun },
    })
      .then((result) => {
        if (dryRun) {
          setPreview(result);
          return;
        }
        notify(
          sprintf(
            /* translators: %s: skill name */
            __('Imported “%s”.', 'agent-studio'),
            result.skill.name,
          ),
        );
        onImported(result.skill.slug);
      })
      .catch((e) => setError(e.message))
      .finally(() => setBusy(false));
  };

  return (
    <Modal
      title={__('Import a skill', 'agent-studio')}
      onRequestClose={onClose}
      size="medium"
    >
      {!preview ? (
        <>
          <p>
            {__(
              'Skills are often shared as a SKILL.md file: a short header with a name and a description of when to use it, followed by the instructions. Choose the file, or paste its text below.',
              'agent-studio',
            )}
          </p>
          <FormFileUpload
            accept=".md,.markdown,.txt,text/markdown,text/plain"
            onChange={readFile}
            render={({ openFileDialog }) => (
              <Button variant="secondary" onClick={openFileDialog}>
                {__('Choose a file…', 'agent-studio')}
              </Button>
            )}
          />
          <TextareaControl
            __nextHasNoMarginBottom
            className="agent-studio-code-field agent-studio-gap-top"
            label={__('Or paste it here', 'agent-studio')}
            rows={12}
            value={markdown}
            placeholder={EXAMPLE}
            onChange={(value) => {
              setMarkdown(value);
              setError('');
            }}
          />
        </>
      ) : (
        <div className="agent-studio-import-preview">
          <p>{__('This is what will be imported:', 'agent-studio')}</p>
          <dl>
            <dt>{__('Name', 'agent-studio')}</dt>
            <dd>{preview.skill.name}</dd>
            <dt>{__('When to use it', 'agent-studio')}</dt>
            <dd>
              {preview.skill.description || __('(none given)', 'agent-studio')}
            </dd>
            <dt>{__('Instructions', 'agent-studio')}</dt>
            <dd>
              <pre className="agent-studio-pre">
                {preview.skill.instructions}
              </pre>
            </dd>
          </dl>
          {preview.exists && (
            <Notice status="warning" isDismissible={false}>
              {__(
                'You already have a skill with this name. Importing replaces its name, description and instructions. The current version stays in its history, so you can go back.',
                'agent-studio',
              )}
            </Notice>
          )}
        </div>
      )}

      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      <div className="agent-studio-modal-actions">
        {preview ? (
          <>
            <Button variant="tertiary" onClick={() => setPreview(null)}>
              {__('Back', 'agent-studio')}
            </Button>
            <Button
              variant="primary"
              isBusy={busy}
              disabled={busy}
              onClick={() => send(false)}
            >
              {preview.exists
                ? __('Replace skill', 'agent-studio')
                : __('Import skill', 'agent-studio')}
            </Button>
          </>
        ) : (
          <>
            <Button variant="tertiary" onClick={onClose}>
              {__('Cancel', 'agent-studio')}
            </Button>
            <Button
              variant="primary"
              isBusy={busy}
              disabled={busy || markdown.trim() === ''}
              onClick={() => send(true)}
            >
              {__('Continue', 'agent-studio')}
            </Button>
          </>
        )}
      </div>
    </Modal>
  );
}
