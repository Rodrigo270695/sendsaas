"""Utilidades para generar iconos desde public/logo.png en #AB3C3D."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
LOGO_SRC = ROOT / 'public' / 'logo.png'
BRAND_RGB = (171, 60, 61)  # #AB3C3D
SURFACE_LIGHT = (250, 250, 249, 255)


def extract_and_recolor(img: Image.Image) -> Image.Image:
    """Quita el fondo negro y pinta la marca en #AB3C3D conservando el alpha."""
    src = img.convert('RGBA')
    pixels = src.load()
    width, height = src.size
    out = Image.new('RGBA', (width, height), (0, 0, 0, 0))
    dest = out.load()
    brand_r, brand_g, brand_b = BRAND_RGB

    for y in range(height):
        for x in range(width):
            red, green, blue, alpha = pixels[x, y]
            if alpha < 8:
                continue
            intensity = max(red, green, blue)
            if intensity < 22:
                continue

            # Marca sólida #AB3C3D; solo el borde usa alpha para el antialias.
            painted_alpha = 255 if intensity >= 70 else min(255, int(intensity * 3.4))
            painted_alpha = min(painted_alpha, alpha)
            if painted_alpha < 8:
                continue

            dest[x, y] = (brand_r, brand_g, brand_b, painted_alpha)

    return out


def load_brand_logo() -> Image.Image:
    return extract_and_recolor(Image.open(LOGO_SRC))


def crop_to_content(img: Image.Image) -> Image.Image:
    bbox = img.getbbox()
    if bbox is None:
        return img
    return img.crop(bbox)


def resize_logo(content: Image.Image, box: int) -> Image.Image:
    width, height = content.size
    scale = min(box / width, box / height)
    new_w, new_h = max(1, int(width * scale)), max(1, int(height * scale))
    return content.resize((new_w, new_h), Image.Resampling.LANCZOS)


def fit_square(
    img: Image.Image,
    size: int,
    *,
    background: tuple[int, int, int, int] | None,
    logo_ratio: float,
) -> Image.Image:
    content = crop_to_content(img)
    inner = max(16, int(size * logo_ratio))
    resized = resize_logo(content, inner)

    canvas = Image.new('RGBA', (size, size), background or (0, 0, 0, 0))
    offset_x = (size - resized.width) // 2
    offset_y = (size - resized.height) // 2
    canvas.paste(resized, (offset_x, offset_y), resized)
    return canvas


def white_circle_canvas(size: int) -> Image.Image:
    canvas = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(canvas)
    radius = size * 0.42
    center = size / 2
    draw.ellipse(
        (center - radius, center - radius, center + radius, center + radius),
        fill=(255, 255, 255, 255),
    )
    return canvas


def compose_any_icon(logo: Image.Image, size: int) -> Image.Image:
    canvas = white_circle_canvas(size)
    content = crop_to_content(logo)
    inner = max(16, int(size * 0.58))
    resized = resize_logo(content, inner)
    offset_x = (size - resized.width) // 2
    offset_y = (size - resized.height) // 2
    canvas.paste(resized, (offset_x, offset_y), resized)
    return canvas


def compose_maskable_icon(logo: Image.Image, size: int) -> Image.Image:
    return fit_square(logo, size, background=SURFACE_LIGHT, logo_ratio=0.68)


def compose_transparent_icon(logo: Image.Image, size: int) -> Image.Image:
    return fit_square(logo, size, background=None, logo_ratio=0.88)
