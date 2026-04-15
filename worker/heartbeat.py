"""Heartbeat thread - sends periodic status updates to the portal."""

import logging
import threading
import time

from api_client import ApiClient
from config import HEARTBEAT_INTERVAL, MACHINE_ID, MAX_PARALLEL, OS_INFO, VERSION, WORKER_NAME

logger = logging.getLogger("worker")


class HeartbeatThread(threading.Thread):
    """Sends periodic heartbeat to the portal."""

    def __init__(self, api: ApiClient, job_tracker: "JobTracker"):
        super().__init__(daemon=True, name="heartbeat")
        self.api = api
        self.job_tracker = job_tracker
        self.worker_id: int | None = None
        self._stop_event = threading.Event()

    def run(self):
        """Main heartbeat loop."""
        logger.info(f"Heartbeat thread started (interval={HEARTBEAT_INTERVAL}s)")

        while not self._stop_event.is_set():
            self._send_heartbeat()
            self._stop_event.wait(HEARTBEAT_INTERVAL)

    def stop(self):
        """Signal the thread to stop."""
        self._stop_event.set()

    def _send_heartbeat(self):
        """Send a single heartbeat."""
        running_jobs = self.job_tracker.get_running_job_ids()
        status = "busy" if running_jobs else "online"

        data = {
            "machine_id": MACHINE_ID,
            "name": WORKER_NAME,
            "status": status,
            "version": VERSION,
            "os_info": OS_INFO,
            "capabilities": ["code_change", "new_project", "prepared"],
            "current_jobs": running_jobs,
            "max_parallel_jobs": MAX_PARALLEL,
        }

        result = self.api.heartbeat(data)
        if result and "data" in result:
            self.worker_id = result["data"].get("id")
            logger.debug(f"Heartbeat sent (worker_id={self.worker_id}, status={status})")
        else:
            logger.warning("Heartbeat failed - portal may be unreachable")


class JobTracker:
    """Thread-safe tracker for currently running jobs."""

    def __init__(self):
        self._lock = threading.Lock()
        self._running: dict[int, threading.Thread] = {}

    def add(self, job_id: int, thread: threading.Thread):
        with self._lock:
            self._running[job_id] = thread

    def remove(self, job_id: int):
        with self._lock:
            self._running.pop(job_id, None)

    def count(self) -> int:
        with self._lock:
            # Clean up finished threads
            finished = [jid for jid, t in self._running.items() if not t.is_alive()]
            for jid in finished:
                del self._running[jid]
            return len(self._running)

    def get_running_job_ids(self) -> list[int]:
        with self._lock:
            finished = [jid for jid, t in self._running.items() if not t.is_alive()]
            for jid in finished:
                del self._running[jid]
            return list(self._running.keys())
