/** What a list shows before anything has been added: what it is, and how to start. */
export default function EmptyState({ title, children, actions }) {
  return (
    <div className="agent-studio-empty">
      <h2>{title}</h2>
      <div className="agent-studio-empty__body">{children}</div>
      {actions && <div className="agent-studio-empty__actions">{actions}</div>}
    </div>
  );
}
