"""CLI tools for the ForeverBox memory provider."""

import json
import os
import sys
from pathlib import Path


def status():
    """Print connection status."""
    hermes_home = os.environ.get("HERMES_HOME", os.path.expanduser("~/.hermes"))
    cfg_path = Path(hermes_home) / "foreverbox.json"

    if not cfg_path.exists():
        print("ForeverBox not configured. Run: hermes memory setup")
        sys.exit(1)

    cfg = json.loads(cfg_path.read_text())
    api_key = bool(os.environ.get("FOREVERBOX_API_KEY"))

    print(f"Agent:       {cfg.get('agent_slug', 'unknown')}")
    print(f"API URL:     {cfg.get('api_url', 'unknown')}")
    print(f"API Key:     {'configured' if api_key else 'MISSING'}")
    print(f"Config:      {cfg_path}")


def config():
    """Show current config."""
    hermes_home = os.environ.get("HERMES_HOME", os.path.expanduser("~/.hermes"))
    cfg_path = Path(hermes_home) / "foreverbox.json"

    if not cfg_path.exists():
        print("No config found.")
        return

    print(cfg_path.read_text())


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "status"
    if cmd == "status":
        status()
    elif cmd == "config":
        config()
    else:
        print(f"Unknown command: {cmd}")
        print("Available: status, config")
