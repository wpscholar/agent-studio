import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Standard screen frame: a back link, the title, one or two sentences that
 * explain the screen, and the main actions on the right.
 */
export default function Page({ title, description, actions, back, children }) {
  return (
    <div className="agent-studio-page">
      {back && (
        <Button
          variant="link"
          className="agent-studio-page__back"
          onClick={back.onClick}
        >
          ← {back.label || __('Back', 'agent-studio')}
        </Button>
      )}
      <div className="agent-studio-page__header">
        <div>
          <h1 className="agent-studio-page__title">{title}</h1>
          {description && (
            <p className="agent-studio-page__description">{description}</p>
          )}
        </div>
        {actions && <div className="agent-studio-page__actions">{actions}</div>}
      </div>
      {children}
    </div>
  );
}
