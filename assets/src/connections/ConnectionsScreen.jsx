import { useQueryParam } from '../ui/route.js';
import ConnectionList from './ConnectionList.jsx';
import AddConnection from './AddConnection.jsx';
import ConnectionDetail from './ConnectionDetail.jsx';

/**
 * Agents → Connections. ?connection=new adds one, ?connection=<id> opens one.
 */
export default function ConnectionsScreen() {
  const [view, navigate] = useQueryParam('connection');

  if (view === 'new') {
    return <AddConnection navigate={navigate} />;
  }
  if (view) {
    return <ConnectionDetail key={view} id={view} navigate={navigate} />;
  }
  return <ConnectionList navigate={navigate} />;
}
