"""
Makes the source pictures for the app icon and the splash screen from the MyKaunsel heart-and-handshake logo.

    python tool/generate_brand_assets.py        (needs Pillow: pip install pillow)

then, in mykaunsel-app/:

    dart run flutter_launcher_icons -f flutter_launcher_icons.yaml
    dart run flutter_launcher_icons -f flutter_launcher_icons-dev.yaml
    dart run flutter_native_splash:create

The logo file assets/images/mykaunsel-icon.png is only 275 x 240 pixels, so it is enlarged here (smoothly). That is fine for
a first version, but an icon is shown BIG on some phones: when a high-resolution or vector (SVG) version of the logo is
available, replace SOURCE and run the three commands again. Nothing else needs to change.

Output (not shipped inside the app, only used by the two generators):
    assets/icon/   icon_prod.png (square, opaque, for iOS and old Android)   icon_foreground.png (Android adaptive)
                   icon_monochrome.png (Android 13 themed icon)   icon_dev.png / icon_dev_foreground.png (with a red DEV tag)
    assets/splash/ splash.png (all phones)   splash_android12.png (Android 12+)
"""
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / 'assets' / 'images' / 'mykaunsel-icon.png'
CREAM = (250, 248, 245, 255)  # the app's background colour (#FAF8F5)
DEV_RED = (214, 40, 57, 255)  # brand red (#D62839)


def enlarged(width: int) -> Image.Image:
    """The logo made `width` pixels wide: smooth enlargement, then a light sharpen so the edges stay crisp."""
    logo = Image.open(SOURCE).convert('RGBA')
    height = round(logo.height * width / logo.width)
    # Resize premultiplied, so the transparent edge does not turn into a dark or white halo.
    big = logo.resize((width, height), Image.LANCZOS)

    return big.filter(ImageFilter.UnsharpMask(radius=2, percent=60, threshold=2))


def centred(canvas_size: int, logo_width: int, background=(0, 0, 0, 0), dy: int = 0) -> Image.Image:
    canvas = Image.new('RGBA', (canvas_size, canvas_size), background)
    logo = enlarged(logo_width)
    canvas.alpha_composite(logo, ((canvas_size - logo.width) // 2, (canvas_size - logo.height) // 2 + dy))

    return canvas


def silhouette(canvas_size: int, logo_width: int) -> Image.Image:
    """One-colour picture for Android 13 "themed icons": the coloured parts of the logo, with its white lines kept as gaps."""
    logo = enlarged(logo_width)
    pixels = logo.load()
    out = Image.new('RGBA', logo.size, (0, 0, 0, 0))
    target = out.load()

    for y in range(logo.height):
        for x in range(logo.width):
            r, g, b, a = pixels[x, y]
            brightness = (r + g + b) / 3
            # Coloured and solid: part of the shape. Whitish (the lines between the hands) or see-through: a gap.
            alpha = a if brightness < 200 else max(0, int(a * (230 - brightness) / 30)) if brightness < 230 else 0
            target[x, y] = (0, 0, 0, alpha)

    canvas = Image.new('RGBA', (canvas_size, canvas_size), (0, 0, 0, 0))
    canvas.alpha_composite(out, ((canvas_size - out.width) // 2, (canvas_size - out.height) // 2))

    return canvas


def dev_tag(image: Image.Image, y_fraction: float = 0.80) -> Image.Image:
    """A red "DEV" pill near the bottom, so the test app is never mistaken for the real one on the same phone."""
    size = image.width
    draw = ImageDraw.Draw(image)
    font = None
    for candidate in ['C:/Windows/Fonts/arialbd.ttf', '/System/Library/Fonts/Helvetica.ttc', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']:
        try:
            font = ImageFont.truetype(candidate, int(size * 0.12))
            break
        except OSError:
            continue
    font = font or ImageFont.load_default()

    box_w, box_h = int(size * 0.26), int(size * 0.15)
    x0 = (size - box_w) // 2
    y0 = int(size * y_fraction) - box_h // 2
    draw.rounded_rectangle((x0, y0, x0 + box_w, y0 + box_h), radius=box_h // 2, fill=DEV_RED)
    draw.text((size // 2, y0 + box_h // 2), 'DEV', font=font, fill=(255, 255, 255, 255), anchor='mm')

    return image


def save(image: Image.Image, relative: str, opaque: bool = False) -> None:
    path = ROOT / relative
    path.parent.mkdir(parents=True, exist_ok=True)
    (image.convert('RGB') if opaque else image).save(path)
    print('wrote', relative, image.size)


# ---- app icon ---------------------------------------------------------------------------
save(centred(1024, 620, CREAM), 'assets/icon/icon_prod.png', opaque=True)            # iOS needs a picture with NO see-through parts
# Android adaptive icons: the icon tool already shrinks these by 16% on every side (the "safe zone"), so the picture itself
# is drawn large here. 800 of 1024 pixels wide ends up well inside the 72-dp circle every phone shape keeps.
save(centred(1024, 800), 'assets/icon/icon_foreground.png')
save(silhouette(1024, 800), 'assets/icon/icon_monochrome.png')

dev = centred(1024, 560, CREAM, dy=-40)
save(dev_tag(dev, 0.84), 'assets/icon/icon_dev.png', opaque=True)
save(dev_tag(centred(1024, 720, dy=-80), 0.80), 'assets/icon/icon_dev_foreground.png')

# ---- splash screen ----------------------------------------------------------------------
# The phone treats this picture as 4x size: 340 pixels = 85 dp wide, the same size as the icon on the app's own first screen,
# so the logo does not jump in size when the app's screen takes over from the phone's.
save(centred(1152, 340), 'assets/splash/splash.png')
save(centred(1152, 560), 'assets/splash/splash_android12.png')                       # fits the 768-pixel circle Android 12 shows
