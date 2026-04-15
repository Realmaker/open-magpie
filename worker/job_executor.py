"""Job execution logic - runs claude -p with the job's prompt."""

import logging
import os
import subprocess
import time

from config import CLAUDE_PATH, WORK_DIR

logger = logging.getLogger("worker")

# Output limits to protect database
MAX_STDOUT = 50 * 1024  # 50 KB
MAX_STDERR = 10 * 1024  # 10 KB


def execute_job(job: dict) -> dict:
    """
    Execute a worker job and return the result.

    Returns dict with: status, output, error_output, exit_code, duration_seconds, result_summary
    """
    job_type = job.get("type", "code_change")
    prompt = job.get("prompt", "")
    project_path = job.get("project_path") or job.get("working_directory")
    project = job.get("project") or {}
    project_config = (project.get("worker_config") if isinstance(project, dict) else None) or {}

    logger.info(f"Executing job {job['id']}: {job['title']} (type={job_type})")

    if job_type == "prepared":
        return _execute_prepared(job, prompt, project_path)
    elif job_type == "new_project":
        return _execute_new_project(job, prompt, project_path)
    else:
        return _execute_code_change(job, prompt, project_path, project_config)


def _execute_code_change(job: dict, prompt: str, project_path: str | None, config: dict) -> dict:
    """Execute claude -p in an existing project directory."""
    work_dir = project_path or WORK_DIR

    if not os.path.isdir(work_dir):
        return {
            "status": "failed",
            "output": None,
            "error_output": f"Working directory does not exist: {work_dir}",
            "exit_code": 1,
            "duration_seconds": 0,
            "result_summary": f"Directory not found: {work_dir}",
        }

    claude_flags = config.get("claude_flags", "")
    pre_commands = config.get("pre_commands", [])
    post_commands = config.get("post_commands", [])

    # Run pre-commands
    for cmd in pre_commands:
        logger.info(f"Running pre-command: {cmd}")
        subprocess.run(cmd, shell=True, cwd=work_dir, capture_output=True, timeout=60)

    # Build claude command
    cmd = [CLAUDE_PATH, "-p", prompt]
    if claude_flags:
        cmd.extend(claude_flags.split())

    result = _run_command(cmd, work_dir)

    # Run post-commands
    for cmd_str in post_commands:
        logger.info(f"Running post-command: {cmd_str}")
        subprocess.run(cmd_str, shell=True, cwd=work_dir, capture_output=True, timeout=60)

    return result


def _execute_new_project(job: dict, prompt: str, project_path: str | None) -> dict:
    """Create a new project directory and run claude -p in it."""
    if not project_path:
        project_path = os.path.join(WORK_DIR, f"project-{job['id']}")

    # Create directory if it doesn't exist
    os.makedirs(project_path, exist_ok=True)
    logger.info(f"Created project directory: {project_path}")

    cmd = [CLAUDE_PATH, "-p", prompt]
    return _run_command(cmd, project_path)


def _execute_prepared(job: dict, prompt: str, project_path: str | None) -> dict:
    """Save prompt as .md file without executing claude."""
    work_dir = project_path or WORK_DIR
    os.makedirs(work_dir, exist_ok=True)

    filename = f"worker-job-{job['id']}.md"
    filepath = os.path.join(work_dir, filename)

    with open(filepath, "w", encoding="utf-8") as f:
        f.write(f"# Worker Job: {job['title']}\n\n")
        f.write(prompt)

    logger.info(f"Prepared prompt saved to: {filepath}")

    return {
        "status": "done",
        "output": f"Prompt saved to: {filepath}",
        "error_output": None,
        "exit_code": 0,
        "duration_seconds": 0,
        "result_summary": f"Prompt prepared and saved as {filename}. Ready for manual execution.",
    }


def _run_command(cmd: list, cwd: str) -> dict:
    """Run a command and capture output with limits."""
    start_time = time.time()

    try:
        logger.info(f"Running: {' '.join(cmd[:3])}... in {cwd}")
        proc = subprocess.run(
            cmd,
            cwd=cwd,
            capture_output=True,
            text=True,
            timeout=1800,  # 30 minute timeout
        )

        duration = int(time.time() - start_time)
        stdout = proc.stdout[:MAX_STDOUT] if proc.stdout else None
        stderr = proc.stderr[:MAX_STDERR] if proc.stderr else None

        status = "done" if proc.returncode == 0 else "failed"

        # Generate summary from output
        summary = None
        if stdout:
            lines = stdout.strip().split("\n")
            if len(lines) > 5:
                summary = "\n".join(lines[-5:])
            else:
                summary = stdout.strip()[:500]

        logger.info(f"Command finished: exit_code={proc.returncode}, duration={duration}s")

        return {
            "status": status,
            "output": stdout,
            "error_output": stderr,
            "exit_code": proc.returncode,
            "duration_seconds": duration,
            "result_summary": summary,
        }

    except subprocess.TimeoutExpired:
        duration = int(time.time() - start_time)
        logger.error(f"Command timed out after {duration}s")
        return {
            "status": "failed",
            "output": None,
            "error_output": "Process timed out after 30 minutes",
            "exit_code": -1,
            "duration_seconds": duration,
            "result_summary": "Job timed out",
        }

    except FileNotFoundError:
        logger.error(f"Command not found: {cmd[0]}")
        return {
            "status": "failed",
            "output": None,
            "error_output": f"Command not found: {cmd[0]}. Is Claude CLI installed and in PATH?",
            "exit_code": 127,
            "duration_seconds": 0,
            "result_summary": f"Command not found: {cmd[0]}",
        }

    except Exception as e:
        duration = int(time.time() - start_time)
        logger.error(f"Command execution error: {e}")
        return {
            "status": "failed",
            "output": None,
            "error_output": str(e),
            "exit_code": -1,
            "duration_seconds": duration,
            "result_summary": f"Execution error: {e}",
        }
