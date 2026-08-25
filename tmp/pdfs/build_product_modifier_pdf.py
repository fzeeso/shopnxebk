from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.platypus import (
    BaseDocTemplate,
    Flowable,
    Frame,
    KeepTogether,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(r"C:\xampp\htdocs\shopnxebk")
OUTPUT = ROOT / "output" / "pdf" / "product-modifier-tables-and-flow.pdf"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)

INK = colors.HexColor("#172033")
MUTED = colors.HexColor("#5E6B7F")
NAVY = colors.HexColor("#153B66")
BLUE = colors.HexColor("#2468A2")
TEAL = colors.HexColor("#178582")
PALE_BLUE = colors.HexColor("#EAF3FA")
PALE_TEAL = colors.HexColor("#E8F6F4")
PALE_GRAY = colors.HexColor("#F4F6F8")
RULE = colors.HexColor("#CCD5DF")
WHITE = colors.white


def register_fonts():
    font_root = Path(r"C:\Windows\Fonts")
    regular = font_root / "segoeui.ttf"
    semibold = font_root / "seguisb.ttf"
    mono = font_root / "consola.ttf"
    if regular.exists():
        pdfmetrics.registerFont(TTFont("DocSans", str(regular)))
        pdfmetrics.registerFont(TTFont("DocSansBold", str(semibold if semibold.exists() else regular)))
    if mono.exists():
        pdfmetrics.registerFont(TTFont("DocMono", str(mono)))


register_fonts()
BODY_FONT = "DocSans" if "DocSans" in pdfmetrics.getRegisteredFontNames() else "Helvetica"
BOLD_FONT = "DocSansBold" if "DocSansBold" in pdfmetrics.getRegisteredFontNames() else "Helvetica-Bold"
MONO_FONT = "DocMono" if "DocMono" in pdfmetrics.getRegisteredFontNames() else "Courier"


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="DocTitle",
    fontName=BOLD_FONT,
    fontSize=25,
    leading=30,
    textColor=NAVY,
    alignment=TA_LEFT,
    spaceAfter=8,
))
styles.add(ParagraphStyle(
    name="DocSubtitle",
    fontName=BODY_FONT,
    fontSize=11.5,
    leading=17,
    textColor=MUTED,
    spaceAfter=18,
))
styles.add(ParagraphStyle(
    name="Section",
    fontName=BOLD_FONT,
    fontSize=16,
    leading=20,
    textColor=NAVY,
    spaceBefore=2,
    spaceAfter=9,
))
styles.add(ParagraphStyle(
    name="Subsection",
    fontName=BOLD_FONT,
    fontSize=11.5,
    leading=15,
    textColor=BLUE,
    spaceBefore=8,
    spaceAfter=5,
))
styles.add(ParagraphStyle(
    name="BodyDoc",
    fontName=BODY_FONT,
    fontSize=9.3,
    leading=13.4,
    textColor=INK,
    spaceAfter=6,
))
styles.add(ParagraphStyle(
    name="Small",
    fontName=BODY_FONT,
    fontSize=8.1,
    leading=11,
    textColor=MUTED,
))
styles.add(ParagraphStyle(
    name="TableHeader",
    fontName=BOLD_FONT,
    fontSize=8.3,
    leading=10.5,
    textColor=WHITE,
))
styles.add(ParagraphStyle(
    name="TableCell",
    fontName=BODY_FONT,
    fontSize=7.8,
    leading=10.3,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="TableCode",
    fontName=MONO_FONT,
    fontSize=7.15,
    leading=9.4,
    textColor=NAVY,
))
styles.add(ParagraphStyle(
    name="Callout",
    fontName=BODY_FONT,
    fontSize=9,
    leading=13,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="StepNumber",
    fontName=BOLD_FONT,
    fontSize=9,
    leading=11,
    textColor=WHITE,
    alignment=TA_CENTER,
))
styles.add(ParagraphStyle(
    name="StepTitle",
    fontName=BOLD_FONT,
    fontSize=8.2,
    leading=10.3,
    textColor=NAVY,
    alignment=TA_CENTER,
))
styles.add(ParagraphStyle(
    name="StepBody",
    fontName=BODY_FONT,
    fontSize=6.8,
    leading=8.4,
    textColor=MUTED,
    alignment=TA_CENTER,
))


def p(text, style="BodyDoc"):
    return Paragraph(text, styles[style])


class StepFlow(Flowable):
    def __init__(self, steps, width=170 * mm, height=42 * mm):
        super().__init__()
        self.steps = steps
        self.width = width
        self.height = height

    def wrap(self, avail_width, avail_height):
        self.width = min(self.width, avail_width)
        return self.width, self.height

    def draw(self):
        count = len(self.steps)
        gap = 5.2 * mm
        box_w = (self.width - gap * (count - 1)) / count
        box_h = 31 * mm
        y = 5 * mm

        for index, (title, body) in enumerate(self.steps):
            x = index * (box_w + gap)
            self.canv.setFillColor(PALE_BLUE if index < 3 else PALE_TEAL)
            self.canv.roundRect(x, y, box_w, box_h, 3 * mm, fill=1, stroke=0)

            badge = 7 * mm
            self.canv.setFillColor(BLUE if index < 3 else TEAL)
            self.canv.circle(x + box_w / 2, y + box_h - 6 * mm, badge / 2, fill=1, stroke=0)
            number = Paragraph(str(index + 1), styles["StepNumber"])
            nw, nh = number.wrap(badge, badge)
            number.drawOn(self.canv, x + (box_w - nw) / 2, y + box_h - 8.1 * mm)

            heading = Paragraph(title, styles["StepTitle"])
            hw, hh = heading.wrap(box_w - 4 * mm, 12 * mm)
            heading.drawOn(self.canv, x + 2 * mm, y + box_h - 15 * mm - hh / 2)

            detail = Paragraph(body, styles["StepBody"])
            dw, dh = detail.wrap(box_w - 4 * mm, 12 * mm)
            detail.drawOn(self.canv, x + 2 * mm, y + 2.6 * mm)

            if index < count - 1:
                x1 = x + box_w + 1 * mm
                x2 = x + box_w + gap - 1 * mm
                mid_y = y + box_h / 2
                self.canv.setStrokeColor(RULE)
                self.canv.setLineWidth(1.2)
                self.canv.line(x1, mid_y, x2, mid_y)
                self.canv.setFillColor(RULE)
                self.canv.line(x2, mid_y, x2 - 2.2 * mm, mid_y + 1.5 * mm)
                self.canv.line(x2, mid_y, x2 - 2.2 * mm, mid_y - 1.5 * mm)


class ModifierDocTemplate(BaseDocTemplate):
    def __init__(self, filename):
        super().__init__(
            filename,
            pagesize=A4,
            leftMargin=18 * mm,
            rightMargin=18 * mm,
            topMargin=21 * mm,
            bottomMargin=17 * mm,
            title="Product Modifier Library - Tables and Flow",
            author="Codex",
            subject="Reusable multi-language product modifier architecture",
        )
        frame = Frame(
            self.leftMargin,
            self.bottomMargin,
            self.width,
            self.height,
            id="main",
            leftPadding=0,
            rightPadding=0,
            topPadding=0,
            bottomPadding=0,
        )
        self.addPageTemplates(PageTemplate(id="content", frames=[frame], onPage=self._page_chrome))

    def _page_chrome(self, canvas, doc):
        canvas.saveState()
        page_width, page_height = A4
        canvas.setStrokeColor(RULE)
        canvas.setLineWidth(0.6)
        canvas.line(18 * mm, page_height - 14 * mm, page_width - 18 * mm, page_height - 14 * mm)
        canvas.setFont(BODY_FONT, 7.5)
        canvas.setFillColor(MUTED)
        canvas.drawString(18 * mm, page_height - 10.5 * mm, "SHOPNXEBK - PRODUCT MODIFIER LIBRARY")
        canvas.drawRightString(page_width - 18 * mm, 9.5 * mm, f"Page {doc.page}")
        canvas.drawString(18 * mm, 9.5 * mm, "Tables and flow reference")
        canvas.restoreState()


def callout(title, body, color=PALE_BLUE):
    data = [[p(title, "Subsection"), p(body, "Callout")]]
    table = Table(data, colWidths=[37 * mm, 133 * mm])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), color),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]))
    return table


def data_table(rows, widths):
    cooked = [[p("Table", "TableHeader"), p("Purpose", "TableHeader"), p("Key behavior", "TableHeader")]]
    for table_name, purpose, behavior in rows:
        cooked.append([p(table_name, "TableCode"), p(purpose, "TableCell"), p(behavior, "TableCell")])
    table = Table(cooked, colWidths=widths, repeatRows=1, hAlign="LEFT")
    style = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LINEBELOW", (0, 1), (-1, -1), 0.35, RULE),
    ]
    for row_index in range(1, len(cooked)):
        if row_index % 2 == 0:
            style.append(("BACKGROUND", (0, row_index), (-1, row_index), PALE_GRAY))
    table.setStyle(TableStyle(style))
    return table


library_rows = [
    ("modifier_library_categories", "Optional categories for organizing reusable modifiers.", "One category can contain many definitions; public ULID."),
    ("modifier_library_category_translations", "Localized category names and descriptions.", "Unique per Store, category, and locale."),
    ("modifier_definitions", "Reusable master definition with code, input type, configuration, and status.", "Defined once and assigned to many products; public ULID."),
    ("modifier_translations", "Localized modifier name, description, and help text.", "Library translation fallback source."),
    ("modifier_values", "Selectable values such as sizes, colors, or add-ons.", "Belongs to one definition; public ULID."),
    ("modifier_value_translations", "Localized value labels and descriptions.", "Falls back to the Store default locale, then value code."),
    ("modifier_validation_rules", "Reusable input constraints: length, pattern, numeric range, or file restrictions.", "Evaluated when cart selections are submitted."),
    ("modifier_validation_rule_translations", "Localized validation messages.", "Keeps storefront errors translatable."),
    ("modifier_price_adjustments", "Library-level pricing for selecting a modifier.", "Filtered by currency, audience, activity dates, and status."),
    ("modifier_value_price_adjustments", "Library-level pricing for an individual value.", "Combined with the effective modifier-level component."),
]

product_rows = [
    ("product_modifier_groups", "Groups modifiers on a product, such as Extras or Choose a size.", "Supports ordering and public ULID."),
    ("product_modifier_group_translations", "Localized product group names.", "Unique per Store, group, and locale."),
    ("product_modifier_assignments", "Connects a product to a reusable modifier.", "Stores group, order, required state, min/max, settings, and public ULID."),
    ("product_modifier_assignment_translations", "Product-specific localized label and description overrides.", "Takes precedence over library text in the requested locale."),
    ("product_modifier_value_assignments", "Enables, disables, or reorders values for a product.", "Restricts the reusable value set without duplicating it."),
    ("product_modifier_price_overrides", "Product-specific modifier-level price replacement.", "Replaces the matching library component; it does not add a second component."),
    ("product_modifier_value_price_overrides", "Product-specific value-level price replacement.", "Replaces the matching library value component."),
]

transaction_rows = [
    ("cart_item_modifier_selections", "Validated selections attached to a cart item.", "Multi-select stores one row per selected value; free form uses JSON input."),
    ("order_item_modifier_snapshots", "Immutable checkout copy of localized names, codes, ULIDs, input, and pricing.", "Source foreign keys are nullable and use SET NULL; order history never cascades away."),
]


story = []
story.append(Spacer(1, 8 * mm))
story.append(p("Reusable Multi-Language<br/>Product Modifier Library", "DocTitle"))
story.append(p("Database tables, relationships, resolution rules, cart validation, and checkout snapshot flow", "DocSubtitle"))
story.append(callout(
    "Architecture summary",
    "The schema contains <b>19 Store-scoped tables</b> split into three layers: 10 reusable library tables, 7 product configuration tables, and 2 transactional tables. A modifier is defined once and reused across products; product assignments store only restrictions and overrides.",
))
story.append(Spacer(1, 8 * mm))
story.append(p("Lifecycle at a glance", "Section"))
story.append(StepFlow([
    ("Build library", "Definitions, translations, values, rules, and default prices"),
    ("Assign product", "Grouping, order, required state, and allowed values"),
    ("Resolve storefront", "Locale fallback and effective server-side pricing"),
    ("Validate cart", "Rules, ownership, counts, values, input, and price"),
    ("Snapshot order", "Immutable customer-visible names and pricing"),
]))
story.append(Spacer(1, 6 * mm))
story.append(p("Identity and tenancy", "Section"))
story.append(data_table([
    ("store_id", "Present throughout the schema for tenant isolation.", "Translations and junction tables retain Store-aware composite relationships."),
    ("Internal IDs", "Used for relational joins and database consistency.", "Two product/value junctions also retain internal modifier_id for composite integrity."),
    ("Public ULIDs", "Exposed only for externally addressable entities.", "Category, modifier definition, modifier value, product group, and product assignment."),
    ("locale", "Uses the existing 35-character locale convention.", "Translation records support lock_it behavior consistent with the project."),
], [37 * mm, 64 * mm, 69 * mm]))
story.append(PageBreak())

story.append(p("1. Reusable modifier library", "Section"))
story.append(p("This layer owns canonical modifier content. Product records reference it instead of copying names, values, validation rules, or default prices."))
story.append(data_table(library_rows, [50 * mm, 61 * mm, 59 * mm]))
story.append(Spacer(1, 6 * mm))
story.append(callout(
    "Reuse principle",
    "A definition such as <b>Engraving</b>, <b>Size</b>, or <b>Gift wrap</b> is created once. The same definition and values can then be assigned to any number of products.",
    PALE_TEAL,
))
story.append(PageBreak())

story.append(p("2. Product configuration", "Section"))
story.append(p("The product layer expresses placement and differences from the library. It can reorder values, restrict availability, override localized labels, and replace default prices."))
story.append(data_table(product_rows, [52 * mm, 58 * mm, 60 * mm]))
story.append(Spacer(1, 7 * mm))
story.append(p("3. Cart and order records", "Section"))
story.append(data_table(transaction_rows, [52 * mm, 58 * mm, 60 * mm]))
story.append(Spacer(1, 7 * mm))
story.append(callout(
    "Historical safety",
    "Order snapshots remain readable when products, assignments, modifier definitions, or values are renamed or removed. Source references may become NULL, but captured codes, localized labels, input, prices, currency, and locale remain unchanged.",
))
story.append(PageBreak())

story.append(p("4. Relationship map", "Section"))
story.append(p("The relationships are intentionally layered: canonical library data flows into product assignments; resolved assignments create cart selections; checkout converts those selections into immutable order snapshots."))

relationship_rows = [
    ("Category -> modifier definition", "One optional category organizes many reusable modifier definitions."),
    ("Modifier definition -> values", "One definition exposes zero or more selectable reusable values."),
    ("Modifier definition -> validation rules", "Rules describe how free-form or media input must be validated."),
    ("Modifier/value -> library prices", "Library price components provide the default pricing behavior."),
    ("Product + modifier -> assignment", "The assignment is the reusable link and carries product-specific behavior."),
    ("Product -> group -> assignment", "Groups are optional and control storefront organization and order."),
    ("Assignment -> value assignments", "The product can enable, disable, and reorder the modifier's values."),
    ("Assignment -> product price overrides", "Product pricing replaces the corresponding library component."),
    ("Assignment -> cart selections", "Only server-validated effective selections are stored."),
    ("Cart selection -> order snapshot", "Checkout copies display and pricing data exactly once."),
]
relationship_table = [[p("Relationship", "TableHeader"), p("Meaning", "TableHeader")]]
for left, right in relationship_rows:
    relationship_table.append([p(left, "TableCode"), p(right, "TableCell")])
relationship_table = Table(relationship_table, colWidths=[70 * mm, 100 * mm], repeatRows=1)
relationship_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 7),
    ("RIGHTPADDING", (0, 0), (-1, -1), 7),
    ("TOPPADDING", (0, 0), (-1, -1), 6),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ("LINEBELOW", (0, 1), (-1, -1), 0.35, RULE),
    ("BACKGROUND", (0, 2), (-1, 2), PALE_GRAY),
    ("BACKGROUND", (0, 4), (-1, 4), PALE_GRAY),
    ("BACKGROUND", (0, 6), (-1, 6), PALE_GRAY),
    ("BACKGROUND", (0, 8), (-1, 8), PALE_GRAY),
    ("BACKGROUND", (0, 10), (-1, 10), PALE_GRAY),
]))
story.append(relationship_table)
story.append(Spacer(1, 8 * mm))
story.append(callout(
    "Deletion behavior",
    "Transactional history is protected from source data changes. Order snapshot source foreign keys are nullable and use SET NULL rather than cascading deletion.",
    PALE_TEAL,
))
story.append(PageBreak())

story.append(p("5. Runtime resolution and validation", "Section"))
story.append(p("Storefront resolution", "Subsection"))
story.append(p("The resolved endpoint loads active product assignments, definitions, allowed values, requested-locale content, Store-default fallbacks, and matching price rows. It returns a storefront-ready DTO rather than exposing raw internal rows."))

resolution_rows = [
    ("Modifier label", "Requested-locale assignment override -> requested-locale library translation -> Store-default library translation -> modifier code."),
    ("Value label", "Requested-locale library translation -> Store-default library translation -> value code."),
    ("Modifier price", "Matching product modifier override replaces the matching library modifier component."),
    ("Value price", "Matching product value override replaces the matching library value component."),
    ("Final adjustment", "Effective modifier component + effective selected-value component. Percentage pricing uses product base price."),
    ("Audience match", "Exact sales channel/customer group match wins over broader active pricing for the same currency and date."),
]
resolution_table = [[p("Resolution", "TableHeader"), p("Precedence or calculation", "TableHeader")]]
for name, rule in resolution_rows:
    resolution_table.append([p(name, "TableCode"), p(rule, "TableCell")])
resolution_table = Table(resolution_table, colWidths=[45 * mm, 125 * mm], repeatRows=1)
resolution_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 7),
    ("RIGHTPADDING", (0, 0), (-1, -1), 7),
    ("TOPPADDING", (0, 0), (-1, -1), 6),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ("LINEBELOW", (0, 1), (-1, -1), 0.35, RULE),
    ("BACKGROUND", (0, 2), (-1, 2), PALE_GRAY),
    ("BACKGROUND", (0, 4), (-1, 4), PALE_GRAY),
    ("BACKGROUND", (0, 6), (-1, 6), PALE_GRAY),
]))
story.append(resolution_table)
story.append(Spacer(1, 7 * mm))
story.append(p("Cart validation", "Subsection"))
validation_items = [
    "1. Re-resolve the active product assignment; never trust stale client configuration.",
    "2. Enforce required state and minimum/maximum selection counts.",
    "3. Confirm every value belongs to the modifier and is enabled for the product.",
    "4. Validate free-form input and same-Store ready media for file/image modifiers.",
    "5. Ignore client-submitted prices and recalculate the effective adjustment server-side.",
    "6. Store one cart row per selected value, or JSON input for free-form selections.",
]
validation_data = [[p(item, "Callout")] for item in validation_items]
validation_table = Table(validation_data, colWidths=[170 * mm])
validation_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), PALE_BLUE),
    ("LEFTPADDING", (0, 0), (-1, -1), 9),
    ("RIGHTPADDING", (0, 0), (-1, -1), 9),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ("LINEBELOW", (0, 0), (-1, -2), 0.3, WHITE),
]))
story.append(validation_table)
story.append(PageBreak())

story.append(p("6. API and implementation reference", "Section"))
story.append(p("Primary resolved endpoint", "Subsection"))
story.append(callout(
    "Storefront read",
    "<font name='DocMono'>GET /api/v1/store/products/{product}/modifiers/resolved?locale=en&amp;currency=GBP</font><br/><br/>Returns assignment and modifier ULIDs, code, type, localized name, required/min/max settings, allowed values, and effective price adjustments.",
))
story.append(Spacer(1, 7 * mm))
story.append(p("Service sequence", "Subsection"))
story.append(StepFlow([
    ("Resolver", "Load active assignments, definitions, groups, and values"),
    ("Translations", "Apply assignment, requested locale, default locale, and code fallback"),
    ("Pricing", "Choose audience-specific active library rows and product replacements"),
    ("Validator", "Validate submitted values, input, media, and counts"),
    ("Snapshot", "Copy final display and pricing fields into order history"),
]))
story.append(Spacer(1, 6 * mm))
story.append(p("Source files", "Subsection"))
source_rows = [
    ("Library migration", r"Modules/Catalog/database/migrations/2026_08_25_001000_create_modifier_library_tables.php"),
    ("Product integration migration", r"Modules/Catalog/database/migrations/2026_08_25_001100_create_product_modifier_assignment_tables.php"),
    ("Cart/order migration", r"Modules/Catalog/database/migrations/2026_08_25_001200_create_cart_and_order_modifier_tables.php"),
    ("Catalog reference", r"docs/catalog.md"),
    ("API manual", r"docs/api-manual.md"),
]
source_table = [[p("Reference", "TableHeader"), p("Repository path", "TableHeader")]]
for label, path in source_rows:
    source_table.append([p(label, "TableCell"), p(path, "TableCode")])
source_table = Table(source_table, colWidths=[47 * mm, 123 * mm])
source_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 7),
    ("RIGHTPADDING", (0, 0), (-1, -1), 7),
    ("TOPPADDING", (0, 0), (-1, -1), 6),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ("LINEBELOW", (0, 1), (-1, -1), 0.35, RULE),
    ("BACKGROUND", (0, 2), (-1, 2), PALE_GRAY),
    ("BACKGROUND", (0, 4), (-1, 4), PALE_GRAY),
]))
story.append(source_table)
story.append(Spacer(1, 8 * mm))
story.append(callout(
    "Current integration boundary",
    "Cart and order integration services and storage are prepared, but this repository does not currently contain Cart, Orders, Sales Channel, or Customer Group modules. Checkout HTTP routes therefore remain unwired, and audience IDs are not accepted through the public API until those modules expose ULIDs.",
    PALE_TEAL,
))
story.append(Spacer(1, 5 * mm))
story.append(p("Database safety note: migrations and database-backed tests were not executed while preparing this implementation reference.", "Small"))


doc = ModifierDocTemplate(str(OUTPUT))
doc.build(story)
print(OUTPUT)
