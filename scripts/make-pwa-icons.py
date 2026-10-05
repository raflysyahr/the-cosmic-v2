#!/usr/bin/env python3
"""
Bangun ikon PWA + poster splash dari logo splash (video .mp4 atau gambar).

Tujuan: splash bawaan OS (ikon app di atas latar hitam) dan splash web (video logo di
resources/views/app.blade.php) memakai logo yang SAMA, sehingga terlihat seperti satu
splash. OS tidak bisa memutar video dan splash-nya tidak bisa dihilangkan; ini cara
terdekat agar tidak terlihat dua logo berbeda.

Pemakaian (dari root project):
    python3 scripts/make-pwa-icons.py public/lv_0_20260622135734.mp4
    python3 scripts/make-pwa-icons.py public/lv_0_20260622135734.mp4 --time 1.5
    python3 scripts/make-pwa-icons.py logo.png

Kebutuhan: Python 3 + Pillow; untuk input video juga ffmpeg di PATH.
    Termux: pkg install python ffmpeg python-pillow

Keluaran di public/icons/: icon-192.png, icon-512.png, icon-maskable-512.png,
apple-touch-icon.png, favicon-32.png, splash-poster.jpg.
Setelah menjalankan: naikkan VERSION di public/sw.js bila ikon berubah, lalu uninstall dan
install ulang PWA (Android menyimpan ikon/splash saat install).
"""
import argparse
import glob
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageStat

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "icons"
VIDEO_EXT = {".mp4", ".webm", ".mov", ".m4v", ".mkv"}


def fail(msg):
    print(f"Error: {msg}", file=sys.stderr)
    sys.exit(1)


def frame_from_video(video: Path, at):
    ffmpeg = shutil.which("ffmpeg")
    if not ffmpeg:
        fail("ffmpeg tidak ditemukan di PATH (Termux: pkg install ffmpeg).")

    def run(args):
        r = subprocess.run([ffmpeg, "-y", "-loglevel", "error", *args], capture_output=True, text=True)
        if r.returncode != 0:
            fail(f"ffmpeg gagal: {r.stderr.strip()}")

    with tempfile.TemporaryDirectory() as tmp:
        if at is not None:
            run(["-ss", str(at), "-i", str(video), "-frames:v", "1", f"{tmp}/f.png"])
            frames = [f"{tmp}/f.png"]
        else:
            # 4 fps selama 4 detik pertama; pilih frame paling terang = logo paling terlihat.
            run(["-t", "4", "-i", str(video), "-vf", "fps=4", f"{tmp}/f_%03d.png"])
            frames = sorted(glob.glob(f"{tmp}/f_*.png"))
        if not frames:
            fail("tidak ada frame yang bisa diambil dari video.")
        best = max(frames, key=lambda p: ImageStat.Stat(Image.open(p).convert("L")).mean[0])
        return Image.open(best).convert("RGB").copy()


def center_square(img: Image.Image) -> Image.Image:
    w, h = img.size
    s = min(w, h)
    left, top = (w - s) // 2, (h - s) // 2
    return img.crop((left, top, left + s, top + s))


def radial_fade(size, inner=0.62, outer=0.98):
    """Mask: 255 di tengah, memudar ke 0 di tepi (menyatu dengan latar hitam)."""
    m = Image.new("L", (size, size), 0)
    d = ImageDraw.Draw(m)
    r_in, r_out = size * inner / 2, size * outer / 2
    for i in range(256):
        t = i / 255
        r = r_out - (r_out - r_in) * t
        d.ellipse([size / 2 - r, size / 2 - r, size / 2 + r, size / 2 + r], fill=int(255 * t))
    return m.filter(ImageFilter.GaussianBlur(size * 0.01))


def make_any(square, size, out):
    # full-bleed; OS yang memotong sendiri
    square.resize((size, size), Image.LANCZOS).save(out, optimize=True)


def make_maskable(square, size, out, content=0.78):
    # konten dikecilkan + fade ke hitam: aman dari mask bulat/squircle Android
    canvas = Image.new("RGB", (size, size), (0, 0, 0))
    c = int(size * content)
    canvas.paste(square.resize((c, c), Image.LANCZOS), ((size - c) // 2, (size - c) // 2), radial_fade(c))
    canvas.save(out, optimize=True)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("source", type=Path, help="video (.mp4) atau gambar logo")
    ap.add_argument("--time", type=float, default=None, help="ambil frame pada detik ini (default: frame paling terang)")
    args = ap.parse_args()

    src = args.source if args.source.is_absolute() else Path.cwd() / args.source
    if not src.exists():
        fail(f"file tidak ditemukan: {src}")

    frame = frame_from_video(src, args.time) if src.suffix.lower() in VIDEO_EXT else Image.open(src).convert("RGB")
    sq = center_square(frame)

    OUT.mkdir(parents=True, exist_ok=True)
    make_any(sq, 192, OUT / "icon-192.png")
    make_any(sq, 512, OUT / "icon-512.png")
    make_maskable(sq, 512, OUT / "icon-maskable-512.png")
    make_any(sq, 180, OUT / "apple-touch-icon.png")
    make_any(sq, 32, OUT / "favicon-32.png")
    # Poster memakai frame UTUH (rasio asli video) supaya sejajar persis dengan elemen <video>.
    frame.save(OUT / "splash-poster.jpg", quality=88, optimize=True)

    print(f"Frame {frame.size[0]}x{frame.size[1]} dari {src.name}")
    for p in sorted(OUT.glob("*")):
        if p.name in {"icon-192.png", "icon-512.png", "icon-maskable-512.png", "apple-touch-icon.png", "favicon-32.png", "splash-poster.jpg"}:
            print(f"  {p.relative_to(ROOT)}  ({p.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
