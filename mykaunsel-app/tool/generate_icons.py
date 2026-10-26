"""
Makes the app icons (real app: Android + iOS, test app: Android with a red DEV tag).  Run from mykaunsel-app/:

    python tool/generate_icons.py

Why a script: the icon tool treats any flutter_launcher_icons-<flavor>.yaml in the project folder as "flavors only" and then
skips the main configuration. So the real icon is made first (flutter_launcher_icons.yaml), then the dev configuration is
copied in just for its own run and removed again.
"""
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEV = ROOT / 'tool' / 'flutter_launcher_icons-dev.yaml'
DEV_COPY = ROOT / 'flutter_launcher_icons-dev.yaml'


def run(*args: str) -> None:
    result = subprocess.run(['dart', 'run', 'flutter_launcher_icons', *args], cwd=ROOT, shell=sys.platform == 'win32')
    if result.returncode != 0:
        raise SystemExit(result.returncode)


run('-f', 'flutter_launcher_icons.yaml')

shutil.copyfile(DEV, DEV_COPY)
try:
    run()
finally:
    DEV_COPY.unlink(missing_ok=True)
