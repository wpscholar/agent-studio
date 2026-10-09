import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../api.js';
import Page from '../ui/Page.jsx';
import EmptyState from '../ui/EmptyState.jsx';
import { timeAgo } from '../ui/format.js';
import ImportModal from './ImportModal.jsx';

function usedByText(usedBy) {
  if (usedBy.length === 0) {
    return __('Not used by any agent yet', 'agent-studio');
  }
  return sprintf(
    /* translators: %d: number of agents */
    _n('Used by %d agent', 'Used by %d agents', usedBy.length, 'agent-studio'),
    usedBy.length,
  );
}

export default function SkillList({ importing, navigate }) {
  const [skills, setSkills] = useState(null);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');

  useEffect(() => {
    api('skills')
      .then(setSkills)
      .catch((e) => setError(e.message));
  }, []);

  const term = search.trim().toLowerCase();
  const shown = (skills || []).filter(
    (skill) =>
      term === '' ||
      skill.name.toLowerCase().includes(term) ||
      skill.description.toLowerCase().includes(term),
  );

  const actions = (
    <>
      <Button variant="secondary" onClick={() => navigate('import')}>
        {__('Import', 'agent-studio')}
      </Button>
      <Button variant="primary" onClick={() => navigate('new')}>
        {__('New skill', 'agent-studio')}
      </Button>
    </>
  );

  const open = (event, slug) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    event.preventDefault();
    navigate(slug);
  };

  return (
    <Page
      title={__('Skills', 'agent-studio')}
      description={__(
        'A skill is a set of instructions any agent can pick up when it needs them, like a recipe card. Agents always see each skill’s “when to use it” line and read the full instructions only when they are relevant, so you can give an agent many skills without slowing it down.',
        'agent-studio',
      )}
      actions={skills && skills.length > 0 ? actions : null}
    >
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      {skills === null && !error && <Spinner />}

      {skills && skills.length === 0 && (
        <EmptyState
          title={__('No skills yet', 'agent-studio')}
          actions={actions}
        >
          <p>
            {__(
              'Write down how something should be done once, then let any agent use it. For example: how to write release notes, how to reply to a refund request, or your house style for headlines.',
              'agent-studio',
            )}
          </p>
          <p>
            {__(
              'Already have a SKILL.md file from another tool? Import it.',
              'agent-studio',
            )}
          </p>
        </EmptyState>
      )}

      {skills && skills.length > 0 && (
        <>
          <div className="agent-studio-toolbar">
            <SearchControl
              __nextHasNoMarginBottom
              value={search}
              onChange={setSearch}
              label={__('Search skills', 'agent-studio')}
              placeholder={__('Search skills', 'agent-studio')}
            />
          </div>
          {shown.length === 0 ? (
            <p>{__('No skills match your search.', 'agent-studio')}</p>
          ) : (
            <ul className="agent-studio-list">
              {shown.map((skill) => (
                <li key={skill.slug} className="agent-studio-list__item">
                  <a
                    className="agent-studio-list__link"
                    href={'?' + linkQuery(skill.slug)}
                    onClick={(event) => open(event, skill.slug)}
                  >
                    <span className="agent-studio-list__title">
                      {skill.name}
                    </span>
                    <span className="agent-studio-list__text">
                      {skill.description}
                    </span>
                    <span className="agent-studio-list__meta">
                      {usedByText(skill.usedBy)}
                      {skill.modified > 0 &&
                        ' · ' +
                          sprintf(
                            /* translators: %s: relative time, like "3 days ago" */
                            __('Updated %s', 'agent-studio'),
                            timeAgo(skill.modified),
                          )}
                    </span>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </>
      )}

      {importing && (
        <ImportModal
          onClose={() => navigate('')}
          onImported={(slug) => navigate(slug)}
        />
      )}
    </Page>
  );
}

function linkQuery(slug) {
  const params = new URLSearchParams(window.location.search);
  params.set('skill', slug);
  return params.toString();
}
