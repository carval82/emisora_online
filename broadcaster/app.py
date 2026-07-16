"""
Emisora Broadcaster — consola de transmisión con mezclador integrado.
"""

from __future__ import annotations

import shutil
import sys
import threading
import time
import tkinter as tk
from pathlib import Path
from tkinter import messagebox, scrolledtext, ttk

from api_client import BroadcasterClient, load_config, save_config
from audio_capture import FFmpegCapture, PythonMixerCapture, list_audio_devices

try:
    from PIL import Image, ImageTk

    HAS_PIL = True
except ImportError:
    HAS_PIL = False


def get_app_dir() -> Path:
    if getattr(sys, "frozen", False):
        return Path(sys.executable).resolve().parent
    return Path(__file__).resolve().parent


def bundled_path(name: str) -> Path | None:
    if getattr(sys, "frozen", False):
        candidate = Path(sys._MEIPASS) / name
        if candidate.exists():
            return candidate
    local = get_app_dir() / name
    return local if local.exists() else None


def ensure_config(config_path: Path) -> None:
    if config_path.exists():
        return
    example = bundled_path("config.example.json")
    if example:
        shutil.copy(example, config_path)


APP_DIR = get_app_dir()
CONFIG_PATH = APP_DIR / "config.json"
CHUNKS_DIR = APP_DIR / "temp_chunks"

COLORS = {
    "bg": "#0a0a0f",
    "chassis": "#1c1c24",
    "strip": "#252530",
    "accent": "#7c3aed",
    "mic": "#38bdf8",
    "music": "#f472b6",
    "master": "#fbbf24",
    "live": "#ef4444",
    "live_glow": "#ff2d2d",
    "live_dim": "#991b1b",
    "on": "#22c55e",
    "on_hi": "#4ade80",
    "off": "#3f3f50",
    "text": "#f8fafc",
    "muted": "#94a3b8",
    "border": "#52525b",
    "fader": "#d4d4d8",
    "track": "#18181b",
    "led_off": "#27272a",
    "led_g": "#22c55e",
    "led_y": "#eab308",
    "led_r": "#ef4444",
    "connect": "#6366f1",
    "connect_ok": "#16a34a",
}


class BroadcasterApp(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.title("Emisora Broadcaster")
        self.geometry("880x760")
        self.minsize(860, 720)
        self.configure(bg=COLORS["bg"])

        self.config_data = load_config(CONFIG_PATH)
        self.client: BroadcasterClient | None = None
        self.capture: FFmpegCapture | PythonMixerCapture | None = None
        self.upload_thread: threading.Thread | None = None
        self.upload_stop = threading.Event()
        self.broadcasting = False
        self.connected = False
        self.chunks_sent = 0
        self._logo_image = None
        self._vu_job: str | None = None
        self._mic_on = True
        self._auto_duck = True
        self._syncing_faders = False
        self._stopping = False

        self._setup_theme()
        self._build_ui()
        self._load_devices()
        self._apply_config()

    def _setup_theme(self) -> None:
        style = ttk.Style(self)
        try:
            style.theme_use("clam")
        except tk.TclError:
            pass
        style.configure("TCombobox", fieldbackground=COLORS["strip"], foreground=COLORS["text"], background=COLORS["strip"])
        style.configure("Mixer.TCombobox", fieldbackground=COLORS["strip"], foreground=COLORS["text"], background=COLORS["strip"])
        style.map(
            "TCombobox",
            fieldbackground=[("readonly", COLORS["strip"])],
            foreground=[("readonly", COLORS["text"])],
            background=[("readonly", COLORS["strip"])],
        )

    def _build_ui(self) -> None:
        root = tk.Frame(self, bg=COLORS["bg"], padx=12, pady=8)
        root.pack(fill="both", expand=True)

        footer = tk.Frame(root, bg=COLORS["bg"])
        footer.pack(side="bottom", fill="x", pady=(6, 0))
        self._build_transport(footer)

        body = tk.Frame(root, bg=COLORS["bg"])
        body.pack(side="top", fill="both", expand=True)

        self._build_header(body)
        self._build_connection(body)
        self._build_console(body)
        self._build_log(body)

    def _build_header(self, parent: tk.Frame) -> None:
        header = tk.Frame(parent, bg=COLORS["bg"])
        header.pack(fill="x", pady=(0, 6))

        logo_path = bundled_path("assets/logo.png") or bundled_path("logo.png")
        if logo_path and logo_path.exists() and HAS_PIL:
            try:
                img = Image.open(logo_path).resize((44, 44), Image.Resampling.LANCZOS)
                self._logo_image = ImageTk.PhotoImage(img)
                tk.Label(header, image=self._logo_image, bg=COLORS["bg"]).pack(side="left", padx=(0, 8))
            except Exception:
                pass

        box = tk.Frame(header, bg=COLORS["bg"])
        box.pack(side="left")
        tk.Label(box, text="EMISORA BROADCASTER", font=("Segoe UI", 14, "bold"), bg=COLORS["bg"], fg=COLORS["text"]).pack(anchor="w")
        tk.Label(box, text="Consola de transmisión en vivo", font=("Segoe UI", 9), bg=COLORS["bg"], fg=COLORS["muted"]).pack(anchor="w")

    def _build_connection(self, parent: tk.Frame) -> None:
        panel = tk.Frame(parent, bg=COLORS["chassis"], highlightbackground=COLORS["border"], highlightthickness=1)
        panel.pack(fill="x", pady=(0, 6))

        tk.Label(panel, text=" CONEXIÓN ", font=("Segoe UI", 8, "bold"), bg=COLORS["accent"], fg="white").pack(anchor="w")
        inner = tk.Frame(panel, bg=COLORS["chassis"], padx=10, pady=6)
        inner.pack(fill="x")

        grid = tk.Frame(inner, bg=COLORS["chassis"])
        grid.pack(fill="x")
        self.server_url = self._entry_row(grid, "Servidor", 0, "http://127.0.0.1:8000")
        self.email = self._entry_row(grid, "Email", 1, "")
        self.password = self._entry_row(grid, "Contraseña", 2, "", show="*")
        self.host_name = self._entry_row(grid, "Locutor", 3, "Locutor en vivo")
        grid.columnconfigure(1, weight=1)

        row = tk.Frame(inner, bg=COLORS["chassis"])
        row.pack(fill="x", pady=(8, 0))
        self.btn_connect = tk.Button(row, text="⚡ CONECTAR", font=("Segoe UI", 9, "bold"), bg=COLORS["connect"], fg="white",
                                     relief="raised", bd=2, padx=12, pady=4, cursor="hand2", command=self.connect)
        self.btn_connect.pack(side="right")
        tk.Button(row, text="↻ Dispositivos", bg=COLORS["strip"], fg=COLORS["text"], relief="raised", bd=2, padx=8, pady=4,
                  font=("Segoe UI", 8), command=self._load_devices).pack(side="right", padx=(0, 6))

    def _build_console(self, parent: tk.Frame) -> None:
        panel = tk.Frame(parent, bg=COLORS["chassis"], highlightbackground="#3f3f46", highlightthickness=1)
        panel.pack(fill="x", pady=(0, 6))

        tk.Label(panel, text=" MEZCLADOR ", font=("Segoe UI", 8, "bold"), bg=COLORS["master"], fg="#1c1917").pack(anchor="w")

        desk = tk.Frame(panel, bg=COLORS["chassis"], padx=10, pady=8)
        desk.pack(fill="x")

        top_row = tk.Frame(desk, bg=COLORS["chassis"])
        top_row.pack(fill="x", pady=(0, 8))
        self._auto_duck_var = tk.BooleanVar(value=True)
        tk.Checkbutton(
            top_row, text="Auto-balance mic ↔ música", variable=self._auto_duck_var,
            bg=COLORS["chassis"], fg=COLORS["text"], selectcolor=COLORS["strip"], activebackground=COLORS["chassis"],
            font=("Segoe UI", 9), command=lambda: setattr(self, "_auto_duck", self._auto_duck_var.get()),
        ).pack(side="left")
        tk.Label(top_row, text="Zara → CABLE Input  ·  App ← CABLE Output", font=("Segoe UI", 8),
                 bg=COLORS["chassis"], fg=COLORS["muted"]).pack(side="right")

        faders = tk.Frame(desk, bg=COLORS["chassis"])
        faders.pack(fill="x")
        for col in range(3):
            faders.columnconfigure(col, weight=1, uniform="mixer")

        self.mic_strip, self.mic_vol, self.mic_vu, self.btn_mic_on, self.mic_pct = self._channel_strip(
            faders, 0, "CANAL 1", "MIC", COLORS["mic"], 70, has_mute=True, on_change=self._on_mic_fader,
        )
        self.music_strip, self.cable_vol, self.cable_vu, _, self.music_pct = self._channel_strip(
            faders, 1, "CANAL 2", "MÚSICA", COLORS["music"], 90, has_mute=False, on_change=self._on_music_fader,
        )
        self.master_strip, self.master_vol, self.master_vu, _, self.master_pct = self._channel_strip(
            faders, 2, "MASTER", "OUT", COLORS["master"], 100, has_mute=False, on_change=self._on_master_fader,
        )

        tk.Frame(desk, bg=COLORS["border"], height=1).pack(fill="x", pady=(10, 8))

        devices = tk.Frame(desk, bg=COLORS["chassis"])
        devices.pack(fill="x")
        for col in range(3):
            devices.columnconfigure(col, weight=1, uniform="mixer")

        self.mic_device = self._device_combo(devices, 0, "Entrada micrófono")
        self.cable_device = self._device_combo(devices, 1, "Entrada música (VB-Cable Output)")
        out_cell = tk.Frame(devices, bg=COLORS["chassis"])
        out_cell.grid(row=0, column=2, sticky="ew", padx=6)
        tk.Label(out_cell, text="Salida", font=("Segoe UI", 8), bg=COLORS["chassis"], fg=COLORS["muted"]).pack(anchor="w")
        tk.Label(
            out_cell, text="Mezcla → transmisión en vivo", font=("Segoe UI", 8),
            bg=COLORS["strip"], fg=COLORS["master"], padx=8, pady=6,
        ).pack(fill="x", pady=(2, 0))

    FADER_H = 108
    VU_H = 108
    MUTE_ROW_H = 28

    def _channel_strip(
        self, parent: tk.Frame, column: int, channel: str, label: str, color: str, default: int, *,
        has_mute: bool = False, on_change=None,
    ) -> tuple[tk.Frame, tk.Scale, tk.Canvas, tk.Button | None, tk.Label]:
        strip = tk.Frame(
            parent, bg=COLORS["strip"], highlightbackground="#3f3f46",
            highlightthickness=1, padx=8, pady=8,
        )
        strip.grid(row=0, column=column, sticky="nsew", padx=4)

        tk.Label(strip, text=channel, font=("Consolas", 8), bg=COLORS["strip"], fg=COLORS["muted"]).pack()
        tk.Label(strip, text=label, font=("Segoe UI", 11, "bold"), bg=COLORS["strip"], fg=color).pack(pady=(0, 4))

        mute_row = tk.Frame(strip, bg=COLORS["strip"], height=self.MUTE_ROW_H)
        mute_row.pack(fill="x")
        mute_row.pack_propagate(False)

        mute_btn = None
        if has_mute:
            mute_btn = tk.Button(
                mute_row, text="ON", font=("Segoe UI", 8, "bold"), width=5, bg=COLORS["on"], fg="#052e16",
                relief="raised", bd=2, cursor="hand2", command=self._toggle_mic,
            )
            mute_btn.pack(expand=True)

        body = tk.Frame(strip, bg=COLORS["strip"])
        body.pack(pady=4, anchor="center")

        vu = tk.Canvas(
            body, width=20, height=self.VU_H, bg=COLORS["track"],
            highlightthickness=1, highlightbackground="#3f3f46",
        )
        vu.pack(side="left", padx=(0, 8))
        self._draw_vu_idle(vu)

        vol = tk.Scale(
            body, from_=100, to=0, orient="vertical", length=self.FADER_H, width=24, showvalue=False,
            bg=COLORS["strip"], fg=COLORS["fader"], troughcolor="#3f3f46", activebackground=color,
            highlightthickness=0, sliderrelief="raised", sliderlength=32,
            command=lambda v: on_change(v) if on_change else self._on_mix_change(),
        )
        vol.set(default)
        vol.pack(side="left")

        pct = tk.Label(strip, text=f"{default}%", font=("Consolas", 11, "bold"), bg=COLORS["strip"], fg=color)
        pct.pack(pady=(6, 0))

        return strip, vol, vu, mute_btn, pct

    def _device_combo(self, parent: tk.Frame, column: int, hint: str) -> ttk.Combobox:
        cell = tk.Frame(parent, bg=COLORS["chassis"])
        cell.grid(row=0, column=column, sticky="ew", padx=4)
        tk.Label(cell, text=hint, font=("Segoe UI", 8), bg=COLORS["chassis"], fg=COLORS["muted"]).pack(anchor="w")
        combo = ttk.Combobox(cell, state="readonly", font=("Segoe UI", 8), style="Mixer.TCombobox")
        combo.pack(fill="x", pady=(3, 0), ipady=2)
        return combo

    def _build_transport(self, parent: tk.Frame) -> None:
        row = tk.Frame(parent, bg=COLORS["bg"])
        row.pack(fill="x", pady=(0, 8))

        self.btn_start = tk.Button(row, text="● ON AIR", font=("Segoe UI", 12, "bold"), bg=COLORS["live"], fg="white",
                                   relief="raised", bd=3, padx=20, pady=8, cursor="hand2", command=self.start_broadcast)
        self.btn_start.pack(side="left", padx=(0, 8))

        self.btn_stop = tk.Button(row, text="■ DETENER", font=("Segoe UI", 11, "bold"), bg=COLORS["off"], fg=COLORS["muted"],
                                  relief="raised", bd=2, padx=14, pady=7, state="disabled", command=self.stop_broadcast)
        self.btn_stop.pack(side="left")

        self.status_label = tk.Label(row, text="● Listo", font=("Segoe UI", 10, "bold"), bg=COLORS["bg"], fg=COLORS["muted"])
        self.status_label.pack(side="right")

    def _build_log(self, parent: tk.Frame) -> None:
        panel = tk.Frame(parent, bg=COLORS["chassis"], highlightbackground=COLORS["border"], highlightthickness=1)
        panel.pack(fill="both", expand=True, pady=(6, 0))
        tk.Label(panel, text=" LOG ", font=("Segoe UI", 9, "bold"), bg=COLORS["strip"], fg=COLORS["text"]).pack(anchor="w")
        self.log = scrolledtext.ScrolledText(panel, height=4, wrap="word", font=("Consolas", 8), bg="#0f0f14", fg="#cbd5e1", relief="flat")
        self.log.pack(fill="both", expand=True, padx=6, pady=6)

    def _entry_row(self, parent: tk.Frame, label: str, row: int, default: str, show: str | None = None) -> tk.Entry:
        tk.Label(parent, text=label, bg=COLORS["chassis"], fg=COLORS["muted"], width=10, anchor="w").grid(row=row, column=0, sticky="w", pady=3)
        entry = tk.Entry(parent, bg=COLORS["strip"], fg=COLORS["text"], insertbackground="white", relief="flat", show=show or "")
        entry.grid(row=row, column=1, sticky="ew", padx=(6, 0), pady=2, ipady=2)
        if default:
            entry.insert(0, default)
        return entry

    def _update_pct(self, scale: tk.Scale, label: tk.Label) -> None:
        label.config(text=f"{int(scale.get())}%")

    def _on_mic_fader(self, value: str) -> None:
        mic = int(float(value))
        self._update_pct(self.mic_vol, self.mic_pct)
        if self._auto_duck and not self._syncing_faders:
            self._syncing_faders = True
            music = max(10, min(100, 100 - int(mic * 0.75)))
            self.cable_vol.set(music)
            self._update_pct(self.cable_vol, self.music_pct)
            self._syncing_faders = False
        self._on_mix_change()

    def _on_music_fader(self, value: str) -> None:
        music = int(float(value))
        self._update_pct(self.cable_vol, self.music_pct)
        if self._auto_duck and not self._syncing_faders:
            self._syncing_faders = True
            mic = max(0, min(100, int((100 - music) / 0.75)))
            self.mic_vol.set(mic)
            self._update_pct(self.mic_vol, self.mic_pct)
            self._syncing_faders = False
        self._on_mix_change()

    def _on_master_fader(self, value: str) -> None:
        self._update_pct(self.master_vol, self.master_pct)
        self._on_mix_change()

    def _toggle_mic(self) -> None:
        self._mic_on = not self._mic_on
        if self._mic_on:
            self.btn_mic_on.config(text="ON", bg=COLORS["on"], fg="#052e16")
        else:
            self.btn_mic_on.config(text="OFF", bg=COLORS["off"], fg=COLORS["muted"])
        self._on_mix_change()

    def _draw_vu_idle(self, canvas: tk.Canvas) -> None:
        canvas.delete("all")
        h = int(canvas["height"])
        step = max(8, h // 12)
        for i in range(12):
            y = h - 8 - i * step
            canvas.create_rectangle(3, y, 15, y + step - 2, fill=COLORS["led_off"], outline="")

    def _draw_vu_level(self, canvas: tk.Canvas, level: int) -> None:
        canvas.delete("all")
        h = int(canvas["height"])
        step = max(8, h // 12)
        for i in range(12):
            y = h - 8 - i * step
            color = COLORS["led_off"]
            if i < level:
                color = COLORS["led_g"] if i < 8 else (COLORS["led_y"] if i < 10 else COLORS["led_r"])
            canvas.create_rectangle(3, y, 15, y + step - 2, fill=color, outline="")

    def _animate_vu(self) -> None:
        if not self.broadcasting:
            return
        import random

        mic_lvl = int(self.mic_vol.get() / 100 * 10) if self._mic_on else 0
        music_lvl = int(self.cable_vol.get() / 100 * 10)
        master_lvl = int(self.master_vol.get() / 100 * max(mic_lvl, music_lvl, 1))
        self._draw_vu_level(self.mic_vu, min(12, mic_lvl + random.randint(0, 1)))
        self._draw_vu_level(self.cable_vu, min(12, music_lvl + random.randint(0, 1)))
        self._draw_vu_level(self.master_vu, min(12, master_lvl))
        self._vu_job = self.after(150, self._animate_vu)

    def _effective_levels(self) -> tuple[float, float, float, bool]:
        return (
            self.mic_vol.get() / 100.0,
            self.cable_vol.get() / 100.0,
            self.master_vol.get() / 100.0,
            self._mic_on,
        )

    def _on_mix_change(self) -> None:
        if not self.broadcasting or not self.capture:
            return
        self._apply_mix_levels()

    def _apply_mix_levels(self) -> None:
        if not self.capture or not hasattr(self.capture, "set_levels"):
            return
        mic_v, cable_v, master_v, mic_on = self._effective_levels()
        self.capture.set_levels(mic_v, cable_v, master_v, mic_on)

    def _pick_device(self, devices: list[str], preferred: str, keywords: tuple[str, ...], exclude: str = "") -> str:
        if preferred and preferred in devices and preferred != exclude:
            return preferred
        for keyword in keywords:
            for name in devices:
                if keyword in name.upper() and name != exclude:
                    return name
        for name in devices:
            if name != exclude:
                return name
        return ""

    def _apply_config(self) -> None:
        for entry, key, default in (
            (self.server_url, "server_url", "http://127.0.0.1:8000"),
            (self.email, "email", ""),
            (self.host_name, "host_name", "Locutor en vivo"),
        ):
            val = self.config_data.get(key, default)
            if val:
                entry.delete(0, "end")
                entry.insert(0, val)

        self.mic_vol.set(int(self.config_data.get("mic_volume", 70)))
        self.cable_vol.set(int(self.config_data.get("cable_volume", 90)))
        self.master_vol.set(int(self.config_data.get("master_volume", 100)))
        self._update_pct(self.mic_vol, self.mic_pct)
        self._update_pct(self.cable_vol, self.music_pct)
        self._update_pct(self.master_vol, self.master_pct)

        self._mic_on = bool(self.config_data.get("mic_enabled", True))
        self._auto_duck = bool(self.config_data.get("auto_duck", True))
        self._auto_duck_var.set(self._auto_duck)
        self.btn_mic_on.config(text="ON" if self._mic_on else "OFF",
                               bg=COLORS["on"] if self._mic_on else COLORS["off"],
                               fg="#052e16" if self._mic_on else COLORS["muted"])

        if self.config_data.get("token"):
            self.client = BroadcasterClient(self.config_data["server_url"], self.config_data["token"], self.config_data.get("email", ""))
            self.connected = True
            self.btn_connect.config(text="✓ CONECTADO", bg=COLORS["connect_ok"])
            self.status_label.config(text="● Token guardado", fg=COLORS["on"])

    def _load_devices(self) -> None:
        try:
            devices = list_audio_devices(self.config_data.get("ffmpeg_path", ""))
            self.mic_device["values"] = [""] + devices
            self.cable_device["values"] = [""] + devices

            legacy = self.config_data.get("audio_device", "")
            mic = self._pick_device(devices, self.config_data.get("mic_device", ""), ("MICRO", "MIC ", "MICRÓFONO", "HEADSET"))
            cable = self._pick_device(devices, self.config_data.get("cable_device", legacy), ("CABLE OUTPUT", "VB-AUDIO"), exclude=mic)

            if not cable:
                cable = self._pick_device(devices, legacy, ("CABLE OUTPUT", "VB-AUDIO"))

            self.mic_device.set(mic)
            self.cable_device.set(cable)
            self.log_line(f"{len(devices)} dispositivos — mic: {mic or '—'} | música: {cable or '—'}")
        except Exception as exc:
            self.log_line(f"Error audio: {exc}")

    def log_line(self, message: str) -> None:
        self.log.insert("end", f"[{time.strftime('%H:%M:%S')}] {message}\n")
        self.log.see("end")

    def connect(self) -> None:
        server = self.server_url.get().strip().rstrip("/")
        email = self.email.get().strip()
        password = self.password.get()
        if not server or not email or not password:
            messagebox.showerror("Error", "Completa servidor, email y contraseña")
            return
        try:
            client = BroadcasterClient(server)
            data = client.login(email, password)
            self.client = client
            self.connected = True
            self._save_user_config(server, email, data.get("name", email))
            self.btn_connect.config(text="✓ CONECTADO", bg=COLORS["connect_ok"])
            self.status_label.config(text=f"● {data.get('name', email)}", fg=COLORS["on"])
            self.log_line("Conexión OK")
        except Exception as exc:
            messagebox.showerror("Error", str(exc))
            self.log_line(f"Login fallido: {exc}")

    def _save_user_config(self, server: str, email: str, name: str) -> None:
        self.config_data.update({
            "server_url": server, "email": email,
            "token": self.client.token if self.client else "",
            "host_name": self.host_name.get().strip() or name,
            "mic_device": self.mic_device.get().strip(),
            "cable_device": self.cable_device.get().strip(),
            "mic_volume": self.mic_vol.get(), "cable_volume": self.cable_vol.get(),
            "master_volume": self.master_vol.get(), "mic_enabled": self._mic_on,
            "auto_duck": self._auto_duck_var.get(),
        })
        save_config(CONFIG_PATH, self.config_data)

    def start_broadcast(self) -> None:
        if self.broadcasting or self._stopping:
            return
        if not self.client or not self.client.token:
            messagebox.showerror("Error", "Conecta primero")
            return

        mic = self.mic_device.get().strip()
        cable = self.cable_device.get().strip()
        use_mic = self._mic_on and bool(mic)

        if not cable and not use_mic:
            messagebox.showerror("Error", "Selecciona VB-Cable (música) y/o micrófono")
            return
        if use_mic and cable and mic == cable:
            messagebox.showerror("Error", "Micrófono y música no pueden ser el mismo dispositivo.\n\nMúsica = CABLE Output\nMic = tu micrófono")
            return

        try:
            host = self.host_name.get().strip() or "Locutor en vivo"
            self.client.start(host)

            segment = int(self.config_data.get("segment_seconds", 1))
            bitrate = str(self.config_data.get("bitrate", "96000"))
            ffmpeg_path = str(self.config_data.get("ffmpeg_path", ""))
            mic_v, cable_v, master_v, _ = self._effective_levels()

            # Solo música (Zara): captura simple — más estable que mezclador dual
            if cable and not use_mic:
                self.capture = FFmpegCapture(cable, CHUNKS_DIR, segment, bitrate, ffmpeg_path)
                self.log_line(f"Modo directo: {cable}")
            else:
                self.capture = PythonMixerCapture(
                    mic_device=mic if use_mic else "",
                    cable_device=cable,
                    chunks_dir=CHUNKS_DIR,
                    segment_seconds=segment,
                    bitrate=bitrate,
                    ffmpeg_path=ffmpeg_path,
                    mic_volume=mic_v,
                    cable_volume=cable_v,
                    master_volume=master_v,
                    mic_enabled=use_mic,
                )
                self.log_line(f"Modo mezcla (tiempo real): mic={mic or 'off'} | música={cable or 'off'}")

            self.capture.start()
            self.upload_stop.clear()
            self.chunks_sent = 0
            self.upload_thread = threading.Thread(target=self._upload_loop, daemon=True)
            self.upload_thread.start()

            self.broadcasting = True
            self.btn_start.config(state="disabled", bg=COLORS["live_dim"])
            self.btn_stop.config(state="normal", bg="#f97316", fg="white")
            self.status_label.config(text="● EN VIVO", fg=COLORS["live_glow"])
            self._animate_vu()
            self._save_user_config(self.server_url.get().strip().rstrip("/"), self.email.get().strip(), host)
        except Exception as exc:
            messagebox.showerror("Error", str(exc))
            self.log_line(f"Error inicio: {exc}")
            self.stop_broadcast()

    def stop_broadcast(self) -> None:
        if self._stopping:
            return
        if not self.broadcasting and not self.capture:
            return

        self._stopping = True
        self.upload_stop.set()
        if self._vu_job:
            self.after_cancel(self._vu_job)
            self._vu_job = None

        was_live = self.broadcasting
        self.broadcasting = False
        self.btn_start.config(state="disabled", bg=COLORS["live_dim"])
        self.btn_stop.config(state="disabled", text="■ DETENIENDO...", bg=COLORS["off"], fg=COLORS["muted"])
        self.status_label.config(text="● Deteniendo...", fg=COLORS["muted"])

        def worker() -> None:
            err_msg = ""
            try:
                if self.client and was_live:
                    self.client.stop()
            except Exception as exc:
                err_msg = f"Error stop servidor: {exc}"

            capture = self.capture
            self.capture = None
            if capture:
                try:
                    capture.stop()
                    tail = capture.stderr_tail()
                    if tail.strip():
                        err_msg = f"{err_msg}\n{tail.strip()[:400]}".strip()
                except Exception as exc:
                    err_msg = f"{err_msg}\nError captura: {exc}".strip()

            chunks = self.chunks_sent
            self.after(0, lambda: self._finish_stop(err_msg, chunks))

        threading.Thread(target=worker, daemon=True).start()

    def _finish_stop(self, err_msg: str, chunks: int) -> None:
        self._stopping = False
        self.btn_start.config(state="normal", bg=COLORS["live"])
        self.btn_stop.config(state="disabled", text="■ DETENER", bg=COLORS["off"], fg=COLORS["muted"])
        self.status_label.config(text="● Detenido", fg=COLORS["muted"])
        for vu in (self.mic_vu, self.cable_vu, self.master_vu):
            self._draw_vu_idle(vu)
        if err_msg:
            self.log_line(err_msg[:400])
        self.log_line(f"Detenido ({chunks} segmentos)")

    def _upload_loop(self) -> None:
        uploaded: set[str] = set()
        while not self.upload_stop.is_set():
            if not self.client:
                break
            for path in sorted(CHUNKS_DIR.glob("chunk_*.webm")):
                if path.name in uploaded:
                    continue
                try:
                    if path.stat().st_size < 100:
                        continue
                    s1 = path.stat().st_size
                    time.sleep(0.15)
                    if path.stat().st_size != s1:
                        continue
                    data = self.client.upload_chunk(path)
                    uploaded.add(path.name)
                    self.chunks_sent += 1
                    self.after(0, lambda i=data.get("index"), s=data.get("size"): self.log_line(f"✓ chunk {i} ({round((s or 0)/1024)} KB)"))
                    path.unlink(missing_ok=True)
                except Exception as exc:
                    self.after(0, lambda e=exc: self.log_line(f"Error subida: {e}"))
                    time.sleep(1)
            time.sleep(0.05)

    def on_close(self) -> None:
        if self.broadcasting and not messagebox.askyesno("Salir", "Transmisión activa. ¿Detener y salir?"):
            return
        if self.broadcasting:
            self.stop_broadcast()
        self.destroy()


def main() -> None:
    ensure_config(CONFIG_PATH)
    app = BroadcasterApp()
    app.protocol("WM_DELETE_WINDOW", app.on_close)
    app.mainloop()


if __name__ == "__main__":
    main()
