"""Cliente HTTP para la API broadcaster de Laravel."""

from __future__ import annotations

import json
from pathlib import Path

import requests


class BroadcasterClient:
    def __init__(self, server_url: str, token: str = "", email: str = ""):
        self.server_url = server_url.rstrip("/")
        self.token = token
        self.email = email
        self.session_id = ""
        self.session = requests.Session()
        self.session.headers.update({"Accept": "application/json"})

    def _headers(self) -> dict:
        headers = {}
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"
        return headers

    def login(self, email: str, password: str) -> dict:
        response = self.session.post(
            f"{self.server_url}/api/broadcaster/login",
            json={"email": email, "password": password},
            timeout=30,
        )
        data = response.json()
        if response.status_code != 200:
            raise RuntimeError(data.get("error", "Login fallido"))
        self.token = data["token"]
        self.email = email
        return data

    def status(self) -> dict:
        response = self.session.get(
            f"{self.server_url}/api/broadcaster/status",
            headers=self._headers(),
            timeout=15,
        )
        response.raise_for_status()
        return response.json()

    def start(self, host_name: str) -> dict:
        response = self.session.post(
            f"{self.server_url}/api/broadcaster/start",
            headers=self._headers(),
            json={"host_name": host_name},
            timeout=30,
        )
        data = response.json()
        if response.status_code != 200:
            raise RuntimeError(data.get("error", "No se pudo iniciar"))
        self.session_id = data.get("session_id", "")
        return data

    def stop(self) -> dict:
        response = self.session.post(
            f"{self.server_url}/api/broadcaster/stop",
            headers=self._headers(),
            timeout=30,
        )
        response.raise_for_status()
        self.session_id = ""
        return response.json()

    def upload_chunk(self, path: Path) -> dict:
        data = path.read_bytes()
        headers = {**self._headers(), "Content-Type": "audio/webm"}
        if self.session_id:
            headers["X-Broadcast-Session"] = self.session_id
        response = self.session.post(
            f"{self.server_url}/api/broadcaster/chunk",
            headers=headers,
            data=data,
            timeout=60,
        )
        if response.status_code == 403:
            return {"forbidden": True, "error": "Transmision no activa"}
        data = response.json()
        if response.status_code != 200:
            raise RuntimeError(data.get("error", "Error al subir chunk"))
        return data


def load_config(path: Path) -> dict:
    if path.exists():
        return json.loads(path.read_text(encoding="utf-8"))
    return {}


def save_config(path: Path, config: dict) -> None:
    path.write_text(json.dumps(config, indent=4, ensure_ascii=False), encoding="utf-8")
