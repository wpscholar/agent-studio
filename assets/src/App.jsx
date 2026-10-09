import { NoticesProvider } from './ui/notices.jsx';
import SkillsScreen from './skills/SkillsScreen.jsx';

const screens = {
  skills: SkillsScreen,
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
