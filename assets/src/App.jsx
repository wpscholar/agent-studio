import { NoticesProvider } from './ui/notices.jsx';
import AgentsScreen from './agents/AgentsScreen.jsx';
import AbilitiesScreen from './abilities/AbilitiesScreen.jsx';
import SkillsScreen from './skills/SkillsScreen.jsx';
import ConnectionsScreen from './connections/ConnectionsScreen.jsx';
import ChannelsScreen from './channels/ChannelsScreen.jsx';

const screens = {
  agents: AgentsScreen,
  abilities: AbilitiesScreen,
  skills: SkillsScreen,
  connections: ConnectionsScreen,
  channels: ChannelsScreen,
};

export default function App({ screen }) {
  const Screen = screens[screen];
  if (!Screen) {
    return null;
  }
  return (
    <NoticesProvider>
      <Screen />
    </NoticesProvider>
  );
}
