"""Build an installable ZIP from an explicit, dependency-free allowlist."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

root = Path(__file__).resolve().parent.parent
output = root / "dist" / "awesomux-version-sync.zip"
output.parent.mkdir(exist_ok=True)
with ZipFile(output, "w", ZIP_DEFLATED) as archive:
    for name in ("awesomux-version-sync.php", "uninstall.php", "readme.txt", "LICENSE"):
        archive.write(root / name, f"awesomux-version-sync/{name}")
print(output)
