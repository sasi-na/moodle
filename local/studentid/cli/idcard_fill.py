#!/usr/bin/env python3
"""
Fill JAIN Student ID card PDF template with student data and photo.

Usage: python3 idcard_fill.py <data_json_file> <output_pdf_file>

Input JSON schema:
{
  "student": {
    "fullname": "...", "program": "...", "batch": "...", "usn": "...",
    "dob": "...", "blood_group": "...", "contact": "...",
    "city": "...", "state": "...", "country": "..."
  },
  "photo_path": "/absolute/path/to/photo.jpg"  // optional
}
"""
import sys
import json
import os
import fitz  # PyMuPDF


# Navy blue matching the template (#192352)
NAVY = (25 / 255, 35 / 255, 82 / 255)
FONT  = "hebo"   # Helvetica-Bold — closest built-in to Gilroy-Bold
FSIZE = 5.0      # matches label font size in template


def _fit_rect(img_w, img_h, box):
    """Return a Rect that places the image centred inside box, preserving aspect ratio."""
    bw, bh = box.width, box.height
    scale = min(bw / img_w, bh / img_h)
    w, h = img_w * scale, img_h * scale
    x0 = box.x0 + (bw - w) / 2
    y0 = box.y0 + (bh - h) / 2
    return fitz.Rect(x0, y0, x0 + w, y0 + h)


def _fit_text(page, text, max_width, fontname, fontsize):
    """Reduce fontsize until text fits within max_width."""
    while fontsize > 3.5:
        if fitz.get_text_length(text, fontname=fontname, fontsize=fontsize) <= max_width:
            break
        fontsize -= 0.3
    return fontsize


def fill_front(page, data, photo_bytes):
    # ── Photo ────────────────────────────────────────────────────────────────
    # White photo box: Rect(41.24, 47.18, 129.06, 137.12) — add 3pt inset to
    # stay inside rounded corners
    photo_box = fitz.Rect(44, 50, 126, 134)
    if photo_bytes:
        try:
            # Determine image dimensions for aspect-ratio fit
            tmp = fitz.open(stream=photo_bytes, filetype="image")
            r = tmp[0].rect
            tmp.close()
            fit = _fit_rect(r.width, r.height, photo_box)
            page.insert_image(fit, stream=photo_bytes, keep_proportion=True)
        except Exception:
            page.insert_image(photo_box, stream=photo_bytes)

    # Horizontal watermark — always drawn regardless of photo presence.
    # It runs straight across the photo's vertical centre so it starts in the
    # yellow card margin on the left, crosses the white photo box, and ends in
    # the yellow margin on the right.  At y≈92 the yellow margins are x≈2–41
    # (left) and x≈130–169 (right); the text is sized to land ~15 pt inside each
    # margin.  Navy at low opacity stays legible on both the yellow and white
    # areas (white-on-yellow would be invisible).
    cx = (photo_box.x0 + photo_box.x1) / 2  # ≈85
    cy = (photo_box.y0 + photo_box.y1) / 2  # ≈92
    wm_text  = "J A I N   O N L I N E"
    wm_font  = fitz.Font("hebo")
    target_w = 138.0  # span ≈ x16 → x154: both ends well inside the yellow margins
    unit_w   = fitz.get_text_length(wm_text, fontname="hebo", fontsize=1)
    wm_size  = target_w / unit_w if unit_w else 12
    start_x  = cx - target_w / 2
    start_y  = cy + wm_size * 0.35   # baseline offset so glyphs centre on cy
    tw = fitz.TextWriter(page.rect, opacity=0.20, color=NAVY)
    tw.append(fitz.Point(start_x, start_y), wm_text, font=wm_font, fontsize=wm_size)
    tw.write_text(page, overlay=True)

    # ── Info panel ────────────────────────────────────────────────────────────
    # White panel: x≈16.5..153.5, y=141.8..203.6. The labels (Program/Mode/
    # Batch/University Seat Number) and their colons sit ~10 pt further left than
    # the older template, so the value column starts at x≈60.
    panel_w = 153.50 - 16.50   # 137 pts
    panel_cx = 16.50 + panel_w / 2  # ≈85.0

    # Student name — centred at top of panel (y≈154)
    fullname = data.get('fullname', '').strip()
    if fullname:
        fs = _fit_text(page, fullname, panel_w - 6, FONT, 6.5)
        tw = fitz.get_text_length(fullname, fontname=FONT, fontsize=fs)
        page.insert_text((panel_cx - tw / 2, 154), fullname,
                         fontname=FONT, fontsize=fs, color=NAVY)

    # Value x starts just after the colon at x≈57
    vx = 60

    program = data.get('program', '').strip()
    if program:
        page.insert_text((vx, 165), program, fontname=FONT, fontsize=FSIZE, color=NAVY)

    batch = data.get('batch', '').strip()
    if batch:
        page.insert_text((vx, 181), batch, fontname=FONT, fontsize=FSIZE, color=NAVY)

    usn = data.get('usn', '').strip()
    if usn:
        # "University Seat Number" label spans two lines; the colon sits on the
        # second line, so the value baseline aligns there (≈196).
        page.insert_text((vx, 196), usn, fontname=FONT, fontsize=FSIZE, color=NAVY)


def fill_back(page, data):
    # Values start just after the colon column (colon at x=99-100.3)
    vx = 103

    rows = [
        ('dob',         52),
        ('blood_group', 65),
        ('contact',     78),
        ('city',        91),
        ('state',       104),
        ('country',     117),
    ]
    for key, y in rows:
        val = data.get(key, '').strip()
        if val:
            page.insert_text((vx, y), val, fontname=FONT, fontsize=FSIZE, color=NAVY)


def fill_idcard(template_path, data, photo_bytes, output_path):
    doc = fitz.open(template_path)
    fill_front(doc[0], data, photo_bytes)
    fill_back(doc[1], data)
    doc.save(output_path, deflate=True)
    doc.close()


if __name__ == '__main__':
    if len(sys.argv) != 3:
        print(f"Usage: {sys.argv[0]} <data_json_file> <output_pdf_file>", file=sys.stderr)
        sys.exit(1)

    data_file   = sys.argv[1]
    output_file = sys.argv[2]

    with open(data_file, 'r', encoding='utf-8') as fh:
        payload = json.load(fh)

    student    = payload.get('student', {})
    photo_path = payload.get('photo_path', '')

    photo_bytes = None
    if photo_path and os.path.isfile(photo_path):
        with open(photo_path, 'rb') as fh:
            photo_bytes = fh.read()

    template = os.path.join(
        os.path.dirname(os.path.abspath(__file__)),
        '..', 'pix', 'idcard_template.pdf'
    )
    fill_idcard(os.path.normpath(template), student, photo_bytes, output_file)
