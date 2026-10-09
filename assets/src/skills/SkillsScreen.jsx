import { useQueryParam } from '../ui/route.js';
import SkillList from './SkillList.jsx';
import SkillEditor from './SkillEditor.jsx';

/**
 * Agents → Skills. ?skill=new adds one, ?skill=import opens the importer,
 * ?skill=<id> opens a skill, and no value shows the list.
 */
export default function SkillsScreen() {
  const [view, navigate] = useQueryParam('skill');

  if (view === 'new') {
    return <SkillEditor key="new" navigate={navigate} />;
  }
  if (view && view !== 'import') {
    return <SkillEditor key={view} slug={view} navigate={navigate} />;
  }
  return <SkillList importing={view === 'import'} navigate={navigate} />;
}
