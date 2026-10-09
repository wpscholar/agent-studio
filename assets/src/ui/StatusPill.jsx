/** A small coloured label: success, warning, error or neutral. */
export default function StatusPill({ tone = 'neutral', children }) {
  return (
    <span className={'agent-studio-status agent-studio-status--' + tone}>
      {children}
    </span>
  );
}
