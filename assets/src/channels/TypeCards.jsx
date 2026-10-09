import { __ } from '@wordpress/i18n';
import { capabilityChips } from './status.js';

/** The kinds of channel as cards to pick from. */
export default function TypeCards({ types, onPick }) {
  return (
    <div className="agent-studio-type-cards">
      {types.map((type) => (
        <button
          type="button"
          key={type.key}
          className="agent-studio-type-card"
          onClick={() => onPick(type.key)}
        >
          <span className="agent-studio-type-card__title">{type.label}</span>
          <span className="agent-studio-type-card__text">
            {type.description}
          </span>
          <span className="agent-studio-type-card__chips">
            {capabilityChips(type.capabilities).map((chip) => (
              <span key={chip} className="agent-studio-chip">
                {chip}
              </span>
            ))}
          </span>
          <span className="agent-studio-type-card__cta">
            {__('Set up', 'agent-studio')} →
          </span>
        </button>
      ))}
    </div>
  );
}
