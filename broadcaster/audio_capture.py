"""Captura y mezcla de audio con FFmpeg (Windows DirectShow)."""

from __future__ import annotations

import array
import re
import shutil
import subprocess
import sys
import threading
import time
from pathlib import Path

CREATE_NO_WINDOW = getattr(subprocess, "CREATE_NO_WINDOW", 0x08000000)


def _hidden_subprocess_kwargs() -> dict:
    if sys.platform != "win32":
        return {}
    startupinfo = subprocess.STARTUPINFO()
    startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
    startupinfo.wShowWindow = subprocess.SW_HIDE
    return {
        "startupinfo": startupinfo,
        "creationflags": CREATE_NO_WINDOW,
    }


def find_ffmpeg(custom_path: str = "") -> str:
    if custom_path and Path(custom_path).is_file():
        return custom_path

    path = shutil.which("ffmpeg")
    if path:
        return path

    for candidate in (
        r"C:\ffmpeg\bin\ffmpeg.exe",
        r"C:\Program Files\ffmpeg\bin\ffmpeg.exe",
    ):
        if Path(candidate).is_file():
            return candidate

    raise RuntimeError(
        "FFmpeg no está instalado. Descárgalo de https://ffmpeg.org/download.html "
        "y agrégalo al PATH, o indica ffmpeg_path en config.json"
    )


def list_audio_devices(custom_ffmpeg: str = "") -> list[str]:
    ffmpeg = find_ffmpeg(custom_ffmpeg)
    result = subprocess.run(
        [ffmpeg, "-hide_banner", "-list_devices", "true", "-f", "dshow", "-i", "dummy"],
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        **_hidden_subprocess_kwargs(),
    )
    combined = (result.stdout or "") + "\n" + (result.stderr or "")
    devices: list[str] = []

    for line in combined.splitlines():
        if "(audio)" in line.lower():
            match = re.search(r'"([^"]+)"', line)
            if match:
                devices.append(match.group(1))
                continue

    if devices:
        return devices

    in_audio = False
    for line in combined.splitlines():
        if "DirectShow audio devices" in line:
            in_audio = True
            continue
        if "DirectShow video devices" in line:
            break
        if not in_audio:
            continue
        match = re.search(r'"([^"]+)"', line)
        if match and "Alternative name" not in line:
            devices.append(match.group(1))

    return devices


def build_capture_command(
    ffmpeg: str,
    device_name: str,
    output_pattern: str,
    segment_seconds: int = 1,
    bitrate: str = "96000",
) -> list[str]:
    return [
        ffmpeg,
        "-hide_banner",
        "-loglevel",
        "error",
        "-f",
        "dshow",
        "-i",
        f"audio={device_name}",
        "-ac",
        "2",
        "-ar",
        "48000",
        "-c:a",
        "libopus",
        "-b:a",
        bitrate,
        "-f",
        "segment",
        "-segment_time",
        str(segment_seconds),
        "-reset_timestamps",
        "1",
        output_pattern,
    ]


def build_mixer_command(
    ffmpeg: str,
    mic_device: str,
    cable_device: str,
    output_pattern: str,
    *,
    mic_volume: float = 1.0,
    cable_volume: float = 0.85,
    master_volume: float = 1.0,
    mic_enabled: bool = True,
    segment_seconds: int = 1,
    bitrate: str = "96000",
) -> list[str]:
    inputs: list[str] = []
    filters: list[str] = []
    mix_labels: list[str] = []
    idx = 0

    use_mic = bool(mic_device) and mic_enabled
    use_cable = bool(cable_device)

    if not use_mic and not use_cable:
        raise ValueError("Selecciona micrófono y/o música (VB-Cable)")

    if use_mic:
        inputs.extend(["-f", "dshow", "-i", f"audio={mic_device}"])
        filters.append(f"[{idx}:a]volume={mic_volume:.3f}[mic]")
        mix_labels.append("[mic]")
        idx += 1

    if use_cable:
        inputs.extend(["-f", "dshow", "-i", f"audio={cable_device}"])
        filters.append(f"[{idx}:a]volume={cable_volume:.3f}[cable]")
        mix_labels.append("[cable]")
        idx += 1

    if len(mix_labels) == 1:
        filter_complex = filters[0].replace(mix_labels[0], "[mix]")
    else:
        filter_complex = (
            ";".join(filters)
            + ";"
            + "".join(mix_labels)
            + f"amix=inputs={len(mix_labels)}:duration=longest:dropout_transition=2[mix]"
        )

    if master_volume != 1.0:
        filter_complex += f";[mix]volume={master_volume:.3f}[out]"
        map_label = "[out]"
    else:
        filter_complex = filter_complex.replace("[mix]", "[out]")
        map_label = "[out]"

    return [
        ffmpeg,
        "-hide_banner",
        "-loglevel",
        "error",
        *inputs,
        "-filter_complex",
        filter_complex,
        "-map",
        map_label,
        "-ac",
        "2",
        "-ar",
        "48000",
        "-c:a",
        "libopus",
        "-b:a",
        bitrate,
        "-f",
        "segment",
        "-segment_time",
        str(segment_seconds),
        "-reset_timestamps",
        "1",
        output_pattern,
    ]


class FFmpegCapture:
    def __init__(
        self,
        device_name: str,
        chunks_dir: Path,
        segment_seconds: int = 1,
        bitrate: str = "96000",
        ffmpeg_path: str = "",
    ):
        self.device_name = device_name
        self.chunks_dir = chunks_dir
        self.segment_seconds = segment_seconds
        self.bitrate = bitrate
        self.process: subprocess.Popen | None = None
        self.ffmpeg = find_ffmpeg(ffmpeg_path)
        self._stderr_lines: list[str] = []

    def start(self) -> None:
        self.chunks_dir.mkdir(parents=True, exist_ok=True)
        for file in self.chunks_dir.glob("*.webm"):
            file.unlink(missing_ok=True)

        pattern = str(self.chunks_dir / "chunk_%05d.webm")
        command = build_capture_command(
            self.ffmpeg,
            self.device_name,
            pattern,
            self.segment_seconds,
            self.bitrate,
        )
        self._spawn(command)
        threading.Thread(target=self._drain_stderr, daemon=True).start()

    def _drain_stderr(self) -> None:
        if not self.process or not self.process.stderr:
            return
        try:
            for line in self.process.stderr:
                text = line.rstrip()
                if text:
                    self._stderr_lines.append(text)
                    if len(self._stderr_lines) > 50:
                        del self._stderr_lines[:25]
        except Exception:
            pass

    def _spawn(self, command: list[str]) -> None:
        self.process = subprocess.Popen(
            command,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
            text=True,
            encoding="utf-8",
            errors="replace",
            **_hidden_subprocess_kwargs(),
        )

    def stop(self) -> None:
        if self.process and self.process.poll() is None:
            self.process.terminate()
            try:
                self.process.wait(timeout=2)
            except subprocess.TimeoutExpired:
                self.process.kill()
        self.process = None

    def stderr_tail(self) -> str:
        return "\n".join(self._stderr_lines[-20:])


class FFmpegMixerCapture:
    """Mezcla micrófono + VB-Cable/Zara con controles de volumen."""

    def __init__(
        self,
        mic_device: str,
        cable_device: str,
        chunks_dir: Path,
        segment_seconds: int = 1,
        bitrate: str = "96000",
        ffmpeg_path: str = "",
        mic_volume: float = 1.0,
        cable_volume: float = 0.85,
        master_volume: float = 1.0,
        mic_enabled: bool = True,
    ):
        self.mic_device = mic_device
        self.cable_device = cable_device
        self.chunks_dir = chunks_dir
        self.segment_seconds = segment_seconds
        self.bitrate = bitrate
        self.ffmpeg = find_ffmpeg(ffmpeg_path)
        self.mic_volume = mic_volume
        self.cable_volume = cable_volume
        self.master_volume = master_volume
        self.mic_enabled = mic_enabled
        self.process: subprocess.Popen | None = None
        self._lock = threading.Lock()
        self._restart_timer: threading.Timer | None = None

    def set_levels(
        self,
        mic_volume: float,
        cable_volume: float,
        master_volume: float,
        mic_enabled: bool,
        *,
        restart_delay: float = 0.6,
    ) -> None:
        with self._lock:
            self.mic_volume = mic_volume
            self.cable_volume = cable_volume
            self.master_volume = master_volume
            self.mic_enabled = mic_enabled

            if not self.process or self.process.poll() is not None:
                return

            if self._restart_timer:
                self._restart_timer.cancel()

            self._restart_timer = threading.Timer(restart_delay, self._restart_capture)
            self._restart_timer.daemon = True
            self._restart_timer.start()

    def start(self) -> None:
        self.chunks_dir.mkdir(parents=True, exist_ok=True)
        for file in self.chunks_dir.glob("*.webm"):
            file.unlink(missing_ok=True)
        self._restart_capture()

    def _build_command(self) -> list[str]:
        pattern = str(self.chunks_dir / "chunk_%05d.webm")
        return build_mixer_command(
            self.ffmpeg,
            self.mic_device,
            self.cable_device,
            pattern,
            mic_volume=self.mic_volume,
            cable_volume=self.cable_volume,
            master_volume=self.master_volume,
            mic_enabled=self.mic_enabled,
            segment_seconds=self.segment_seconds,
            bitrate=self.bitrate,
        )

    def _restart_capture(self) -> None:
        with self._lock:
            old = self.process
            if old and old.poll() is None:
                old.terminate()
                try:
                    old.wait(timeout=2)
                except subprocess.TimeoutExpired:
                    old.kill()

            command = self._build_command()
            self.process = subprocess.Popen(
                command,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.PIPE,
                text=True,
                encoding="utf-8",
                errors="replace",
                **_hidden_subprocess_kwargs(),
            )

    def stop(self) -> None:
        with self._lock:
            if self._restart_timer:
                self._restart_timer.cancel()
                self._restart_timer = None
            if self.process and self.process.poll() is None:
                self.process.terminate()
                try:
                    self.process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    self.process.kill()
            self.process = None

    def stderr_tail(self) -> str:
        if not self.process or not self.process.stderr:
            return ""
        try:
            return self.process.stderr.read() or ""
        except Exception:
            return ""


def build_dshow_pcm_command(ffmpeg: str, device_name: str) -> list[str]:
    return [
        ffmpeg,
        "-hide_banner",
        "-loglevel",
        "error",
        "-f",
        "dshow",
        "-rtbufsize",
        "50M",
        "-i",
        f"audio={device_name}",
        "-ac",
        "2",
        "-ar",
        "48000",
        "-f",
        "s16le",
        "pipe:1",
    ]


def build_encoder_from_pipe_command(
    ffmpeg: str,
    output_pattern: str,
    segment_seconds: int = 1,
    bitrate: str = "96000",
) -> list[str]:
    return [
        ffmpeg,
        "-hide_banner",
        "-loglevel",
        "error",
        "-f",
        "s16le",
        "-ar",
        "48000",
        "-ac",
        "2",
        "-i",
        "pipe:0",
        "-c:a",
        "libopus",
        "-b:a",
        bitrate,
        "-f",
        "segment",
        "-segment_time",
        str(segment_seconds),
        "-reset_timestamps",
        "1",
        output_pattern,
    ]


def _read_exact(stream, nbytes: int) -> bytes | None:
    data = bytearray()
    while len(data) < nbytes:
        chunk = stream.read(nbytes - len(data))
        if not chunk:
            return None
        data.extend(chunk)
    return bytes(data)


def _mix_pcm_block(
    primary: bytes,
    secondary: bytes | None,
    primary_gain: float,
    secondary_gain: float,
) -> bytes:
    samples = array.array("h")
    samples.frombytes(primary)
    count = len(samples)

    if secondary and secondary_gain > 0:
        other = array.array("h")
        other.frombytes(secondary[: count * 2])
        if len(other) < count:
            other.extend([0] * (count - len(other)))
        for i in range(count):
            mixed = int(samples[i] * primary_gain + other[i] * secondary_gain)
            samples[i] = max(-32768, min(32767, mixed))
    elif primary_gain != 1.0:
        for i in range(count):
            samples[i] = max(-32768, min(32767, int(samples[i] * primary_gain)))

    return samples.tobytes()


class PythonMixerCapture:
    """Mezcla micrófono + VB-Cable en Python; el volumen cambia al instante sin reiniciar FFmpeg."""

    FRAME_SAMPLES = 1024
    SAMPLE_RATE = 48000
    CHANNELS = 2

    def __init__(
        self,
        mic_device: str,
        cable_device: str,
        chunks_dir: Path,
        segment_seconds: int = 1,
        bitrate: str = "96000",
        ffmpeg_path: str = "",
        mic_volume: float = 1.0,
        cable_volume: float = 0.85,
        master_volume: float = 1.0,
        mic_enabled: bool = True,
    ):
        self.mic_device = mic_device
        self.cable_device = cable_device
        self.chunks_dir = chunks_dir
        self.segment_seconds = segment_seconds
        self.bitrate = bitrate
        self.ffmpeg = find_ffmpeg(ffmpeg_path)
        self.mic_volume = mic_volume
        self.cable_volume = cable_volume
        self.master_volume = master_volume
        self.mic_enabled = mic_enabled
        self.process: subprocess.Popen | None = None
        self._mic_proc: subprocess.Popen | None = None
        self._cable_proc: subprocess.Popen | None = None
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self._mix_thread: threading.Thread | None = None
        self._stderr_lines: list[str] = []

    @property
    def _frame_bytes(self) -> int:
        return self.FRAME_SAMPLES * self.CHANNELS * 2

    def set_levels(
        self,
        mic_volume: float,
        cable_volume: float,
        master_volume: float,
        mic_enabled: bool,
        *,
        restart_delay: float = 0.0,
    ) -> None:
        del restart_delay
        with self._lock:
            self.mic_volume = mic_volume
            self.cable_volume = cable_volume
            self.master_volume = master_volume
            self.mic_enabled = mic_enabled

    def _effective_gains(self) -> tuple[float, float]:
        with self._lock:
            master = self.master_volume
            mic = self.mic_volume * master if self.mic_enabled and self.mic_device else 0.0
            cable = self.cable_volume * master if self.cable_device else 0.0
        return mic, cable

    def _spawn_source(self, device_name: str) -> subprocess.Popen:
        command = build_dshow_pcm_command(self.ffmpeg, device_name)
        return subprocess.Popen(
            command,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            **_hidden_subprocess_kwargs(),
        )

    def _spawn_encoder(self, pattern: str) -> subprocess.Popen:
        command = build_encoder_from_pipe_command(
            self.ffmpeg,
            pattern,
            self.segment_seconds,
            self.bitrate,
        )
        return subprocess.Popen(
            command,
            stdin=subprocess.PIPE,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
            **_hidden_subprocess_kwargs(),
        )

    def _log_stderr(self, proc: subprocess.Popen | None, label: str) -> None:
        if not proc or not proc.stderr:
            return
        try:
            for raw in proc.stderr:
                line = raw.decode("utf-8", errors="replace").rstrip()
                if line:
                    self._stderr_lines.append(f"[{label}] {line}")
                    if len(self._stderr_lines) > 80:
                        del self._stderr_lines[:40]
        except Exception:
            pass

    def start(self) -> None:
        use_mic = bool(self.mic_device) and self.mic_enabled
        use_cable = bool(self.cable_device)
        if not use_mic and not use_cable:
            raise ValueError("Selecciona micrófono y/o música (VB-Cable)")

        self.chunks_dir.mkdir(parents=True, exist_ok=True)
        for file in self.chunks_dir.glob("*.webm"):
            file.unlink(missing_ok=True)

        pattern = str(self.chunks_dir / "chunk_%05d.webm")
        self._stop.clear()
        self._stderr_lines.clear()

        if use_mic:
            self._mic_proc = self._spawn_source(self.mic_device)
            threading.Thread(
                target=self._log_stderr,
                args=(self._mic_proc, "mic"),
                daemon=True,
            ).start()
        if use_cable:
            self._cable_proc = self._spawn_source(self.cable_device)
            threading.Thread(
                target=self._log_stderr,
                args=(self._cable_proc, "cable"),
                daemon=True,
            ).start()

        self.process = self._spawn_encoder(pattern)
        threading.Thread(
            target=self._log_stderr,
            args=(self.process, "encoder"),
            daemon=True,
        ).start()

        self._mix_thread = threading.Thread(target=self._mix_loop, daemon=True)
        self._mix_thread.start()

    def _mix_loop(self) -> None:
        frame_bytes = self._frame_bytes
        encoder = self.process
        if not encoder or not encoder.stdin:
            return

        try:
            while not self._stop.is_set():
                mic_gain, cable_gain = self._effective_gains()

                if self._cable_proc and self._cable_proc.stdout:
                    cable_data = _read_exact(self._cable_proc.stdout, frame_bytes)
                    if cable_data is None:
                        break

                    if self._mic_proc and self._mic_proc.stdout:
                        if mic_gain > 0:
                            mic_data = _read_exact(self._mic_proc.stdout, frame_bytes)
                            if mic_data is None:
                                break
                            mixed = _mix_pcm_block(cable_data, mic_data, cable_gain, mic_gain)
                        else:
                            _read_exact(self._mic_proc.stdout, frame_bytes)
                            mixed = _mix_pcm_block(cable_data, None, cable_gain, 0.0)
                    else:
                        mixed = _mix_pcm_block(cable_data, None, cable_gain, 0.0)
                elif self._mic_proc and self._mic_proc.stdout:
                    mic_data = _read_exact(self._mic_proc.stdout, frame_bytes)
                    if mic_data is None:
                        break
                    mixed = _mix_pcm_block(mic_data, None, mic_gain, 0.0)
                else:
                    break

                encoder.stdin.write(mixed)
        except Exception as exc:
            self._stderr_lines.append(f"[mixer] {exc}")
        finally:
            try:
                if encoder.stdin:
                    encoder.stdin.close()
            except Exception:
                pass

    def stop(self) -> None:
        self._stop.set()
        for proc in (self._mic_proc, self._cable_proc):
            if proc and proc.stdout:
                try:
                    proc.stdout.close()
                except Exception:
                    pass
        if self.process and self.process.stdin:
            try:
                self.process.stdin.close()
            except Exception:
                pass
        for proc in (self._mic_proc, self._cable_proc, self.process):
            if proc and proc.poll() is None:
                proc.terminate()
                try:
                    proc.wait(timeout=2)
                except subprocess.TimeoutExpired:
                    proc.kill()
        if self._mix_thread and self._mix_thread.is_alive():
            self._mix_thread.join(timeout=1)
        self._mic_proc = None
        self._cable_proc = None
        self.process = None
        self._mix_thread = None

    def stderr_tail(self) -> str:
        return "\n".join(self._stderr_lines[-20:])
