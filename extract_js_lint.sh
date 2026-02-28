#!/bin/bash
# Extract inline <script> blocks from PHP files and lint them with ESLint
# Usage: bash extract_js_lint.sh

TMPDIR=".eslint_tmp"
rm -rf "$TMPDIR"
mkdir -p "$TMPDIR"
trap 'rm -rf "$TMPDIR"' EXIT
ERRORS=0
WARNINGS=0
FILES_CHECKED=0
FILES_WITH_ISSUES=0

echo "========================================="
echo "  ESLint Check - Inline JS in PHP files"
echo "========================================="
echo ""

# Verify required tools
for cmd in python3 eslint; do
    if ! command -v "$cmd" &>/dev/null; then
        echo "ERROR: $cmd is not installed or not in PATH" >&2
        exit 1
    fi
done

# Create the Python extractor
cat > "$TMPDIR/extract.py" << 'PYEOF'
import re, sys

fname = sys.argv[1]
with open(fname, 'r', encoding='utf-8', errors='replace') as f:
    content = f.read()

blocks = []
in_script = False
for line in content.split('\n'):
    stripped = line.strip()
    stripped_lower = stripped.lower()
    # Single-line script block: <script>...</script> on the same line
    if '<script' in stripped_lower and '</script>' in stripped_lower and 'src=' not in stripped_lower:
        m = re.search(r'<script[^>]*>(.*?)</script>', line, re.IGNORECASE)
        if m and m.group(1).strip():
            blocks.append(m.group(1))
        continue
    if '<script' in stripped_lower and 'src=' not in stripped_lower:
        # Capture any JS after the opening tag on the same line
        open_match = re.search(r'<script[^>]*>', line, re.IGNORECASE)
        if open_match:
            after_open = line[open_match.end():]
            if after_open.strip():
                blocks.append(after_open)
        in_script = True
        continue
    if in_script and '</script>' in stripped_lower:
        # Capture any JS before the closing tag on the same line
        close_match = re.search(r'</script>', line, re.IGNORECASE)
        if close_match:
            before_close = line[:close_match.start()]
            if before_close.strip():
                blocks.append(before_close)
        in_script = False
        continue
    if '</script>' in stripped_lower:
        in_script = False
        continue
    if '<script' in stripped_lower and 'src=' in stripped_lower:
        continue
    if in_script:
        # Replace PHP echo inside strings: "<?php echo ...; ?>" -> "__PHP__"
        line = re.sub(r'<\?(?:php|=)\s*[^?]*?\?>', '__PHP__', line)
        # If line still has unclosed PHP, comment it out
        if '<?' in line or '?>' in line:
            line = '// PHP line'
        blocks.append(line)

print('\n'.join(blocks))
PYEOF

# Recursive search, excluding vendor and node_modules
while IFS= read -r phpfile; do

    # Let the Python extractor handle filtering
    jsfile="$TMPDIR/$(echo "$phpfile" | sed 's|/|__|g; s|\.php$|.js|')"

    if ! python3 "$TMPDIR/extract.py" "$phpfile" > "$jsfile"; then
        echo "ERROR: estrazione JS fallita per $phpfile" >&2
        FILES_WITH_ISSUES=$((FILES_WITH_ISSUES + 1))
        ERRORS=$((ERRORS + 1))
        rm -f "$jsfile"
        continue
    fi

    # Skip if no JS was extracted or file is empty
    if [ ! -s "$jsfile" ]; then
        rm -f "$jsfile"
        continue
    fi

    FILES_CHECKED=$((FILES_CHECKED + 1))

    # Run ESLint
    output=$(eslint -c eslint.config.mjs "$jsfile" 2>&1)
    exit_code=$?

    # Exit code 2 = fatal error (config problem, invalid file, etc.)
    if [ $exit_code -eq 2 ]; then
        FILES_WITH_ISSUES=$((FILES_WITH_ISSUES + 1))
        ERRORS=$((ERRORS + 1))
        echo "--- $phpfile [FATAL] ---"
        echo "$output" | sed "s|$jsfile|$phpfile|g"
        echo ""
        continue
    fi

    # Parse counts from ESLint summary line (e.g. "2 problems (1 error, 1 warning)")
    # Always parse regardless of exit_code since warnings have exit_code 0
    file_errors=0
    file_warnings=0
    summary_line=$(echo "$output" | grep -E '[0-9]+ problems?' | tail -n1 || true)
    if [ -n "$summary_line" ]; then
        file_errors=$(echo "$summary_line" | grep -oE '[0-9]+ errors?' | grep -oE '[0-9]+' | head -1)
        file_warnings=$(echo "$summary_line" | grep -oE '[0-9]+ warnings?' | grep -oE '[0-9]+' | head -1)
        file_errors=${file_errors:-0}
        file_warnings=${file_warnings:-0}
    else
        file_errors=$(echo "$output" | grep -c " error " 2>/dev/null || echo 0)
        file_warnings=$(echo "$output" | grep -c " warning " 2>/dev/null || echo 0)
    fi

    file_errors=$((file_errors + 0))
    file_warnings=$((file_warnings + 0))

    ERRORS=$((ERRORS + file_errors))
    WARNINGS=$((WARNINGS + file_warnings))

    if [ $exit_code -ne 0 ] || [ "$file_warnings" -gt 0 ]; then
        FILES_WITH_ISSUES=$((FILES_WITH_ISSUES + 1))
        echo "--- $phpfile ---"
        echo "$output" | sed "s|$jsfile|$phpfile|g"
        echo ""
    fi
done < <(find . -name "*.php" -type f \
    -not -path "*/vendor/*" \
    -not -path "*/node_modules/*" \
    -not -path "*/.eslint_tmp/*" \
    | sort)

echo "========================================="
echo "  SUMMARY"
echo "========================================="
echo "  Files checked:      $FILES_CHECKED"
echo "  Files with issues:  $FILES_WITH_ISSUES"
echo "  Errors:             $ERRORS"
echo "  Warnings:           $WARNINGS"
echo "========================================="

# Cleanup handled by trap EXIT

if [ $ERRORS -gt 0 ]; then
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    exit 0
else
    echo ""
    echo "  All clean!"
    exit 0
fi
