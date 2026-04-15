"""Worker configuration - loads from .env file."""

import os
import platform
import uuid

from dotenv import load_dotenv

# Load .env from worker directory
load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))


def get_machine_id() -> str:
    """Generate a stable machine identifier."""
    node = uuid.getnode()
    hostname = platform.node()
    return f"{hostname}-{node:012x}"


# API Configuration
API_URL = os.getenv("WORKER_API_URL", "http://localhost:8000/api/v1")
API_TOKEN = os.getenv("WORKER_API_TOKEN", "")

# Worker Identity
WORKER_NAME = os.getenv("WORKER_NAME", platform.node())
MACHINE_ID = get_machine_id()

# Paths
WORK_DIR = os.getenv("WORKER_WORK_DIR", os.path.expanduser("~"))
CLAUDE_PATH = os.getenv("WORKER_CLAUDE_PATH", "claude")

# Timing
POLL_INTERVAL = int(os.getenv("WORKER_POLL_INTERVAL", "10"))
HEARTBEAT_INTERVAL = int(os.getenv("WORKER_HEARTBEAT_INTERVAL", "30"))
MAX_PARALLEL = int(os.getenv("WORKER_MAX_PARALLEL", "2"))

# Logging
LOG_FILE = os.getenv("WORKER_LOG_FILE", "worker.log")

# System Info
OS_INFO = f"{platform.system()} {platform.release()} ({platform.machine()})"
VERSION = "1.0.0"
