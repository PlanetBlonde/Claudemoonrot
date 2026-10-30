"""Rebuild the crystal shelf in site/index.html from tools/products.json.

Run from the repo root:  python3 tools/build_shop.py
Everything between <!-- shop:start --> and <!-- shop:end --> is replaced.
"""
import html
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent
PAGE = ROOT / "site" / "index.html"
DATA = ROOT / "tools" / "products.json"

SHAPES = {
    "tower": '<path d="M50 6 L68 26 L68 112 L32 112 L32 26 Z"/><path d="M32 26 H68 M50 6 V26"/>',
    "palm": '<ellipse cx="50" cy="60" rx="36" ry="27" transform="rotate(-18 50 60)"/><path d="M28 54 q16 -14 40 -8" />',
}


def shot(item):
    photos = item.get("photos") or []
    if not photos:
        return (f'<svg class="ph" viewBox="0 0 100 120" aria-hidden="true" style="--tint:{item["tint"]}">'
                f'{SHAPES[item["shape"]]}</svg>\n            <span class="soon">photo soon</span>')
    alt = html.escape(item["alt"])
    out = f'<img src="img/{photos[0]}" alt="{alt}" width="800" height="800" loading="lazy">'
    if len(photos) > 1:
        # Second angle fades in on hover; decorative, so screen readers skip it.
        out += f'\n            <img class="alt" src="img/{photos[1]}" alt="" width="800" height="800" loading="lazy">'
    return out


def card(item):
    price = f'${item["price"]}' if item.get("price") is not None else "Price soon"
    link = item.get("link")
    buy = (f'<a class="buy" href="{link}" target="_blank" rel="noopener">Buy &#8599;</a>' if link
           else '<span class="buy off">Checkout soon</span>')
    return f'''        <article class="product reveal">
          <div class="shot">
            {shot(item)}
          </div>
          <div class="info">
            <p class="kind">{item["kind"]}</p>
            <h3>{html.escape(item["name"])}</h3>
            <p>{html.escape(item["desc"])}</p>
            <div class="buyrow"><span class="price">{price}</span>{buy}</div>
          </div>
        </article>
'''


def build():
    groups = json.loads(DATA.read_text())
    parts = []
    for g in groups:
        parts.append(f'      <h3 class="shelf">{html.escape(g["group"])}</h3>\n      <div class="products">\n')
        parts.extend(card(i) for i in g["items"])
        parts.append("      </div>\n")
    page = PAGE.read_text()
    new, n = re.subn(r"(<!-- shop:start -->\n).*?(\s*<!-- shop:end -->)",
                     lambda m: m.group(1) + "".join(parts).rstrip("\n") + m.group(2),
                     page, flags=re.S)
    if n != 1:
        raise SystemExit("shop markers not found in index.html")
    PAGE.write_text(new)
    print(f"built {sum(len(g['items']) for g in groups)} listings in {len(groups)} groups")


if __name__ == "__main__":
    build()
