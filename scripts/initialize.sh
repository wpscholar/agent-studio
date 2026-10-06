#!/usr/bin/env bash
set -euo pipefail

echo "Initialize new plugin from template"
read -p "Plugin slug (kebab-case, e.g. my-plugin): " slug
read -p "Plugin display name (e.g. 'My Plugin'): " name
read -p "PHP namespace (PascalCase, e.g. MyPlugin): " namespace
read -p "Plugin description: " description

upper=$(echo "$namespace" | tr '[:lower:]' '[:upper:]')

# Replace placeholders in files
find . -type f \
  -not -path './.git/*' \
  -not -path './node_modules/*' \
  -not -path './vendor/*' \
  -not -path './build/*' \
  -not -name 'initialize.sh' \
  -exec sed -i.bak \
    -e "s|wp-agents|$slug|g" \
    -e "s|WP Agents|$name|g" \
    -e "s|WPAgents|$namespace|g" \
    -e "s|WP_AGENTS|$upper|g" \
    -e "s|Build AI agents inside WordPress. Trigger them from site events, webhooks or direct calls, and give them your site abilities and skills.|$description|g" {} +
find . -name '*.bak' -delete

# Rename plugin main file
if [ -f "wp-agents.php" ]; then mv "wp-agents.php" "$slug.php"; fi

# Symlink CLAUDE.md -> AGENTS.md (replace placeholder file)
if [ -L CLAUDE.md ]; then rm CLAUDE.md; fi
if [ -f CLAUDE.md ]; then rm CLAUDE.md; fi
ln -s AGENTS.md CLAUDE.md

echo "Done. Review the diff, commit, and start coding."
