import { createRoot } from '@wordpress/element';
import '../admin.js';
import App from './App.jsx';

function mount() {
  const root = document.getElementById('agent-studio-app');
  if (root) {
    createRoot(root).render(<App screen={root.dataset.screen} />);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount);
} else {
  mount();
}
