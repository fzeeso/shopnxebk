from __future__ import annotations

from datetime import date
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.platypus import (
    Flowable,
    HRFlowable,
    KeepTogether,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / "output" / "pdf" / "category-collection-product-database-schema.pdf"

PAGE_SIZE = landscape(A4)
PAGE_WIDTH, PAGE_HEIGHT = PAGE_SIZE
MARGIN_X = 16 * mm
MARGIN_TOP = 18 * mm
MARGIN_BOTTOM = 16 * mm
CONTENT_WIDTH = PAGE_WIDTH - (2 * MARGIN_X)

NAVY = colors.HexColor("#102A43")
BLUE = colors.HexColor("#2F6BFF")
TEAL = colors.HexColor("#159A80")
ORANGE = colors.HexColor("#E67E22")
INK = colors.HexColor("#243B53")
MUTED = colors.HexColor("#627D98")
LINE = colors.HexColor("#D9E2EC")
PALE_BLUE = colors.HexColor("#EAF1FF")
PALE_TEAL = colors.HexColor("#E7F7F3")
PALE_ORANGE = colors.HexColor("#FFF3E8")
PALE_GRAY = colors.HexColor("#F5F7FA")
WHITE = colors.white


def register_fonts() -> tuple[str, str, str]:
    windows_fonts = Path("C:/Windows/Fonts")
    candidates = [
        ("SchemaSans", windows_fonts / "arial.ttf", windows_fonts / "arialbd.ttf"),
        ("SchemaSans", windows_fonts / "calibri.ttf", windows_fonts / "calibrib.ttf"),
    ]
    for family, regular_path, bold_path in candidates:
        if regular_path.exists() and bold_path.exists():
            pdfmetrics.registerFont(TTFont(family, str(regular_path)))
            pdfmetrics.registerFont(TTFont(f"{family}-Bold", str(bold_path)))
            return family, f"{family}-Bold", "Courier"
    return "Helvetica", "Helvetica-Bold", "Courier"


FONT, FONT_BOLD, FONT_MONO = register_fonts()

styles = getSampleStyleSheet()
styles.add(
    ParagraphStyle(
        name="SchemaTitle",
        parent=styles["Title"],
        fontName=FONT_BOLD,
        fontSize=28,
        leading=34,
        textColor=NAVY,
        alignment=TA_LEFT,
        spaceAfter=10,
    )
)
styles.add(
    ParagraphStyle(
        name="SchemaSubtitle",
        parent=styles["Normal"],
        fontName=FONT,
        fontSize=12,
        leading=18,
        textColor=MUTED,
        spaceAfter=14,
    )
)
styles.add(
    ParagraphStyle(
        name="SchemaH1",
        parent=styles["Heading1"],
        fontName=FONT_BOLD,
        fontSize=18,
        leading=22,
        textColor=NAVY,
        spaceBefore=5,
        spaceAfter=8,
        keepWithNext=True,
    )
)
styles.add(
    ParagraphStyle(
        name="SchemaH2",
        parent=styles["Heading2"],
        fontName=FONT_BOLD,
        fontSize=12,
        leading=15,
        textColor=BLUE,
        spaceBefore=5,
        spaceAfter=5,
        keepWithNext=True,
    )
)
styles.add(
    ParagraphStyle(
        name="SchemaBody",
        parent=styles["BodyText"],
        fontName=FONT,
        fontSize=8.7,
        leading=12.2,
        textColor=INK,
        spaceAfter=5,
    )
)
styles.add(
    ParagraphStyle(
        name="SchemaSmall",
        parent=styles["BodyText"],
        fontName=FONT,
        fontSize=7.2,
        leading=9.5,
        textColor=MUTED,
        spaceAfter=3,
    )
)
styles.add(
    ParagraphStyle(
        name="TableHeader",
        parent=styles["Normal"],
        fontName=FONT_BOLD,
        fontSize=7.5,
        leading=9,
        textColor=WHITE,
    )
)
styles.add(
    ParagraphStyle(
        name="TableCell",
        parent=styles["Normal"],
        fontName=FONT,
        fontSize=7.1,
        leading=9.1,
        textColor=INK,
    )
)
styles.add(
    ParagraphStyle(
        name="TableCode",
        parent=styles["Normal"],
        fontName=FONT_MONO,
        fontSize=6.8,
        leading=8.8,
        textColor=NAVY,
    )
)
styles.add(
    ParagraphStyle(
        name="NoteTitle",
        parent=styles["Normal"],
        fontName=FONT_BOLD,
        fontSize=7.4,
        leading=8.8,
        textColor=BLUE,
    )
)
styles.add(
    ParagraphStyle(
        name="NoteBody",
        parent=styles["Normal"],
        fontName=FONT,
        fontSize=7.1,
        leading=8.8,
        textColor=INK,
    )
)
styles.add(
    ParagraphStyle(
        name="RelationLine",
        parent=styles["Normal"],
        fontName=FONT,
        fontSize=6.9,
        leading=8.2,
        textColor=INK,
    )
)
styles.add(
    ParagraphStyle(
        name="TableIntro",
        parent=styles["SchemaBody"],
        keepWithNext=True,
    )
)
styles.add(
    ParagraphStyle(
        name="CoverMetric",
        parent=styles["Normal"],
        fontName=FONT_BOLD,
        fontSize=19,
        leading=22,
        alignment=TA_CENTER,
        textColor=NAVY,
    )
)
styles.add(
    ParagraphStyle(
        name="CoverMetricLabel",
        parent=styles["Normal"],
        fontName=FONT,
        fontSize=8,
        leading=10,
        alignment=TA_CENTER,
        textColor=MUTED,
    )
)


def para(text: str, style: str = "SchemaBody") -> Paragraph:
    return Paragraph(text, styles[style])


def code(text: str) -> Paragraph:
    return Paragraph(text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;"), styles["TableCode"])


def heading(text: str, level: int = 1) -> Paragraph:
    return para(text, "SchemaH1" if level == 1 else "SchemaH2")


def section_rule() -> HRFlowable:
    return HRFlowable(width="100%", thickness=0.7, color=LINE, spaceBefore=2, spaceAfter=7)


def structure_table(rows: list[tuple[str, str, str]], widths: tuple[float, float, float] | None = None) -> Table:
    if widths is None:
        widths = (0.25, 0.27, 0.48)
    data = [
        [para("Column", "TableHeader"), para("PostgreSQL type", "TableHeader"), para("Null, default, key or meaning", "TableHeader")]
    ]
    for column, data_type, details in rows:
        data.append([code(column), para(data_type, "TableCell"), para(details, "TableCell")])
    table = Table(
        data,
        colWidths=[CONTENT_WIDTH * widths[0], CONTENT_WIDTH * widths[1], CONTENT_WIDTH * widths[2]],
        repeatRows=1,
        hAlign="LEFT",
    )
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), NAVY),
                ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 2.5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 2.5),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PALE_GRAY]),
                ("LINEBELOW", (0, 1), (-1, -1), 0.35, LINE),
            ]
        )
    )
    return table


def summary_table(headers: list[str], rows: list[list[str]], widths: list[float]) -> Table:
    data = [[para(h, "TableHeader") for h in headers]]
    for row in rows:
        data.append([para(cell, "TableCell") for cell in row])
    table = Table(data, colWidths=[CONTENT_WIDTH * width for width in widths], repeatRows=1, hAlign="LEFT")
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), NAVY),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 2.5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 2.5),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PALE_GRAY]),
                ("LINEBELOW", (0, 1), (-1, -1), 0.35, LINE),
            ]
        )
    )
    return table


def note_box(title: str, text: str, background=PALE_BLUE, accent=BLUE) -> Table:
    rich_text = f'<font name="{FONT_BOLD}">{title}:</font> {text}'
    data = [[para(rich_text, "NoteBody")]]
    table = Table(data, colWidths=[CONTENT_WIDTH], hAlign="LEFT")
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), background),
                ("BOX", (0, 0), (-1, -1), 0.8, accent),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 9),
                ("RIGHTPADDING", (0, 0), (-1, -1), 9),
                ("TOPPADDING", (0, 0), (-1, -1), 3),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
            ]
        )
    )
    return table


class RelationshipMap(Flowable):
    def __init__(self, width: float, height: float = 250):
        super().__init__()
        self.width = width
        self.height = height

    def wrap(self, avail_width, avail_height):
        return min(self.width, avail_width), self.height

    def draw_box(self, canvas, x, y, w, h, label, fill, stroke=NAVY, size=8):
        canvas.setFillColor(fill)
        canvas.setStrokeColor(stroke)
        canvas.setLineWidth(0.7)
        canvas.roundRect(x, y, w, h, 5, fill=1, stroke=1)
        canvas.setFillColor(NAVY)
        canvas.setFont(FONT_BOLD, size)
        canvas.drawCentredString(x + (w / 2), y + (h / 2) - (size * 0.32), label)

    def line(self, canvas, x1, y1, x2, y2, color=MUTED):
        canvas.setStrokeColor(color)
        canvas.setLineWidth(0.9)
        canvas.line(x1, y1, x2, y2)

    def draw(self):
        c = self.canv
        w = self.width
        store = (w / 2 - 55, 214, 110, 27)
        category = (45, 160, 125, 28)
        collection = (w / 2 - 62, 160, 125, 28)
        product = (w - 170, 160, 125, 28)
        brand = (w - 170, 214, 125, 27)

        for x, y, bw, bh in [category, collection, product]:
            self.line(c, store[0] + store[2] / 2, store[1], x + bw / 2, y + bh)
        self.line(c, brand[0] + brand[2] / 2, brand[1], product[0] + product[2] / 2, product[1] + product[3])

        lower = [
            (18, 100, 135, 25, "category_translations", PALE_BLUE),
            (166, 100, 135, 25, "product_categories", PALE_ORANGE),
            (w / 2 - 67, 100, 135, 25, "collection_translations", PALE_TEAL),
            (w / 2 - 67, 65, 135, 25, "collection_rules / AI jobs", PALE_TEAL),
            (w - 301, 100, 135, 25, "product_collections", PALE_ORANGE),
            (w - 153, 100, 135, 25, "product children", PALE_BLUE),
            (w - 153, 65, 135, 25, "product_tags", PALE_BLUE),
        ]

        self.line(c, category[0] + category[2] / 2, category[1], 18 + 67.5, 125)
        self.line(c, category[0] + category[2] / 2, category[1], 166 + 67.5, 125)
        self.line(c, product[0] + product[2] / 2, product[1], 166 + 67.5, 125)
        self.line(c, collection[0] + collection[2] / 2, collection[1], w / 2, 125)
        self.line(c, collection[0] + collection[2] / 2, collection[1], w / 2, 90)
        self.line(c, collection[0] + collection[2] / 2, collection[1], w - 301 + 67.5, 125)
        self.line(c, product[0] + product[2] / 2, product[1], w - 301 + 67.5, 125)
        self.line(c, product[0] + product[2] / 2, product[1], w - 153 + 67.5, 125)
        self.line(c, product[0] + product[2] / 2, product[1], w - 153 + 67.5, 90)

        self.draw_box(c, *store, "stores", PALE_BLUE)
        self.draw_box(c, *brand, "brands (optional)", PALE_ORANGE)
        self.draw_box(c, *category, "categories", PALE_BLUE, BLUE, 9)
        self.draw_box(c, *collection, "collections", PALE_TEAL, TEAL, 9)
        self.draw_box(c, *product, "products", PALE_ORANGE, ORANGE, 9)
        for box in lower:
            self.draw_box(c, *box)

        c.setFillColor(MUTED)
        c.setFont(FONT, 7)
        c.drawString(18, 27, "Every relation repeats store_id; composite foreign keys enforce Store isolation.")
        c.drawRightString(w - 18, 27, "Solid paths indicate database-enforced foreign keys.")


class ProductMap(Flowable):
    def __init__(self, width: float, height: float = 275):
        super().__init__()
        self.width = width
        self.height = height

    def wrap(self, avail_width, avail_height):
        return min(self.width, avail_width), self.height

    def draw_box(self, c, x, y, w, h, label, fill=PALE_BLUE, stroke=BLUE, size=7.4):
        c.setFillColor(fill)
        c.setStrokeColor(stroke)
        c.setLineWidth(0.65)
        c.roundRect(x, y, w, h, 4, fill=1, stroke=1)
        c.setFillColor(NAVY)
        c.setFont(FONT_BOLD, size)
        c.drawCentredString(x + w / 2, y + h / 2 - size * 0.32, label)

    def line(self, c, x1, y1, x2, y2):
        c.setStrokeColor(LINE)
        c.setLineWidth(1.1)
        c.line(x1, y1, x2, y2)

    def draw(self):
        c = self.canv
        w = self.width
        root = (w / 2 - 58, 238, 116, 27)
        groups = [
            (16, 182, 130, 25, "localization / taxonomy", PALE_BLUE, BLUE),
            (158, 182, 130, 25, "options / values", PALE_TEAL, TEAL),
            (300, 182, 130, 25, "variants", PALE_ORANGE, ORANGE),
            (442, 182, 130, 25, "media / fulfillment", PALE_BLUE, BLUE),
            (584, 182, 130, 25, "custom fields", PALE_TEAL, TEAL),
        ]
        children = [
            (16, 137, 130, 22, "translations / pivots", PALE_GRAY, BLUE),
            (158, 137, 130, 22, "option translations", PALE_GRAY, TEAL),
            (158, 102, 130, 22, "option values + i18n", PALE_GRAY, TEAL),
            (300, 137, 130, 22, "variant translations", PALE_GRAY, ORANGE),
            (300, 102, 130, 22, "variant_option_values", PALE_GRAY, ORANGE),
            (442, 137, 130, 22, "images + translations", PALE_GRAY, BLUE),
            (442, 102, 130, 22, "digital assets + i18n", PALE_GRAY, BLUE),
            (442, 67, 130, 22, "license keys", PALE_GRAY, BLUE),
            (584, 137, 130, 22, "typed scalar values", PALE_GRAY, TEAL),
            (584, 102, 130, 22, "text translations", PALE_GRAY, TEAL),
            (584, 67, 130, 22, "multi-select options", PALE_GRAY, TEAL),
        ]
        for gx, gy, gw, gh, _, _, _ in groups:
            self.line(c, root[0] + root[2] / 2, root[1], gx + gw / 2, gy + gh)
        group_centers = {round(g[0]): (g[0] + g[2] / 2, g[1]) for g in groups}
        for x, y, bw, bh, _, _, _ in children:
            parent_x = max(key for key in group_centers if key <= round(x))
            px, py = group_centers[parent_x]
            self.line(c, px, py, x + bw / 2, y + bh)

        self.draw_box(c, *root, "products", PALE_ORANGE, ORANGE, 9)
        for box in groups:
            self.draw_box(c, *box)
        for box in children:
            self.draw_box(c, *box)

        c.setFillColor(MUTED)
        c.setFont(FONT, 7)
        c.drawString(16, 26, "Direct Product ownership cascades on delete; nullable Variant links detach where noted.")


def page_header_footer(canvas, doc):
    canvas.saveState()
    if doc.page > 1:
        canvas.setStrokeColor(LINE)
        canvas.setLineWidth(0.5)
        canvas.line(MARGIN_X, PAGE_HEIGHT - 11 * mm, PAGE_WIDTH - MARGIN_X, PAGE_HEIGHT - 11 * mm)
        canvas.setFont(FONT_BOLD, 7.5)
        canvas.setFillColor(NAVY)
        canvas.drawString(MARGIN_X, PAGE_HEIGHT - 8.5 * mm, "SHOPNXE CATALOG DATABASE SCHEMA")
        canvas.setFont(FONT, 7.2)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(PAGE_WIDTH - MARGIN_X, PAGE_HEIGHT - 8.5 * mm, "Live PostgreSQL structure")
    canvas.setStrokeColor(LINE)
    canvas.line(MARGIN_X, 10 * mm, PAGE_WIDTH - MARGIN_X, 10 * mm)
    canvas.setFont(FONT, 7)
    canvas.setFillColor(MUTED)
    canvas.drawString(MARGIN_X, 6.5 * mm, "Category, Collection and Product relations")
    canvas.drawRightString(PAGE_WIDTH - MARGIN_X, 6.5 * mm, f"Page {doc.page}")
    canvas.restoreState()


def add_schema_table(story, title, intro, rows, relationship_text):
    story.append(heading(title, 2))
    if intro:
        story.append(para(intro, "TableIntro"))
    story.append(structure_table(rows))
    if relationship_text:
        story.append(Spacer(1, 2))
        story.append(
            para(
                f'<font name="{FONT_BOLD}" color="#2F6BFF">Keys and relations:</font> {relationship_text}',
                "RelationLine",
            )
        )
    story.append(Spacer(1, 3))


def build_story() -> list:
    story: list = []

    story.append(Spacer(1, 12 * mm))
    story.append(para("DATABASE REFERENCE", "SchemaSmall"))
    story.append(para("Category, Collection and Product Schema", "SchemaTitle"))
    story.append(
        para(
            "Complete relation map and table structure generated from the existing ShopNxe PostgreSQL database. "
            "Snapshot: 2026-08-20.",
            "SchemaSubtitle",
        )
    )
    story.append(Spacer(1, 3 * mm))
    metrics = Table(
        [
            [para("26", "CoverMetric"), para("5", "CoverMetric"), para("4", "CoverMetric"), para("PostgreSQL", "CoverMetric")],
            [
                para("Catalog tables", "CoverMetricLabel"),
                para("External anchor tables", "CoverMetricLabel"),
                para("Core schema migrations", "CoverMetricLabel"),
                para("Verified database", "CoverMetricLabel"),
            ],
        ],
        colWidths=[CONTENT_WIDTH / 4] * 4,
    )
    metrics.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), PALE_BLUE),
                ("BOX", (0, 0), (-1, -1), 0.8, BLUE),
                ("INNERGRID", (0, 0), (-1, -1), 0.4, LINE),
                ("TOPPADDING", (0, 0), (-1, 0), 12),
                ("BOTTOMPADDING", (0, 1), (-1, 1), 10),
            ]
        )
    )
    story.append(metrics)
    story.append(Spacer(1, 9 * mm))
    story.append(RelationshipMap(CONTENT_WIDTH, 250))
    story.append(PageBreak())

    story.append(heading("1. Conventions and tenant boundary"))
    story.append(section_rule())
    story.append(
        note_box(
            "Store isolation",
            "Every Catalog relationship carries store_id. Composite foreign keys such as "
            "(product_id, store_id) referencing products(id, store_id) prevent cross-Store assignments at the database level.",
            PALE_TEAL,
            TEAL,
        )
    )
    story.append(Spacer(1, 6))
    story.append(
        summary_table(
            ["Notation", "Meaning", "Database behavior"],
            [
                ["PK", "Primary key", "Uniquely identifies a row."],
                ["UQ", "Unique constraint or index", "Prevents duplicate business identities."],
                ["FK", "Foreign key", "Enforces a valid parent row and defined delete behavior."],
                ["Nullable", "Column may be NULL", "Used for optional Brand, parent, Variant, image and lifecycle data."],
                ["lock_it", "Translation overwrite lock", "false by default; true preserves merchant-authored localized content."],
                ["Minor units", "Integer money representation", "Avoids floating-point price storage."],
            ],
            [0.14, 0.33, 0.53],
        )
    )
    story.append(Spacer(1, 8))
    story.append(heading("High-level Catalog relationships", 2))
    story.append(RelationshipMap(CONTENT_WIDTH, 250))
    story.append(PageBreak())

    story.append(heading("2. Category aggregate"))
    story.append(section_rule())
    add_schema_table(
        story,
        "categories",
        "Store-owned navigation taxonomy with an optional parent Category.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required FK to stores.id; Store deletion cascades."),
            ("parent_id", "bigint", "Nullable self-reference inside the same Store."),
            ("image_url", "varchar(500)", "Nullable shared Category image."),
            ("is_active", "boolean", "Required, default true."),
            ("sort_order", "integer", "Required, default 0."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, store_id). Self FK (parent_id, store_id) references categories(id, store_id). "
        "Deleting a parent sets only parent_id to NULL. Indexes: (store_id, is_active) and "
        "(store_id, parent_id, sort_order).",
    )
    add_schema_table(
        story,
        "category_translations",
        "Localized navigation, SEO, template and media presentation for a Category.",
        [
            ("store_id", "bigint", "Required FK to stores.id."),
            ("category_id", "bigint", "Required composite FK to categories."),
            ("locale", "varchar(35)", "Required locale key."),
            ("title", "varchar(255)", "Required localized title."),
            ("slug", "varchar(255)", "Required localized URL segment."),
            ("description", "text", "Nullable localized description."),
            ("seo_title", "varchar(255)", "Nullable SEO title."),
            ("seo_description", "text", "Nullable SEO description."),
            ("page_title", "varchar(255)", "Nullable storefront page title."),
            ("search_keywords", "text", "Nullable search keywords."),
            ("category_template", "varchar(120)", "Nullable storefront template identifier."),
            ("banner_url", "varchar(500)", "Nullable locale-specific banner."),
            ("image_url", "varchar(500)", "Nullable locale-specific image."),
            ("lock_it", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (category_id, locale). Unique (store_id, locale, slug). Composite Category FK cascades on delete.",
    )
    add_schema_table(
        story,
        "product_categories",
        "Many-to-many assignment between Products and Categories.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("category_id", "bigint", "Required composite FK to categories."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("sort_order", "integer", "Required, default 0."),
            ("is_primary", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (category_id, product_id). Partial unique index (store_id, product_id) WHERE is_primary "
        "allows only one primary Category per Product. Parent deletion removes only the assignment.",
    )
    story.append(PageBreak())

    story.append(heading("3. Collection aggregate"))
    story.append(section_rule())
    add_schema_table(
        story,
        "collections",
        "Store-owned merchandising groups populated manually, by rules or from AI-generated rules.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required FK to stores.id."),
            ("parent_id", "bigint", "Nullable same-Store parent Collection."),
            ("image_url", "varchar(500)", "Nullable Collection image."),
            ("is_active", "boolean", "Required, default true."),
            ("sort_order", "integer", "Required, default 0."),
            ("collection_type", "varchar(20)", "Default manual; manual, rule_based or ai_generated."),
            ("rules_match_type", "varchar(10)", "Default all; all or any."),
            ("ai_prompt", "text", "Nullable latest AI instruction."),
            ("ai_model", "varchar(100)", "Nullable latest model identifier."),
            ("ai_status", "varchar(20)", "Nullable; pending, processing, completed or failed."),
            ("ai_last_run_at", "timestamptz", "Nullable latest run time."),
            ("ai_error_message", "text", "Nullable latest operator-facing error."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, store_id). Self FK sets parent_id to NULL when a parent is deleted. Indexes: "
        "(store_id, collection_type) and (store_id, parent_id, sort_order).",
    )
    add_schema_table(
        story,
        "collection_translations",
        "Localized Collection content and SEO metadata.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("collection_id", "bigint", "Required composite FK to collections."),
            ("locale", "varchar(35)", "Required locale key."),
            ("title", "varchar(255)", "Required localized title."),
            ("slug", "varchar(255)", "Required localized URL segment."),
            ("description", "text", "Nullable localized description."),
            ("seo_title", "varchar(255)", "Nullable SEO title."),
            ("seo_description", "text", "Nullable SEO description."),
            ("lock_it", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (collection_id, locale). Unique (store_id, locale, slug). Collection deletion cascades.",
    )
    add_schema_table(
        story,
        "collection_rules",
        "Ordered structured conditions that define automatic membership.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("collection_id", "bigint", "Required composite FK to collections."),
            ("field", "varchar(50)", "Required Product field identifier."),
            ("operator", "varchar(20)", "Required comparison operator."),
            ("value", "varchar(255)", "Required serialized operand."),
            ("position", "integer", "Required, default 0."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, store_id). Index (store_id, collection_id, position). Collection deletion cascades.",
    )
    story.append(PageBreak())

    story.append(heading("3. Collection aggregate - automation and membership"))
    story.append(section_rule())
    add_schema_table(
        story,
        "collection_ai_jobs",
        "Append-style audit history for AI Collection generation.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("collection_id", "bigint", "Required composite FK to collections."),
            ("prompt", "text", "Required prompt used for the run."),
            ("model", "varchar(100)", "Required model identifier."),
            ("status", "varchar(20)", "Default pending; pending, processing, completed or failed."),
            ("result_rules", "jsonb", "Nullable raw structured output."),
            ("matched_count", "integer", "Nullable matched Product count."),
            ("error_message", "text", "Nullable failure detail."),
            ("tokens_used", "integer", "Nullable usage accounting."),
            ("completed_at", "timestamptz", "Nullable terminal time."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, store_id). Index (store_id, collection_id, created_at). The row records a run; "
        "application code must normalize rules and Product assignments.",
    )
    add_schema_table(
        story,
        "product_collections",
        "Many-to-many Product membership with automation metadata. PK (collection_id, product_id); indexes "
        "support Product lookup and Collection sorting. Parent deletion removes only the assignment. "
        "Regeneration should replace unpinned automated rows while preserving pinned merchant decisions.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("collection_id", "bigint", "Required composite FK to collections."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("sort_order", "integer", "Required, default 0."),
            ("added_by", "varchar(10)", "Default manual; manual, rule or ai."),
            ("is_pinned", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "",
    )
    story.append(PageBreak())

    story.append(heading("4. Product aggregate"))
    story.append(section_rule())
    story.append(ProductMap(CONTENT_WIDTH, 275))
    story.append(Spacer(1, 6))
    add_schema_table(
        story,
        "products",
        "The Product root contains Store-wide, non-localized behavior and publication state.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required FK to stores.id."),
            ("brand_id", "bigint", "Nullable same-Store Brand reference."),
            ("vendor", "varchar(255)", "Nullable supplier text."),
            ("product_type", "varchar(255)", "Nullable merchant Product type."),
            ("fulfillment_type", "varchar(20)", "Default physical; physical, digital, software or service."),
            ("track_inventory", "boolean", "Required, default true."),
            ("status", "varchar(20)", "Default draft; draft, active or archived."),
            ("has_variants", "boolean", "Required, default false."),
            ("published_at", "timestamptz", "Nullable publication time."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, store_id). Brand deletion sets brand_id to NULL. Indexes: (store_id, status), "
        "(store_id, fulfillment_type), and (store_id, brand_id).",
    )
    story.append(PageBreak())

    story.append(heading("4. Product localization and taxonomy"))
    story.append(section_rule())
    add_schema_table(
        story,
        "product_translations",
        "Localized storefront content and SEO metadata.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("locale", "varchar(35)", "Required locale key."),
            ("title", "varchar(255)", "Required localized title."),
            ("slug", "varchar(255)", "Required localized URL segment."),
            ("description", "text", "Nullable localized description."),
            ("seo_title", "varchar(255)", "Nullable SEO title."),
            ("seo_description", "text", "Nullable SEO description."),
            ("lock_it", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (product_id, locale). Unique (store_id, locale, slug). Product deletion cascades.",
    )
    add_schema_table(
        story,
        "product_tags",
        "Many-to-many Product and Tag assignment.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("tag_id", "bigint", "Required composite FK to tags."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (product_id, tag_id). Index (store_id, tag_id). Deleting either parent removes the assignment.",
    )
    story.append(
        summary_table(
            ["Pivot", "Primary key", "Additional state", "Delete behavior"],
            [
                ["product_categories", "(category_id, product_id)", "sort_order, is_primary", "Cascade assignment"],
                ["product_collections", "(collection_id, product_id)", "sort_order, added_by, is_pinned", "Cascade assignment"],
                ["product_tags", "(product_id, tag_id)", "Timestamps", "Cascade assignment"],
            ],
            [0.22, 0.25, 0.31, 0.22],
        )
    )
    story.append(PageBreak())

    story.append(heading("5. Product options and option values"))
    story.append(section_rule())
    option_summary = [
        ["product_options", "id PK; Product FK", "position=0", "Defines Color, Size or another dimension."],
        ["product_option_translations", "PK (option_id, locale)", "name, lock_it=false", "Localized option name."],
        ["product_option_values", "id PK; composite Option FK", "product_id, option_id, position=0", "Defines Red, Blue, Small or similar choices."],
        ["product_option_value_translations", "PK (option_value_id, locale)", "value, lock_it=false", "Localized choice label."],
    ]
    story.append(summary_table(["Table", "Key", "Important columns", "Purpose"], option_summary, [0.25, 0.23, 0.26, 0.26]))
    story.append(Spacer(1, 8))
    add_schema_table(
        story,
        "product_options",
        "Product-scoped option definitions.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("position", "integer", "Required, default 0."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, store_id) and (id, product_id, store_id). Index (store_id, product_id, position).",
    )
    add_schema_table(
        story,
        "product_option_values",
        "Ordered values that belong to a Product option.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required Product scope."),
            ("option_id", "bigint", "Required composite FK to product_options."),
            ("position", "integer", "Required, default 0."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Composite FK (option_id, product_id, store_id) ensures the value belongs to the same Product. "
        "Unique (id, product_id, store_id).",
    )
    story.append(PageBreak())

    story.append(heading("6. Product variants and selections"))
    story.append(section_rule())
    add_schema_table(
        story,
        "product_variants",
        "Purchasable Product combinations with price, inventory and fulfillment dimensions.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("sku", "varchar(100)", "Nullable; unique per Store when present."),
            ("barcode", "varchar(100)", "Nullable."),
            ("price_amount_minor", "bigint", "Required, non-negative."),
            ("compare_at_price_amount_minor", "bigint", "Nullable, non-negative."),
            ("msrp_amount_minor", "bigint", "Nullable, non-negative."),
            ("cost_per_item_amount_minor", "bigint", "Nullable, non-negative."),
            ("currency_code", "character(3)", "Required, three uppercase letters."),
            ("inventory_qty", "integer", "Required, default 0."),
            ("inventory_policy", "varchar(20)", "Default deny; deny or continue."),
            ("weight_grams", "integer", "Nullable."),
            ("height", "numeric(12,4)", "Nullable."),
            ("width", "numeric(12,4)", "Nullable."),
            ("depth", "numeric(12,4)", "Nullable."),
            ("dimension_unit", "varchar(10)", "Required, default cm."),
            ("requires_shipping", "boolean", "Required, default true."),
            ("taxable", "boolean", "Required, default true."),
            ("call_for_price", "boolean", "Required, default false."),
            ("image_id", "bigint", "Nullable representative Product image."),
            ("position", "integer", "Required, default 0."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, product_id, store_id). Index (store_id, product_id, position). Deleting the Product cascades; "
        "deleting a representative image sets image_id to NULL.",
    )
    story.append(PageBreak())

    story.append(heading("6. Variant translations and selected values"))
    story.append(section_rule())
    add_schema_table(
        story,
        "product_variant_translations",
        "Localized optional Variant titles.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("variant_id", "bigint", "Required composite FK to product_variants."),
            ("locale", "varchar(35)", "Required locale key."),
            ("title", "varchar(255)", "Nullable localized title."),
            ("lock_it", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (variant_id, locale). Variant deletion cascades.",
    )
    add_schema_table(
        story,
        "variant_option_values",
        "Join table that records each option value selected by a Variant.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required Product scope."),
            ("variant_id", "bigint", "Required composite FK to product_variants."),
            ("option_value_id", "bigint", "Required composite FK to product_option_values."),
            ("created_at", "timestamptz", "Required, default CURRENT_TIMESTAMP."),
        ],
        "PK (variant_id, option_value_id). Both composite FKs include product_id and store_id, preventing "
        "a Variant from selecting a value from another Product or Store.",
    )
    story.append(
        note_box(
            "Money storage",
            "Variant prices use integer minor units. For a two-decimal currency, 250000 represents 2,500.00. "
            "This avoids floating-point rounding in the database.",
            PALE_ORANGE,
            ORANGE,
        )
    )
    story.append(PageBreak())

    story.append(heading("7. Product images"))
    story.append(section_rule())
    add_schema_table(
        story,
        "product_images",
        "Ordered Product images with optional Variant specialization.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("variant_id", "bigint", "Nullable same-Product Variant reference."),
            ("url", "varchar(500)", "Required image locator."),
            ("width", "integer", "Nullable pixel width."),
            ("height", "integer", "Nullable pixel height."),
            ("position", "integer", "Required, default 0."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (id, product_id, store_id). Indexes by Product position and Variant. Deleting the Product cascades; "
        "deleting the Variant sets variant_id to NULL.",
    )
    add_schema_table(
        story,
        "product_image_translations",
        "Localized alternative text for an image.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("image_id", "bigint", "Required composite FK to product_images."),
            ("locale", "varchar(35)", "Required locale key."),
            ("alt_text", "varchar(255)", "Nullable accessibility text."),
            ("lock_it", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (image_id, locale). Image deletion cascades.",
    )
    story.append(PageBreak())

    story.append(heading("8. Digital assets and licenses"))
    story.append(section_rule())
    add_schema_table(
        story,
        "product_digital_assets",
        "Downloadable files attached to a Product or optional Variant.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("variant_id", "bigint", "Nullable same-Product Variant reference."),
            ("file_url", "varchar(500)", "Required file locator."),
            ("file_name", "varchar(255)", "Required file name."),
            ("file_size_bytes", "bigint", "Nullable file size."),
            ("file_type", "varchar(50)", "Nullable file type."),
            ("download_limit", "integer", "Nullable allowed download count."),
            ("link_expires_after_days", "integer", "Nullable link lifetime."),
            ("position", "integer", "Required, default 0."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Indexes by Product position and Variant. Product deletion cascades; Variant deletion sets variant_id to NULL.",
    )
    add_schema_table(
        story,
        "product_digital_asset_translations",
        "Localized display name and description for downloadable files.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("digital_asset_id", "bigint", "Required composite FK to product_digital_assets."),
            ("locale", "varchar(35)", "Required locale key."),
            ("display_name", "varchar(255)", "Nullable localized display name."),
            ("description", "text", "Nullable localized description."),
            ("lock_it", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (digital_asset_id, locale). Digital asset deletion cascades.",
    )
    story.append(PageBreak())

    story.append(heading("8. Product license keys"))
    story.append(section_rule())
    add_schema_table(
        story,
        "product_license_keys",
        "Store-scoped license inventory for software and license-based Products.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("variant_id", "bigint", "Nullable same-Product Variant reference."),
            ("key_code", "varchar(255)", "Required; unique per Store."),
            ("status", "varchar(20)", "Default available; available, assigned, revoked or expired."),
            ("max_activations", "integer", "Required, default 1; must be greater than zero."),
            ("assigned_to_order_id", "bigint", "Nullable and indexed; no Order FK currently exists."),
            ("assigned_at", "timestamptz", "Nullable assignment time."),
            ("expires_at", "timestamptz", "Nullable expiry time."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique (store_id, key_code). Indexes by Product/status and Variant/status. Product deletion cascades; "
        "Variant deletion sets variant_id to NULL.",
    )
    story.append(
        note_box(
            "Order integration gap",
            "assigned_to_order_id is an indexed bigint but is not protected by a database foreign key. "
            "Order existence and Store consistency must currently be enforced by application code.",
            PALE_ORANGE,
            ORANGE,
        )
    )
    story.append(PageBreak())

    story.append(heading("9. Product custom-field values"))
    story.append(section_rule())
    add_schema_table(
        story,
        "product_custom_field_values",
        "Typed values attached at Product level or optionally overridden for a Variant.",
        [
            ("id", "bigint", "Auto-increment PK."),
            ("public_id", "character(26)", "Required, unique public ULID."),
            ("store_id", "bigint", "Required Store boundary."),
            ("product_id", "bigint", "Required composite FK to products."),
            ("variant_id", "bigint", "Nullable same-Product Variant override."),
            ("definition_id", "bigint", "Required composite FK to custom_field_definitions."),
            ("value_number", "numeric(18,4)", "Nullable numeric scalar."),
            ("value_boolean", "boolean", "Nullable boolean scalar."),
            ("value_date", "date", "Nullable date scalar."),
            ("value_option_id", "bigint", "Nullable single-select option; deletion restricted while used."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "Unique expression scope (store_id, definition_id, product_id, COALESCE(variant_id, 0)). "
        "A CHECK permits at most one scalar column. Product, Variant or Definition deletion cascades as applicable.",
    )
    add_schema_table(
        story,
        "product_custom_field_value_translations",
        "Localized text value for translatable custom fields.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("value_id", "bigint", "Required composite FK to product_custom_field_values."),
            ("locale", "varchar(35)", "Required locale key."),
            ("value_text", "text", "Required localized text."),
            ("lock_it", "boolean", "Required, default false."),
            ("created_at", "timestamptz", "Nullable audit timestamp."),
            ("updated_at", "timestamptz", "Nullable audit timestamp."),
        ],
        "PK (value_id, locale). Parent custom-field value deletion cascades.",
    )
    add_schema_table(
        story,
        "product_custom_field_value_options",
        "Multi-select join between a Product custom-field value and allowed options.",
        [
            ("store_id", "bigint", "Required Store boundary."),
            ("definition_id", "bigint", "Required definition scope."),
            ("value_id", "bigint", "Required composite FK to product_custom_field_values."),
            ("option_id", "bigint", "Required composite FK to custom_field_options."),
            ("created_at", "timestamptz", "Required, default CURRENT_TIMESTAMP."),
        ],
        "PK (value_id, option_id). Composite definition and Store keys prevent selecting an option from another field or Store.",
    )
    story.append(PageBreak())

    story.append(heading("10. Foreign-key delete behavior"))
    story.append(section_rule())
    story.append(
        summary_table(
            ["Deleted parent", "Affected relation", "Database result"],
            [
                ["Store", "All Category, Collection and Product tables", "Cascade delete."],
                ["Parent Category", "categories.parent_id", "Set parent_id to NULL; keep child."],
                ["Parent Collection", "collections.parent_id", "Set parent_id to NULL; keep child."],
                ["Brand", "products.brand_id", "Set brand_id to NULL; keep Product."],
                ["Category", "category translations and product_categories", "Cascade children and assignments."],
                ["Collection", "translations, rules, AI jobs and product_collections", "Cascade children and assignments."],
                ["Product", "All Product-owned rows", "Cascade through translations, pivots, options, variants, media, licenses and custom values."],
                ["Variant", "Variant translations and selections", "Cascade delete."],
                ["Variant", "image, digital asset and license optional links", "Set variant_id to NULL."],
                ["Variant", "Variant-specific custom-field values", "Cascade delete."],
                ["Product image", "product_variants.image_id", "Set image_id to NULL."],
                ["Custom-field definition", "Product custom-field values", "Cascade delete."],
                ["Single-select option in use", "value_option_id", "Restrict deletion."],
                ["Multi-select option", "product_custom_field_value_options", "Cascade pivot row."],
            ],
            [0.25, 0.35, 0.40],
        )
    )
    story.append(Spacer(1, 8))
    story.append(
        note_box(
            "Deletion principle",
            "Core entities delete their owned dependent rows. Shared entities such as Brands, Categories, Collections and Tags "
            "either detach or remove only pivots so unrelated Catalog records survive.",
            PALE_TEAL,
            TEAL,
        )
    )
    story.append(PageBreak())

    story.append(heading("11. Constraints and index summary"))
    story.append(section_rule())
    story.append(
        summary_table(
            ["Area", "Constraint or index", "Purpose"],
            [
                ["Category", "(store_id, locale, slug) unique", "Localized Category URLs are unique per Store."],
                ["Category", "One primary Category partial index", "Only one product_categories row can be primary per Product."],
                ["Collection", "Type and status CHECK constraints", "Restricts Collection and AI lifecycle values."],
                ["Collection", "Store/type and Store/parent/sort indexes", "Supports listing and hierarchy ordering."],
                ["Product", "Status, fulfillment and Brand indexes", "Supports Store-scoped management filters."],
                ["Product", "(store_id, locale, slug) unique", "Localized Product URLs are unique per Store."],
                ["Variant", "Partial unique (store_id, sku)", "A non-null SKU cannot repeat inside a Store."],
                ["Variant", "Currency and non-negative price CHECKs", "Protects monetary integrity."],
                ["Variant", "Product/position index", "Supports ordered Product Variant retrieval."],
                ["Media", "Product/position and Variant indexes", "Supports ordered galleries and Variant lookups."],
                ["License", "Store/key unique and status indexes", "Protects key uniqueness and assignment queries."],
                ["Custom fields", "Expression unique Product/Variant scope", "One field value per Product or Variant scope."],
                ["Custom fields", "Scalar num_nonnulls CHECK", "Prevents multiple scalar representations in one row."],
            ],
            [0.19, 0.34, 0.47],
        )
    )
    story.append(Spacer(1, 8))
    story.append(heading("External anchor tables", 2))
    story.append(
        summary_table(
            ["Anchor", "Referenced by", "Role"],
            [
                ["stores", "Every Catalog table", "Tenant ownership and cascade root."],
                ["brands", "products.brand_id", "Optional Product Brand."],
                ["tags", "product_tags.tag_id", "Shared Product classification."],
                ["custom_field_definitions", "product_custom_field_values.definition_id", "Defines field key and type."],
                ["custom_field_options", "Single-select and multi-select values", "Defines allowed choices."],
            ],
            [0.23, 0.38, 0.39],
        )
    )
    story.append(PageBreak())

    story.append(heading("12. Source and implementation notes"))
    story.append(section_rule())
    story.append(
        para(
            "The structure in this document was checked against the live PostgreSQL database with Laravel's db:table "
            "inspection command. The following applied migrations define the schema:",
        )
    )
    sources = [
        "Modules/Catalog/database/migrations/2026_08_05_000100_create_catalog_taxonomy_tables.php",
        "Modules/Catalog/database/migrations/2026_08_05_000200_create_catalog_product_tables.php",
        "Modules/Catalog/database/migrations/2026_08_05_000300_create_catalog_variant_fulfillment_tables.php",
        "Modules/Catalog/database/migrations/2026_08_05_000400_create_catalog_custom_field_value_tables.php",
        "Modules/Catalog/database/migrations/2026_08_09_000100_add_lock_it_to_catalog_translation_tables.php",
        "Modules/Catalog/database/migrations/2026_08_08_000100_add_page_title_and_search_keywords_to_category_translations_table.php",
        "Modules/Catalog/database/migrations/2026_08_08_000200_add_category_template_to_category_translations_table.php",
        "Modules/Catalog/database/migrations/2026_08_08_000300_add_banner_url_to_category_translations_table.php",
        "Modules/Catalog/database/migrations/2026_08_17_000100_add_image_url_to_category_translations_table.php",
    ]
    source_rows = [[str(index + 1), source] for index, source in enumerate(sources)]
    story.append(summary_table(["#", "Repository source"], source_rows, [0.07, 0.93]))
    story.append(Spacer(1, 8))
    story.append(
        note_box(
            "Application coverage",
            "The database schema is broader than current ORM and API coverage. Product currently exposes Store, Brand, "
            "translations and Categories in its model; Collection and several Product child aggregates still require "
            "application-layer models, services and endpoints before they can be fully managed.",
            PALE_ORANGE,
            ORANGE,
        )
    )
    story.append(Spacer(1, 8))
    story.append(
        para(
            "Generated as a technical database reference. This document contains schema metadata only and does not include credentials or row data.",
            "SchemaSmall",
        )
    )
    return story


def build_pdf() -> None:
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc = SimpleDocTemplate(
        str(OUTPUT),
        pagesize=PAGE_SIZE,
        rightMargin=MARGIN_X,
        leftMargin=MARGIN_X,
        topMargin=MARGIN_TOP,
        bottomMargin=MARGIN_BOTTOM,
        title="Category, Collection and Product Database Schema",
        author="ShopNxe",
        subject="Live PostgreSQL Catalog schema reference",
        creator="ReportLab",
    )
    doc.build(build_story(), onFirstPage=page_header_footer, onLaterPages=page_header_footer)


if __name__ == "__main__":
    build_pdf()
    print(OUTPUT)
