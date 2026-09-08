"""Favicons transparentes (sin fondo blanco) y marca UI desde public/logo.png."""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from brand_icon_utils import compose_transparent_icon, load_brand_logo

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'

SIZES_ICO = (16, 32, 48)


def main() -> None:
    logo = load_brand_logo()

    ico_images = [compose_transparent_icon(logo, size) for size in SIZES_ICO]
    ico_images[0].save(
        PUBLIC / 'favicon.ico',
        format='ICO',
        sizes=[(size, size) for size in SIZES_ICO],
        append_images=ico_images[1:],
    )

    compose_transparent_icon(logo, 16).save(PUBLIC / 'favicon-16x16.png', 'PNG')
    compose_transparent_icon(logo, 32).save(PUBLIC / 'favicon-32x32.png', 'PNG')
    compose_transparent_icon(logo, 256).save(PUBLIC / 'logo-mark.png', 'PNG')

    print('Generados en public/:')
    print('  favicon.ico (transparente)')
    print('  favicon-16x16.png')
    print('  favicon-32x32.png')
    print('  logo-mark.png')


if __name__ == '__main__':
    main()
