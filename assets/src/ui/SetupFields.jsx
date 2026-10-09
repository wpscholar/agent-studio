import {
  SelectControl,
  TextControl,
  TextareaControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function visible(field, values) {
  return Object.entries(field.showWhen || {}).every(
    ([key, value]) => (values[key] ?? '') === value,
  );
}

/**
 * Draws setup fields described by the server (see Domain\Setup\SetupField),
 * so connection and channel types from other plugins get a form for free.
 * Secret fields are never filled in: a saved value shows as "saved".
 */
export default function SetupFields({
  fields,
  values,
  onChange,
  savedSecrets = [],
}) {
  const set = (key) => (value) => onChange({ ...values, [key]: value });

  return fields
    .filter((field) => visible(field, values))
    .map((field) => {
      const common = {
        key: field.key,
        label:
          field.label +
          (field.required ? '' : ' ' + __('(optional)', 'agent-studio')),
        help: field.help,
        value: values[field.key] ?? field.default ?? '',
        onChange: set(field.key),
        __nextHasNoMarginBottom: true,
      };

      switch (field.type) {
        case 'textarea':
          return (
            <TextareaControl
              {...common}
              rows={4}
              placeholder={field.placeholder}
            />
          );
        case 'select':
          return (
            <SelectControl
              {...common}
              __next40pxDefaultSize
              options={Object.entries(field.options).map(([value, label]) => ({
                value,
                label,
              }))}
            />
          );
        case 'toggle':
          return (
            <ToggleControl
              key={field.key}
              __nextHasNoMarginBottom
              label={field.label}
              help={field.help}
              checked={values[field.key] === '1'}
              onChange={(checked) => set(field.key)(checked ? '1' : '')}
            />
          );
        case 'secret':
          return (
            <TextControl
              {...common}
              __next40pxDefaultSize
              type="password"
              autoComplete="off"
              placeholder={
                savedSecrets.includes(field.key)
                  ? __('Saved. Leave empty to keep it.', 'agent-studio')
                  : field.placeholder
              }
            />
          );
        default:
          return (
            <TextControl
              {...common}
              __next40pxDefaultSize
              type={field.type === 'url' ? 'url' : 'text'}
              placeholder={field.placeholder}
            />
          );
      }
    });
}
