import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** A read-only value with a Copy button, for things people paste elsewhere. */
export default function CopyField({ label, value, help }) {
  const [copied, setCopied] = useState(false);

  const copy = () => {
    const done = () => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(done);
    }
  };

  return (
    <div className="agent-studio-copy">
      <span className="agent-studio-copy__label">{label}</span>
      <div className="agent-studio-copy__row">
        <input
          type="text"
          readOnly
          value={value}
          onFocus={(e) => e.target.select()}
        />
        <Button variant="secondary" onClick={copy}>
          {copied ? __('Copied', 'agent-studio') : __('Copy', 'agent-studio')}
        </Button>
      </div>
      {help && <p className="agent-studio-muted">{help}</p>}
    </div>
  );
}
