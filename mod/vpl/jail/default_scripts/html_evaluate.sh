#!/bin/bash
# This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
# Generic evaluator for Web Technologies labs (HTML / CSS / XHTML / XML)
# Auto-selected by VPL for activities with language set to "html"
#
# Per-lab grading rules live in vpl_checks.conf uploaded via Moodle UI —
# no code change or commit is needed when adding a new lab activity.
#
# ─── vpl_checks.conf format ────────────────────────────────────────────
#
#   # Lines starting with # are comments
#
#   check       = Description :: grep_pattern          :: points
#   check_css   = Description :: grep_pattern          :: points
#   check_notag = Description :: grep_pattern          :: points   (passes if pattern NOT found; case-sensitive)
#   check_count = Description :: grep_pattern :: min   :: points
#   check_file  = Description :: file_ext              :: points
#   check_xmllint     = Description                        :: points
#   check_xmllint_valid = Description                      :: points          (validates against DTD declared inside the file)
#   check_xmllint_dtd = Description :: file.dtd|auto       :: points          (validates against external DTD; auto finds first .dtd)
#   check_xmllint_xsd = Description :: file.xsd|auto       :: points          (validates against XSD schema; auto finds first .xsd)
#
#   Delimiter is " :: " (space-colon-colon-space).
#   grep_pattern uses Extended Regular Expressions (ERE).
#   check / check_css / check_count use case-insensitive matching (-iE).
#   check_notag uses case-sensitive matching (-E) so <[A-Z] detects uppercase tags.

CONFIG="vpl_checks.conf"
SCORE=0
TOTAL=0

# ── Find the main submitted file ──────────────────────────────────────
find_main_file() {
    local f
    for ext in html htm xhtml xml; do
        f=$(ls *."$ext" 2>/dev/null | grep -iv "^vpl_" | head -1)
        [ -n "$f" ] && echo "$f" && return
    done
}

# ── Collect all CSS: inline style attrs + <style> blocks + .css files ─
collect_css() {
    local src="$1"
    grep -oi 'style="[^"]*"' "$src" 2>/dev/null
    sed -n '/<style/I,/<\/style>/Ip' "$src" 2>/dev/null
    for f in $(ls *.css 2>/dev/null | grep -iv "^vpl_"); do
        cat "$f" 2>/dev/null
    done
}

# ── Extract Nth field from a string split on " :: " ───────────────────
field() { echo "$1" | awk -v n="$2" -F' *:: *' '{print $n}' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'; }

# ── Guard: no config ──────────────────────────────────────────────────
if [ ! -f "$CONFIG" ]; then
    echo "ERROR: vpl_checks.conf not found."
    echo "Upload vpl_checks.conf in the VPL activity Execution files tab."
    echo "Grade :=>> 0"
    exit 0
fi

# ── Guard: no student file ────────────────────────────────────────────
MAIN=$(find_main_file)
if [ -z "$MAIN" ]; then
    echo "ERROR: No HTML, XHTML, or XML file found in submission."
    echo "Grade :=>> 0"
    exit 0
fi

CSS=$(collect_css "$MAIN")

echo "Evaluating: $MAIN"
echo "============================================"
echo ""

# ── Process checks ────────────────────────────────────────────────────
while IFS= read -r raw; do
    [[ "$raw" =~ ^[[:space:]]*$  ]] && continue
    [[ "$raw" =~ ^[[:space:]]*\# ]] && continue

    TYPE=$(echo "$raw" | cut -d'=' -f1 | tr -d '[:space:]')
    REST=$(echo "$raw" | cut -d'=' -f2-)

    case "$TYPE" in

      check)
        DESC=$(field "$REST" 1); PAT=$(field "$REST" 2); PTS=$(field "$REST" 3 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        if grep -qiE "$PAT" "$MAIN" 2>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s\n" "$PTS" "$DESC"
        else
            printf "[  0 ]  Missing : %s\n" "$DESC"
        fi
        ;;

      check_css)
        DESC=$(field "$REST" 1); PAT=$(field "$REST" 2); PTS=$(field "$REST" 3 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        if echo "$CSS" | grep -qiE "$PAT" 2>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s\n" "$PTS" "$DESC"
        else
            printf "[  0 ]  Missing : %s\n" "$DESC"
        fi
        ;;

      check_notag)
        # Case-sensitive: intended for patterns like <[A-Z] to detect uppercase tags
        DESC=$(field "$REST" 1); PAT=$(field "$REST" 2); PTS=$(field "$REST" 3 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        if ! grep -qE "$PAT" "$MAIN" 2>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s\n" "$PTS" "$DESC"
        else
            FOUND=$(grep -oE "$PAT" "$MAIN" | head -3 | tr '\n' ' ')
            printf "[  0 ]  Failed  : %s  (found: %s)\n" "$DESC" "$FOUND"
        fi
        ;;

      check_count)
        DESC=$(field "$REST" 1); PAT=$(field "$REST" 2)
        MIN=$(field "$REST" 3 | tr -d '[:space:]'); PTS=$(field "$REST" 4 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        CNT=$(grep -oiE "$PAT" "$MAIN" 2>/dev/null | wc -l)
        if [ "$CNT" -ge "$MIN" ]; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (%s found)\n" "$PTS" "$DESC" "$CNT"
        else
            printf "[  0 ]  Missing : %s  (found %s, need >= %s)\n" "$DESC" "$CNT" "$MIN"
        fi
        ;;

      check_file)
        DESC=$(field "$REST" 1); EXT=$(field "$REST" 2 | tr -d '[:space:]'); PTS=$(field "$REST" 3 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        FOUND_FILE=$(ls *."$EXT" 2>/dev/null | grep -iv "^vpl_" | head -1)
        if [ -n "$FOUND_FILE" ]; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (%s)\n" "$PTS" "$DESC" "$FOUND_FILE"
        else
            printf "[  0 ]  Missing : %s  (no .%s file submitted)\n" "$DESC" "$EXT"
        fi
        ;;

      check_xmllint)
        DESC=$(field "$REST" 1); PTS=$(field "$REST" 2 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        if ! command -v xmllint &>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (xmllint unavailable — skipped)\n" "$PTS" "$DESC"
        elif xmllint --noout "$MAIN" 2>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s\n" "$PTS" "$DESC"
        else
            ERRS=$(xmllint --noout "$MAIN" 2>&1 | head -3 | tr '\n' ' ')
            printf "[  0 ]  Failed  : %s\n         %s\n" "$DESC" "$ERRS"
        fi
        ;;

      check_xmllint_valid)
        # Validates against the DTD declared inline in the file's own DOCTYPE (--valid)
        DESC=$(field "$REST" 1); PTS=$(field "$REST" 2 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        if ! command -v xmllint &>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (xmllint unavailable — skipped)\n" "$PTS" "$DESC"
        elif xmllint --valid --noout "$MAIN" 2>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s\n" "$PTS" "$DESC"
        else
            ERRS=$(xmllint --valid --noout "$MAIN" 2>&1 | head -3 | tr '\n' ' ')
            printf "[  0 ]  Failed  : %s\n         %s\n" "$DESC" "$ERRS"
        fi
        ;;

      check_xmllint_dtd)
        # Validates against an external DTD: check_xmllint_dtd = Desc :: file.dtd|auto :: points
        # "auto" finds the first .dtd file in the submission
        DESC=$(field "$REST" 1); DTD=$(field "$REST" 2); PTS=$(field "$REST" 3 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        [ "$DTD" = "auto" ] || [ -z "$DTD" ] && DTD=$(ls *.dtd 2>/dev/null | grep -iv "^vpl_" | head -1)
        if [ -z "$DTD" ] || [ ! -f "$DTD" ]; then
            printf "[  0 ]  Failed  : %s  (no .dtd file found — submit a DTD file)\n" "$DESC"
        elif ! command -v xmllint &>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (xmllint unavailable — skipped)\n" "$PTS" "$DESC"
        elif xmllint --dtdvalid "$DTD" --noout "$MAIN" 2>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (validated against %s)\n" "$PTS" "$DESC" "$DTD"
        else
            ERRS=$(xmllint --dtdvalid "$DTD" --noout "$MAIN" 2>&1 | head -3 | tr '\n' ' ')
            printf "[  0 ]  Failed  : %s\n         %s\n" "$DESC" "$ERRS"
        fi
        ;;

      check_xmllint_xsd)
        # Validates against an XSD schema: check_xmllint_xsd = Desc :: file.xsd|auto :: points
        # "auto" finds the first .xsd file in the submission
        DESC=$(field "$REST" 1); XSD=$(field "$REST" 2); PTS=$(field "$REST" 3 | tr -d '[:space:]')
        [ -z "$PTS" ] && continue
        TOTAL=$((TOTAL + PTS))
        [ "$XSD" = "auto" ] || [ -z "$XSD" ] && XSD=$(ls *.xsd 2>/dev/null | grep -iv "^vpl_" | head -1)
        if [ -z "$XSD" ] || [ ! -f "$XSD" ]; then
            printf "[  0 ]  Failed  : %s  (no .xsd file found — submit an XSD schema file)\n" "$DESC"
        elif ! command -v xmllint &>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (xmllint unavailable — skipped)\n" "$PTS" "$DESC"
        elif xmllint --schema "$XSD" --noout "$MAIN" 2>/dev/null; then
            SCORE=$((SCORE + PTS))
            printf "[+%3s]  %s  (validated against %s)\n" "$PTS" "$DESC" "$XSD"
        else
            ERRS=$(xmllint --schema "$XSD" --noout "$MAIN" 2>&1 | head -3 | tr '\n' ' ')
            printf "[  0 ]  Failed  : %s\n         %s\n" "$DESC" "$ERRS"
        fi
        ;;

    esac
done < "$CONFIG"

echo ""
echo "============================================"

if [ "$TOTAL" -eq 0 ]; then
    echo "No valid checks found in vpl_checks.conf."
    echo "Grade :=>> 0"
    exit 0
fi

PCT=$(( SCORE * 100 / TOTAL ))
echo "Score : $SCORE / $TOTAL  ($PCT%)"
echo ""
echo "Grade :=>> $PCT"
