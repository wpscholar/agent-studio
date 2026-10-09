import { NoticesProvider } from './ui/notices.jsx';
import SkillsScreen from './skills/SkillsScreen.jsx';
import ConnectionsScreen from './connections/ConnectionsScreen.jsx';

const screens = {
  skills: SkillsScreen,
  connections: ConnectionsScreen,
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
