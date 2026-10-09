import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** Asks before something that cannot be undone. */
export default function ConfirmDialog({
  title,
  children,
  confirmLabel,
  isDestructive = false,
  isBusy = false,
  onConfirm,
  onCancel,
}) {
  return (
    <Modal title={title} onRequestClose={onCancel} size="small">
      <div className="agent-studio-confirm">{children}</div>
      <div className="agent-studio-modal-actions">
        <Button variant="tertiary" onClick={onCancel} disabled={isBusy}>
          {__('Cancel', 'agent-studio')}
        </Button>
        <Button
          variant="primary"
          isDestructive={isDestructive}
          isBusy={isBusy}
          disabled={isBusy}
          onClick={onConfirm}
        >
          {confirmLabel}
        </Button>
      </div>
    </Modal>
  );
}
