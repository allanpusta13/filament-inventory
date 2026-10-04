#!/usr/bin/env bash
# Runs when this manager finishes responding (Stop hook).
# If UPDATES.md changed since the last ping, posts a short Discord webhook message.
# Sends NO content: only the folder name. Silent if no webhook is set.
[ -n "$SECRETARY_RUN" ] && exit 0            # the secretary already gets the reply directly
[ -z "$DISCORD_WEBHOOK_URL" ] && exit 0      # webhook URL lives in your shell env, never in this repo
dir="${CLAUDE_PROJECT_DIR:-$(pwd)}"
f="$dir/UPDATES.md"; state="$dir/.notify-state"
[ -f "$f" ] || exit 0
cur=$(cksum < "$f" | cut -d' ' -f1)
[ "$cur" = "$(cat "$state" 2>/dev/null)" ] && exit 0
echo "$cur" > "$state"
name=$(basename "$dir")
curl -s -m 5 -H "Content-Type: application/json" \
  -d "{\"content\":\"$name has a new update. Ask the secretary: any updates?\"}" \
  "$DISCORD_WEBHOOK_URL" >/dev/null 2>&1 || true
exit 0
