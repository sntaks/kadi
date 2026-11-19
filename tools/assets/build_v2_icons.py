"""Rebuild public/v2 exports. Run with /usr/bin/python3 (Pillow, GI Rsvg, Cairo)."""
from pathlib import Path
import io
import math
import re
import cairo
import gi
from PIL import Image, ImageDraw, ImageFont

gi.require_version('Rsvg', '2.0')
from gi.repository import Rsvg

ROOT = Path(__file__).resolve().parents[2] / 'public/v2'
NIGHT = '#0e0d0b'


def render(source, size):
    surface = cairo.ImageSurface(cairo.FORMAT_ARGB32, size, size)
    context = cairo.Context(surface)
    handle = Rsvg.Handle.new_from_data(source.encode())
    viewport = Rsvg.Rectangle()
    viewport.x = viewport.y = 0
    viewport.width = viewport.height = size
    handle.render_document(context, viewport)
    buffer = io.BytesIO()
    surface.write_to_png(buffer)
    return Image.open(buffer).convert('RGBA')


def export(source, name, size, opaque=False):
    # Supersample small curves, then downsample with alpha intact.
    image = render(source, size * 4 if size <= 512 else size)
    image = image.resize((size, size), Image.Resampling.LANCZOS)
    if opaque:
        base = Image.new('RGBA', image.size, NIGHT)
        base.alpha_composite(image)
        image = base.convert('RGB')
    image.save(ROOT / name, optimize=True)
    return image


def build():
    master_path = ROOT / 'master-2048.svg'
    master = master_path.read_text()
    master = re.sub(r'<rect width="1024" height="1024" fill="#[0-9A-Fa-f]{6}"/>\s*', '', master)
    master_path.write_text(master)
    favicon_path = ROOT / 'favicon.svg'
    favicon = favicon_path.read_text()
    favicon = re.sub(r'<rect width="1024" height="1024" fill="#[0-9A-Fa-f]{6}"/>\s*', '', favicon)
    favicon_path.write_text(favicon)
    maskable = (ROOT / 'pwa-icons/icon-maskable.svg').read_text()
    badge = (ROOT / 'pwa-icons/badge.svg').read_text()
    export(master, 'master-2048.png', 2048)
    for size in (192, 512):
        export(master, f'pwa-icons/icon-{size}.png', size)
    export(maskable, 'pwa-icons/icon-maskable-512.png', 512, opaque=True)
    export(master, 'pwa-icons/apple-touch-icon.png', 180, opaque=True)
    export(badge, 'pwa-icons/badge-72.png', 72)
    favicons = []
    for size in (16, 32, 48):
        source = favicon
        if size == 16:
            source = source.replace('rotate(18)', 'rotate(12)').replace('scale(1.11)', 'scale(1.22)')
            source = source.replace('M -42,-58 Q -42,-72 -28,-64 L 78,-9 Q 92,0 78,9 L -28,64 Q -42,72 -42,58 Z',
                                    'M -55,-76 L 94,0 L -55,76 Z')
        favicons.append(export(source, f'favicon-{size}x{size}.png', size))
    favicons[-1].save(ROOT / 'favicon.ico', format='ICO', sizes=[(16,16),(32,32),(48,48)],
                      append_images=favicons[:-1])
    preview()
    verify()


def preview():
    image = Image.new('RGB', (1200, 970), '#211e17')
    draw = ImageDraw.Draw(image)
    font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 18)
    draw.text((28, 20), 'KADI / transparent artwork and installed-app icons', font=font, fill='white')
    for index, (label, color) in enumerate([('Light', '#f4f1ea'), ('Night', NIGHT), ('Felt', '#17150f')]):
        x = 28 + index * 390
        draw.rectangle((x, 65, x+362, 370), fill=color)
        icon = Image.open(ROOT / 'pwa-icons/icon-512.png').convert('RGBA').resize((280,280), Image.Resampling.LANCZOS)
        image.paste(icon, (x+41, 75), icon)
        draw.text((x, 382), label + ' / transparent standard icon', font=font, fill='white')
        draw.rectangle((x, 425, x+362, 535), fill=color)
        for offset, size in zip((25, 130, 240), (16,32,48)):
            icon = Image.open(ROOT / f'favicon-{size}x{size}.png').convert('RGBA')
            image.paste(icon, (x+offset, 442), icon)
            draw.text((x+offset, 500), str(size)+'px', font=font, fill='#a8a191' if index else '#0c1028')
    for index, shape in enumerate(('Circle mask', 'Rounded mask', 'Apple / opaque', 'Badge / mono')):
        x = 28+index*295
        icon = Image.open(ROOT / ('pwa-icons/icon-maskable-512.png' if index < 2 else 'pwa-icons/apple-touch-icon.png' if index == 2 else 'pwa-icons/badge-72.png')).convert('RGBA')
        if index == 3:
            icon = render((ROOT / 'pwa-icons/badge.svg').read_text(), 960)
        icon = icon.resize((240,240), Image.Resampling.LANCZOS)
        if index < 2:
            mask = Image.new('L', (240,240))
            pen = ImageDraw.Draw(mask)
            if index == 0:
                pen.ellipse((0,0,239,239), fill=255)
            else:
                pen.rounded_rectangle((0,0,239,239), radius=52, fill=255)
            icon.putalpha(mask)
        if index == 3:
            draw.rounded_rectangle((x,600,x+240,840),radius=40, fill='#36312a')
        image.paste(icon,(x,600),icon)
        draw.text((x,860),shape,font=font,fill='white')
    draw.text((28,920), 'Favicons above shown at actual size. Backgrounds are preview surfaces only.',font=font,fill='#cfc8b6')
    image.save(ROOT / 'preview.png',optimize=True)


def verify():
    for name, size in [('master-2048.png',2048), ('pwa-icons/icon-192.png',192),('pwa-icons/icon-512.png',512),('pwa-icons/badge-72.png',72)]+[(f'favicon-{s}x{s}.png',s) for s in (16,32,48)]:
        image = Image.open(ROOT/name).convert('RGBA')
        assert image.size == (size,size), name
        assert image.getpixel((0,0))[3] == 0, name
        bounds = image.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox()
        assert bounds and min(bounds[:2]) > 0 and max(bounds[2:]) < size, (name,bounds)
    for name in ('pwa-icons/icon-maskable-512.png','pwa-icons/apple-touch-icon.png'):
        assert Image.open(ROOT/name).convert('RGBA').getchannel('A').getextrema() == (255,255)
    mask = render((ROOT/'pwa-icons/icon-maskable.svg').read_text().replace('<rect width="1024" height="1024" fill="#0E0D0B"/>',''),1024)
    for y in range(1024):
        for x in range(1024):
            if mask.getpixel((x,y))[3] > 8:
                assert math.hypot(x-512,y-512) <= 409.6, 'Maskable artwork exceeds safe circle'
    ico = Image.open(ROOT/'favicon.ico')
    assert ico.info['sizes'] == {(16,16),(32,32),(48,48)}
    for source, size in zip(('favicon-16x16.png','favicon-32x32.png','favicon-48x48.png'),(16,32,48)):
        assert ico.ico.getimage((size,size)).convert('RGBA').tobytes() == Image.open(ROOT/source).convert('RGBA').tobytes()
    print('Verified export dimensions, transparency, opaque backgrounds, mask safe zone and ICO frames.')


if __name__ == '__main__':
    build()
