import os, re

root = r'D:\GITHUB Projects\Namaste Kalyan\namastekalyan\public'

print('=== REMAINING BARE assets/ IN JS FILES ===')
for fn in ['shared-site-chrome.js', 'script.js']:
    fpath = os.path.join(root, 'js', fn)
    txt = open(fpath, encoding='utf-8').read()
    # Find ALL occurrences of assets/ in string literals
    all_assets = re.findall(r'["\']([^"\']*assets/[^"\']*)["\']', txt)
    bare = [m for m in all_assets if not m.startswith('../')]
    if bare:
        print(f'{fn}: STILL HAS BARE PATHS:')
        for m in bare[:8]: print(f'  {m}')
    else:
        print(f'{fn}: OK')

print()
print('=== REMAINING BARE assets/ IN EVENTS PAGES ===')
for fn in ['index.html', 'event.html', 'verification.html']:
    fpath = os.path.join(root, 'events', fn)
    if not os.path.exists(fpath): continue
    txt = open(fpath, encoding='utf-8', errors='ignore').read()
    all_assets = re.findall(r'["\']([^"\']*assets/[^"\']*)["\']', txt)
    bare = [m for m in all_assets if not m.startswith('../') and not m.startswith('http')]
    if bare:
        print(f'events/{fn}: BARE PATHS:')
        for m in bare[:5]: print(f'  {m}')
    else:
        print(f'events/{fn}: OK')

print()
print('=== script.js BUILDURL FUNCTIONS ===')
txt = open(os.path.join(root, 'js', 'script.js'), encoding='utf-8').read()
for i, line in enumerate(txt.splitlines(), 1):
    if ('event.html' in line or 'events.html' in line or "'/events" in line or '`/events' in line):
        print(f'  L{i}: {line.strip()}')
