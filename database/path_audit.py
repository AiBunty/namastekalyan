import os, re, json

root = r'D:\GITHUB Projects\Namaste Kalyan\namastekalyan\public'

# 1. Check all HTML pages for any inline style background-image or src with local paths
print("=== INLINE ASSET REFS IN HTML ===")
for dirpath, dirs, files in os.walk(root):
    # Skip admin for brevity, check key dirs
    rel = os.path.relpath(dirpath, root)
    if rel.startswith('assets') or rel.startswith('css') or rel.startswith('js'):
        continue
    for fn in files:
        if fn.endswith('.html'):
            fpath = os.path.join(dirpath, fn)
            txt = open(fpath, encoding='utf-8', errors='ignore').read()
            page = os.path.relpath(fpath, root)
            # Find inline background-image url()
            bg = re.findall(r'background(?:-image)?:\s*url\([^)]+\)', txt)
            # Find style="..." with url
            style_url = re.findall(r'style="[^"]*url\([^)]+\)[^"]*"', txt)
            # Find any src/href pointing to local relative path with ../ or direct
            local_src = re.findall(r'(?:src|href)="(?!https?://|mailto:|tel:|#|data:)([^"]+)"', txt)
            if bg:
                print(f"\n{page} BACKGROUND-IMAGE:")
                for b in bg[:5]: print(f"  {b}")
            if style_url:
                print(f"\n{page} STYLE URL:")
                for s in style_url[:3]: print(f"  {s}")

print("\n=== JS FILES - ASSET PATHS ===")
js_dir = os.path.join(root, 'js')
for fn in os.listdir(js_dir):
    fpath = os.path.join(js_dir, fn)
    if fn.endswith('.js') and os.path.isfile(fpath):
        txt = open(fpath, encoding='utf-8', errors='ignore').read()
        # Look for /assets or /css or /js patterns
        matches = re.findall(r'["\'](\/?(?:assets|css|js)/[^"\'`\s]+)["\']', txt)
        if matches:
            print(f"\n{fn}:")
            for m in matches[:8]: print(f"  {m}")

print("\n=== SHARED-SITE-CHROME.JS snippets ===")
sc = open(os.path.join(js_dir, 'shared-site-chrome.js'), encoding='utf-8', errors='ignore').read()
# First 200 chars and any path-like refs
print(sc[:300])

print("\nDone.")
