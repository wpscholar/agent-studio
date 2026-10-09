import { useEffect, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';

/** How to start a new agent: from scratch, or from a ready-made example. */
export default function NewAgent({ navigate }) {
  const [templates, setTemplates] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api('agent-templates')
      .then(setTemplates)
      .catch((e) => setError(e.message));
  }, []);

  const card = (key, title, text, cta) => (
    <button
      key={key}
      type="button"
      className="agent-studio-type-card"
      onClick={() => navigate('new:' + key)}
    >
      <span className="agent-studio-type-card__title">{title}</span>
      <span className="agent-studio-type-card__text">{text}</span>
      <span className="agent-studio-type-card__cta">{cta}</span>
    </button>
  );

  return (
    <Page
      title={__('New agent', 'agent-studio')}
      description={__(
        'Start from scratch, or from a working example you can change. Either way the agent starts as a draft: nothing runs until you switch it on.',
        'agent-studio',
      )}
      back={{
        label: __('All agents', 'agent-studio'),
        onClick: () => navigate(''),
      }}
    >
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      <div className="agent-studio-type-cards">
        {card(
          'blank',
          __('Start from scratch', 'agent-studio'),
          __(
            'An empty agent. You write what it should do and choose when it runs.',
            'agent-studio',
          ),
          __('Start →', 'agent-studio'),
        )}
        {templates === null && !error && <Spinner />}
        {(templates || []).map((template) =>
          card(
            template.key,
            template.title,
            template.summary,
            __('Use this example →', 'agent-studio'),
          ),
        )}
      </div>
    </Page>
  );
}
