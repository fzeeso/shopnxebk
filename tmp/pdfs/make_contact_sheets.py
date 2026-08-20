from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


source = Path(__file__).resolve().parent / "rendered-delivery"
pages = sorted(source.glob("catalog-schema-*.png"))
font_path = Path("C:/Windows/Fonts/arialbd.ttf")
font = ImageFont.truetype(str(font_path), 18) if font_path.exists() else ImageFont.load_default()

thumb_width = 560
thumb_height = 396
label_height = 30
columns = 2
rows = 3
per_sheet = columns * rows

for sheet_index in range((len(pages) + per_sheet - 1) // per_sheet):
    subset = pages[sheet_index * per_sheet : (sheet_index + 1) * per_sheet]
    canvas = Image.new("RGB", (columns * thumb_width, rows * (thumb_height + label_height)), "white")
    draw = ImageDraw.Draw(canvas)
    for offset, page_path in enumerate(subset):
        row = offset // columns
        column = offset % columns
        image = Image.open(page_path).convert("RGB")
        image.thumbnail((thumb_width - 8, thumb_height - 8))
        x = column * thumb_width + (thumb_width - image.width) // 2
        y = row * (thumb_height + label_height) + label_height + (thumb_height - image.height) // 2
        canvas.paste(image, (x, y))
        page_number = int(page_path.stem.rsplit("-", 1)[1])
        draw.text((column * thumb_width + 10, row * (thumb_height + label_height) + 5), f"Page {page_number}", fill="black", font=font)
    output = source / f"contact-{sheet_index + 1}.png"
    canvas.save(output, optimize=True)
    print(output)
