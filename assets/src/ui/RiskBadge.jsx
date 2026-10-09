import { __ } from '@wordpress/i18n';

const LABELS = {
  read: () => [
    __('Read', 'agent-studio'),
    __('Reads data and changes nothing.', 'agent-studio'),
  ],
  write: () => [
    __('Write', 'agent-studio'),
    __('Creates or changes data.', 'agent-studio'),
  ],
  destructive: () => [
    __('Destructive', 'agent-studio'),
    __(
      'Deletes data or changes it in a way that is hard to undo.',
      'agent-studio',
    ),
  ],
  external: () => [
    __('External', 'agent-studio'),
    __('Has an effect outside the site.', 'agent-studio'),
  ],
};

/** The same risk words the agent editor uses, with the explanation on hover. */
export default function RiskBadge({ risk }) {
  const [label, help] = (LABELS[risk] || LABELS.write)();
  return (
    <span
      className={'agent-studio-risk agent-studio-risk--' + risk}
      title={help}
    >
      {label}
    </span>
  );
}
