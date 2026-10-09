import { Fragment } from '@wordpress/element';

/** Plain text with any https:// addresses turned into links that open in a new tab. */
export default function Linkify({ text }) {
  const parts = text.split(/(https?:\/\/[^\s)]+)/g);
  return parts.map((part, index) => {
    if (!/^https?:\/\//.test(part)) {
      return part;
    }
    // A full stop or comma right after an address belongs to the sentence.
    const trailing = /[.,]$/.test(part) ? part.slice(-1) : '';
    const url = trailing ? part.slice(0, -1) : part;
    return (
      <Fragment key={index}>
        <a href={url} target="_blank" rel="noreferrer">
          {url}
        </a>
        {trailing}
      </Fragment>
    );
  });
}
