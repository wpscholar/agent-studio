import { __ } from '@wordpress/i18n';

export function channelStatus(channel) {
  switch (channel.status) {
    case 'active':
      return { tone: 'success', label: __('On', 'agent-studio') };
    case 'off':
      return { tone: 'neutral', label: __('Off', 'agent-studio') };
    case 'problem':
      return { tone: 'error', label: __('Problem', 'agent-studio') };
    default:
      return { tone: 'neutral', label: __('Not set up', 'agent-studio') };
  }
}

/** What the channel does, in a few words. */
export function channelPurpose(channel) {
  if (channel.capabilities && channel.capabilities.receive && channel.agent) {
    return channel.agent.name;
  }
  return __('Notifications', 'agent-studio');
}

export function capabilityChips(capabilities) {
  const chips = [];
  if (capabilities.receive) {
    chips.push(__('Chat with an agent', 'agent-studio'));
  }
  if (capabilities.notify) {
    chips.push(__('Notifications', 'agent-studio'));
  }
  if (capabilities.approve) {
    chips.push(__('Approve from the app', 'agent-studio'));
  }
  return chips;
}
