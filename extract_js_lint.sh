#!/bin/bash
# Extract inline <script> blocks from PHP files and lint them with ESLint
# Usage: bash extract_js_lint.sh

TMPDIR=".eslint_tmp"
rm -rf "$TMPDIR"
mkdir -p "$TMPDIR"
ERRORS=0
WARNINGS=0
FILES_CHECKED=0
FILES_WITH_ISSUES=0

echo "========================================="
echo "  ESLint Check - Inline JS in PHP files"
echo "========================================="
echo ""

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
    if '<script' in stripped and 'src=' not in stripped and '</script>' not in stripped:
        in_script = True
        continue
    if '</script>' in stripped:
        in_script = False
        continue
    if '<script' in stripped and 'src=' in stripped:
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
find . -name "*.php" -type f \
    -not -path "*/vendor/*" \
    -not -path "*/node_modules/*" \
    -not -path "*/.eslint_tmp/*" \
    | sort | while IFS= read -r phpfile; do

    # Let the Python extractor handle filtering
    jsfile="$TMPDIR/$(echo "$phpfile" | sed 's|/|__|g; s|\.php$|.js|')"

    python3 "$TMPDIR/extract.py" "$phpfile" > "$jsfile" 2>/dev/null

    # Skip if no JS was extracted or file is empty
    if [ ! -s "$jsfile" ]; then
        rm -f "$jsfile"
        continue
    fi

    FILES_CHECKED=$((FILES_CHECKED + 1))

    # Run ESLint
    output=$(eslint -c eslint.config.mjs "$jsfile" 2>&1)
    exit_code=$?

    if [ $exit_code -ne 0 ]; then
        FILES_WITH_ISSUES=$((FILES_WITH_ISSUES + 1))
        echo "--- $phpfile ---"
        echo "$output" | sed "s|$jsfile|$phpfile|g"
        echo ""

        file_errors=$(echo "$output" | grep -c " error ")
        file_warnings=$(echo "$output" | grep -c " warning ")
        ERRORS=$((ERRORS + file_errors))
        WARNINGS=$((WARNINGS + file_warnings))
    fi
done

echo "========================================="
echo "  SUMMARY"
echo "========================================="
echo "  Files checked:      $FILES_CHECKED"
echo "  Files with issues:  $FILES_WITH_ISSUES"
echo "  Errors:             $ERRORS"
echo "  Warnings:           $WARNINGS"
echo "========================================="

# Cleanup
rm -rf "$TMPDIR"

if [ $ERRORS -gt 0 ]; then
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    exit 0
else
    echo ""
    echo "  All clean!"
    exit 0
fi
