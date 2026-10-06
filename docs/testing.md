# Testing

## Unit tests (no WordPress)

Domain and Application code is plain PHP and is tested without WordPress.

```bash
composer test:unit
```

## Integration tests (real WordPress)

These wire the real repositories and the real Abilities API and fake only the AI model.

```bash
bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
composer test:integration
```

## Static analysis

```bash
composer analyze
```

## Lint

```bash
composer lint        # PHPCS (PSR-12 with selected WordPress sniffs)
composer lint:fix    # auto-fix
composer compat      # PHPCompatibilityWP
npm run lint         # ESLint
npm run format:check # Prettier check
```

## E2E and visual

```bash
npx playwright install --with-deps
npm run test:e2e
npm run test:visual
```

## CI matrix

WordPress: 7.0, 7.1. PHP: 8.2, 8.3, 8.4, 8.5. The plugin requires WordPress 7.0 for the Abilities API and AI Client. Adjust in `.github/workflows/ci.yml` when the floor changes.
