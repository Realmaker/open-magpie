#!/usr/bin/env python3
"""
Claude Code Hub - Worker

Main entry point for the worker process. Polls the portal for pending jobs,
claims them, executes via `claude -p`, and reports results back.

Usage:
    python worker.py
"""

import signal
import sys
import threading
import time

from api_client import ApiClient
from config import API_TOKEN, API_URL, MACHINE_ID, MAX_PARALLEL, POLL_INTERVAL, WORKER_NAME
from heartbeat import HeartbeatThread, JobTracker
from job_executor import execute_job
from logger_setup import setup_logger

logger = setup_logger()
shutdown_event = threading.Event()


def handle_shutdown(signum, frame):
    """Handle graceful shutdown on SIGINT/SIGTERM."""
    logger.info("Shutdown signal received, finishing running jobs...")
    shutdown_event.set()


def run_job(api: ApiClient, job: dict, worker_id: int, tracker: JobTracker):
    """Execute a single job in a thread."""
    job_id = job["id"]
    try:
        # Mark as running
        api.start_job(job_id)
        logger.info(f"Job {job_id} started: {job['title']}")

        # Execute
        result = execute_job(job)

        # Report result
        api.complete_job(job_id, result)
        logger.info(f"Job {job_id} completed: status={result['status']}, exit_code={result.get('exit_code')}")

    except Exception as e:
        logger.error(f"Job {job_id} execution error: {e}")
        api.complete_job(
            job_id,
            {
                "status": "failed",
                "error_output": str(e),
                "exit_code": -1,
                "duration_seconds": 0,
                "result_summary": f"Worker error: {e}",
            },
        )
    finally:
        tracker.remove(job_id)


def main():
    """Main worker loop."""
    # Validate configuration
    if not API_TOKEN:
        logger.error("WORKER_API_TOKEN is not set. Please configure .env file.")
        sys.exit(1)

    logger.info("=" * 60)
    logger.info(f"Claude Code Hub Worker v1.0.0")
    logger.info(f"Name: {WORKER_NAME}")
    logger.info(f"Machine ID: {MACHINE_ID}")
    logger.info(f"API URL: {API_URL}")
    logger.info(f"Max parallel jobs: {MAX_PARALLEL}")
    logger.info(f"Poll interval: {POLL_INTERVAL}s")
    logger.info("=" * 60)

    # Setup
    signal.signal(signal.SIGINT, handle_shutdown)
    signal.signal(signal.SIGTERM, handle_shutdown)

    api = ApiClient()
    tracker = JobTracker()

    # Start heartbeat thread
    heartbeat = HeartbeatThread(api, tracker)
    heartbeat.start()

    # Wait for initial heartbeat to get worker_id
    time.sleep(2)

    if not heartbeat.worker_id:
        logger.error("Failed to register with portal. Check API URL and token.")
        sys.exit(1)

    logger.info(f"Registered with portal (worker_id={heartbeat.worker_id})")
    logger.info("Polling for jobs...")

    # Main polling loop
    while not shutdown_event.is_set():
        try:
            # Check capacity
            running = tracker.count()
            if running >= MAX_PARALLEL:
                shutdown_event.wait(POLL_INTERVAL)
                continue

            # Fetch pending jobs
            pending = api.get_pending_jobs()

            if pending:
                slots = MAX_PARALLEL - running
                for job in pending[:slots]:
                    # Try to claim
                    claimed = api.claim_job(
                        job["id"],
                        heartbeat.worker_id,
                        MACHINE_ID,
                    )

                    if claimed:
                        job_data = claimed.get("data", job)
                        t = threading.Thread(
                            target=run_job,
                            args=(api, job_data, heartbeat.worker_id, tracker),
                            name=f"job-{job['id']}",
                            daemon=True,
                        )
                        tracker.add(job["id"], t)
                        t.start()
                        logger.info(f"Job {job['id']} claimed and started in thread")

        except Exception as e:
            logger.error(f"Poll loop error: {e}")

        shutdown_event.wait(POLL_INTERVAL)

    # Graceful shutdown
    logger.info("Waiting for running jobs to finish...")
    heartbeat.stop()

    # Wait up to 60s for jobs
    deadline = time.time() + 60
    while tracker.count() > 0 and time.time() < deadline:
        time.sleep(1)

    remaining = tracker.count()
    if remaining > 0:
        logger.warning(f"{remaining} jobs still running at shutdown")

    logger.info("Worker stopped.")


if __name__ == "__main__":
    main()
