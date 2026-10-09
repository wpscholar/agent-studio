import { useQueryParam } from '../ui/route.js';
import ChannelList from './ChannelList.jsx';
import AddChannel from './AddChannel.jsx';
import ChannelDetail from './ChannelDetail.jsx';

/**
 * Agents → Channels. ?channel=new adds one (?channel=new-telegram picks the
 * kind), ?channel=<id> opens one.
 */
export default function ChannelsScreen() {
  const [view, navigate] = useQueryParam('channel');

  if (view === 'new' || view.startsWith('new-')) {
    return (
      <AddChannel key={view} typeKey={view.slice(4)} navigate={navigate} />
    );
  }
  if (view) {
    return <ChannelDetail key={view} id={view} navigate={navigate} />;
  }
  return <ChannelList navigate={navigate} />;
}
