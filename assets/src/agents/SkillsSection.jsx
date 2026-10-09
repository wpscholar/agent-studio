import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api, config } from '../api.js';
import Picker, { pageLocally } from '../ui/Picker.jsx';

/** "Skills": reusable instructions this agent can open when it needs them. */
export default function SkillsSection({ chosen, details, onChange }) {
  const [picking, setPicking] = useState(false);
  const [all, setAll] = useState(null);

  const load = (query) =>
    (all
      ? Promise.resolve(all)
      : api('skills').then((list) => {
          const items = list.map((skill) => ({
            slug: skill.slug,
            name: skill.name,
            description: skill.description,
          }));
          setAll(items);
          return items;
        })
    ).then((items) => pageLocally(items, query));

  const toggle = (item, on) =>
    onChange(
      on ? [...chosen, item.slug] : chosen.filter((slug) => slug !== item.slug),
      item,
    );

  return (
    <Card>
      <CardHeader>
        <div>
          <h2>{__('Skills', 'agent-studio')}</h2>
          <p className="agent-studio-card-intro">
            {__(
              'Skills are instructions you write once and share between agents, like your house style or how to handle a refund. The agent reads a skill only when it is relevant, so adding several does not slow it down.',
              'agent-studio',
            )}
          </p>
        </div>
      </CardHeader>
      <CardBody className="agent-studio-fields">
        {chosen.length === 0 ? (
          <p className="agent-studio-muted">
            {__(
              'This agent has no skills. That is fine for simple jobs.',
              'agent-studio',
            )}
          </p>
        ) : (
          <ul className="agent-studio-chosen">
            {chosen.map((slug) => {
              const skill = details[slug] || {
                slug,
                name: slug,
                missing: true,
              };
              return (
                <li
                  key={slug}
                  className={
                    'agent-studio-chosen__item' +
                    (skill.missing ? ' is-missing' : '')
                  }
                >
                  <div className="agent-studio-chosen__text">
                    <strong>{skill.name}</strong>
                    {skill.missing ? (
                      <span className="agent-studio-warning-text">
                        {__(
                          'This skill was deleted. Remove it.',
                          'agent-studio',
                        )}
                      </span>
                    ) : (
                      <span className="agent-studio-muted agent-studio-clamp">
                        {skill.description}
                      </span>
                    )}
                  </div>
                  <Button
                    variant="tertiary"
                    size="compact"
                    isDestructive
                    onClick={() => toggle(skill, false)}
                    label={sprintf(
                      /* translators: %s: skill name */
                      __('Remove “%s”', 'agent-studio'),
                      skill.name,
                    )}
                  >
                    {__('Remove', 'agent-studio')}
                  </Button>
                </li>
              );
            })}
          </ul>
        )}
        <div className="agent-studio-button-row">
          <Button variant="secondary" onClick={() => setPicking(true)}>
            {__('Choose skills', 'agent-studio')}
          </Button>
          <a href={config.urls.skills}>
            {__('Write a new skill', 'agent-studio')}
          </a>
        </div>
      </CardBody>

      {picking && (
        <Picker
          multiple
          title={__('Choose skills', 'agent-studio')}
          searchLabel={__('Search skills', 'agent-studio')}
          load={load}
          itemKey={(item) => item.slug}
          selected={chosen}
          onToggle={toggle}
          emptyText={__(
            'No skills found. Write one on the Skills screen.',
            'agent-studio',
          )}
          renderItem={(item) => (
            <span className="agent-studio-picker__text">
              <strong>{item.name}</strong>
              <span className="agent-studio-muted agent-studio-clamp">
                {item.description}
              </span>
            </span>
          )}
          onClose={() => setPicking(false)}
        />
      )}
    </Card>
  );
}
