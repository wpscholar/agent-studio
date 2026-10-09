import { NoticesProvider } from './ui/notices.jsx';
import SkillsScreen from './skills/SkillsScreen.jsx';
import ConnectionsScreen from './connections/ConnectionsScreen.jsx';
import ChannelsScreen from './channels/ChannelsScreen.jsx';

const screens = {
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
