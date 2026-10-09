import {
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** Name, which agent people talk to, and whether approval requests go here. */
export default function BasicsFields({ type, agents, values, onChange }) {
  const set = (key) => (value) => onChange({ ...values, [key]: value });

  return (
    <>
      <TextControl
        __nextHasNoMarginBottom
        __next40pxDefaultSize
        label={__('Name', 'agent-studio')}
        help={__('For you, like “Support bot” or “My phone”.', 'agent-studio')}
        value={values.name}
        onChange={set('name')}
      />
      {type.capabilities.receive && (
        <SelectControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          label={__('Which agent do people talk to here?', 'agent-studio')}
          help={__(
            'It answers with its own instructions and abilities, and acts with the permissions of the user it runs as.',
            'agent-studio',
          )}
          value={String(values.agent_id || '')}
          options={[
            { value: '', label: __('Choose an agent…', 'agent-studio') },
          ].concat(
            agents.map((agent) => ({
              value: String(agent.id),
              label: agent.active
                ? agent.name
                : agent.name + ' ' + __('(paused)', 'agent-studio'),
            })),
          )}
          onChange={(value) => set('agent_id')(Number(value) || 0)}
        />
      )}
      {type.capabilities.notify && (
        <ToggleControl
          __nextHasNoMarginBottom
          label={__('Send me approval requests here', 'agent-studio')}
          help={
            type.capabilities.approve
              ? __(
                  'When an agent needs permission, you get a message with Approve and Reject buttons. Email still comes too.',
                  'agent-studio',
                )
              : __(
                  'When an agent needs permission, you get a message here. Email still comes too.',
                  'agent-studio',
                )
          }
          checked={values.notify}
          onChange={set('notify')}
        />
      )}
    </>
  );
}
