import {
  createContext,
  useCallback,
  useContext,
  useState,
} from '@wordpress/element';
import { SnackbarList } from '@wordpress/components';

const NoticesContext = createContext(() => {});

/** Short confirmations ("Skill saved.") that appear at the bottom of the screen. */
export function NoticesProvider({ children }) {
  const [notices, setNotices] = useState([]);

  const notify = useCallback((content) => {
    const id = String(Date.now() + Math.random());
    setNotices((list) => [...list, { id, content }]);
  }, []);

  const remove = (id) =>
    setNotices((list) => list.filter((notice) => notice.id !== id));

  return (
    <NoticesContext.Provider value={notify}>
      {children}
      <SnackbarList
        className="agent-studio-snackbars"
        notices={notices}
        onRemove={remove}
      />
    </NoticesContext.Provider>
  );
}

export function useNotify() {
  return useContext(NoticesContext);
}
