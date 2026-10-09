import { useQueryParam } from '../ui/route.js';
import AgentList from './AgentList.jsx';
import NewAgent from './NewAgent.jsx';
import AgentEditor from './AgentEditor.jsx';

/**
 * Agents → All agents. ?agent=new chooses how to start, ?agent=new:<template>
 * (or new:blank) opens the editor for a new agent, ?agent=<id> opens one, and
 * no value shows the list.
 */
export default function AgentsScreen() {
  const [view, navigate] = useQueryParam('agent');

  if (view === 'new') {
    return <NewAgent navigate={navigate} />;
  }
  if (view.startsWith('new:')) {
    const template = view.slice(4);
    return (
      <AgentEditor
        key={view}
        template={template === 'blank' ? '' : template}
        navigate={navigate}
      />
    );
  }
  if (view) {
    return <AgentEditor key={view} id={view} navigate={navigate} />;
  }
  return <AgentList navigate={navigate} />;
}
