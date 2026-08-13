#!/usr/bin/env bash
# Warns when a functionality change is about to be left without its CLAUDE.md entry.
# Warns only: see the Conventions section for why the rule is a convention with a reminder
# rather than a gate. Nothing here blocks a commit or a turn.

changed=$(git diff HEAD --name-only 2>/dev/null; git ls-files --others --exclude-standard 2>/dev/null)

[ -z "$changed" ] && exit 0

printf '%s\n' "$changed" | grep -qE '^(app|resources/js|routes|database/migrations)/' || exit 0
printf '%s\n' "$changed" | grep -qx 'CLAUDE.md' && exit 0

printf '%s' '{"systemMessage":"CLAUDE.md was not touched, but this change was: app/, resources/js/, routes/ or a migration. Per the Conventions section, the documentation belongs in the same commit — add, edit or delete the paragraph this affects, or say why none applies."}'
