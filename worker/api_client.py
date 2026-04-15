"""REST API client for communicating with Claude Code Hub."""

import logging
from typing import Any

import requests

from config import API_TOKEN, API_URL

logger = logging.getLogger("worker")


class ApiClient:
    """Client for the Claude Code Hub Worker API."""

    def __init__(self):
        self.base_url = API_URL.rstrip("/")
        self.session = requests.Session()
        self.session.headers.update(
            {
                "Authorization": f"Bearer {API_TOKEN}",
                "Content-Type": "application/json",
                "Accept": "application/json",
            }
        )
        self.session.timeout = 30

    def heartbeat(self, data: dict) -> dict | None:
        """Send heartbeat to register/update worker."""
        try:
            resp = self.session.post(f"{self.base_url}/worker/heartbeat", json=data)
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as e:
            logger.error(f"Heartbeat failed: {e}")
            return None

    def get_pending_jobs(self) -> list[dict]:
        """Fetch pending jobs that can be claimed."""
        try:
            resp = self.session.get(f"{self.base_url}/worker/jobs/pending")
            resp.raise_for_status()
            result = resp.json()
            return result.get("data", [])
        except requests.RequestException as e:
            logger.error(f"Failed to fetch pending jobs: {e}")
            return []

    def claim_job(self, job_id: int, worker_id: int, machine_id: str) -> dict | None:
        """Claim a job atomically."""
        try:
            resp = self.session.post(
                f"{self.base_url}/worker/jobs/{job_id}/claim",
                json={"worker_id": worker_id, "machine_id": machine_id},
            )
            if resp.status_code == 409:
                logger.info(f"Job {job_id} already claimed by another worker")
                return None
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as e:
            logger.error(f"Failed to claim job {job_id}: {e}")
            return None

    def start_job(self, job_id: int) -> dict | None:
        """Mark job as running."""
        try:
            resp = self.session.post(f"{self.base_url}/worker/jobs/{job_id}/start")
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as e:
            logger.error(f"Failed to start job {job_id}: {e}")
            return None

    def complete_job(self, job_id: int, data: dict) -> dict | None:
        """Complete a job with results."""
        try:
            resp = self.session.post(
                f"{self.base_url}/worker/jobs/{job_id}/complete",
                json=data,
            )
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as e:
            logger.error(f"Failed to complete job {job_id}: {e}")
            return None
