#!/usr/bin/env python3
"""Recover post-stop staging PHP/TLS lock state, never production.

When the original staging launcher leaked flock FD 9 to child processes,
port shutdown may complete before inherited file descriptors close.
This deliberately does not unlink or replace the lock inode.
"""
from __future__ import annotations

import fcntl
import os
from pathlib import Path
import signal
import socket
import stat
import subprocess
import sys
import time

LAB = Path("/var/lib/samithai-editorial-staging-20261112")
ORIGINAL = LAB / "releases/candidate"
STAGED = LAB / "releases/pr6-faf8bd2b4335"
HTTPS_LOCK = Path("/run/lock/samithai-editorial-staging-https.lock")
PID_FILES = (LAB / "server.pid", LAB / "tls-proxy.pid")
OLD_SHA = "54c92ba0817b31673e81972c1d7beebe2bf9f17e"
NEW_SHA = "faf8bd2b4335f47715c41e29dff25c2f5e75ca31"


class Blocked(RuntimeError):
    pass


def check(condition: bool, reason: str) -> None:
    if not condition:
        raise Blocked(reason)


def file_identity(path: Path) -> os.stat_result:
    st = path.lstat()
    check(stat.S_ISREG(st.st_mode) and st.st_uid == 0,
          "UNSAFE_ROOT_OWNED_REGULAR_FILE:" + path.name)
    return st


def short_git_sha(path: Path) -> str:
    return subprocess.check_output(
        ["git", "-C", str(path), "rev-parse", "HEAD"],
        text=True, stderr=subprocess.DEVNULL, timeout=5,
    ).strip()


def read_pid(path: Path) -> int:
    file_identity(path)
    check(path.stat().st_size < 32, "UNEXPECTED_PIDFILE_LENGTH:" + path.name)
    value = path.read_text().strip()
    check(value.isascii() and value.isdecimal() and not value.startswith("0"),
          "INVALID_PRIVATE_STAGING_PID:" + path.name)
    pid = int(value)
    check(pid > 1, "INVALID_PID:" + path.name)
    return pid


def ports_are_free() -> bool:
    for port in (8331, 8843):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            try:
                sock.bind(("127.0.0.1", port))
            except OSError:
                return False
    return True


def get_args(pid: int) -> str:
    try:
        return Path(f"/proc/{pid}/cmdline").read_bytes().replace(b"\x00", b" ").decode(
            "utf-8", "replace"
        ).strip()[:600]
    except (OSError, PermissionError):
        return ""


def get_state(pid: int) -> str:
    try:
        # /proc/<pid>/stat uses a parenthesized command field; the state follows it.
        return Path(f"/proc/{pid}/stat").read_text().rsplit(") ", 1)[1][0]
    except (OSError, IndexError, PermissionError):
        return ""


def lock_holders() -> list[tuple[int, int, str]]:
    lock_stat = file_identity(HTTPS_LOCK)
    holders = []
    # Only root-owned, known inode. No process is signaled by this inspection.
    for entry in os.scandir("/proc"):
        if not entry.name.isdecimal():
            continue
        pid = int(entry.name)
        if pid == os.getpid():
            continue
        directory = f"/proc/{pid}/fd"
        try:
            descriptors = os.listdir(directory)
        except (OSError, PermissionError):
            continue
        for descriptor in descriptors:
            try:
                item = os.stat(f"{directory}/{descriptor}")
                if item.st_dev != lock_stat.st_dev or item.st_ino != lock_stat.st_ino:
                    continue
                group = os.getpgid(pid)
            except (OSError, PermissionError, ProcessLookupError):
                continue
            holders.append((pid, group, get_args(pid)))
            break
    return holders


def try_lock() -> bool:
    # Never truncate/remove a lock file. O_NOFOLLOW prevents symlink substitution.
    fd = os.open(HTTPS_LOCK, os.O_RDWR | os.O_CLOEXEC | os.O_NOFOLLOW)
    try:
        try:
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return False
        fcntl.flock(fd, fcntl.LOCK_UN)
        return True
    finally:
        os.close(fd)


def approved_holder(pid: int, group: int, args: str, http_pid: int,
                    tls_pid: int) -> bool:
    if group not in (http_pid, tls_pid):
        return False
    if group == http_pid:
        # Matches only the known launcher and its PHP server descendants.
        return "127.0.0.1:8331" in args and str(LAB / "router.php") in args
    return ("python3" in args and str(LAB / "tls-proxy.py") in args)


def report_holders(holders: list[tuple[int, int, str]], http_pid: int,
                   tls_pid: int) -> list[int]:
    approved = []
    for pid, pgid, args in holders:
        accepted = approved_holder(pid, pgid, args, http_pid, tls_pid)
        print(f"LOCK_FD_HOLDER_PID={pid} PGID={pgid} "
              f"VERIFIED_STAGING_CHILD={'YES' if accepted else 'NO'}")
        if not accepted:
            raise Blocked("LOCK_FD_HELD_BY_UNRELATED_OR_UNVERIFIED_PROCESS")
        approved.append(pid)
    return approved


def still_running_owner(pid: int, role: str) -> bool:
    state = get_state(pid)
    if not state or state == "Z":
        return False
    args = get_args(pid)
    if role == "http":
        check("127.0.0.1:8331" in args and str(LAB / "router.php") in args,
              "RECORDED_HTTP_PID_REUSED_BY_UNRELATED_PROCESS")
    else:
        check("python3" in args and str(LAB / "tls-proxy.py") in args,
              "RECORDED_TLS_PID_REUSED_BY_UNRELATED_PROCESS")
    return True


def main() -> None:
    check(len(sys.argv) == 2 and sys.argv[1] in ("--inspect", "--complete"),
          "USAGE_--inspect_OR_--complete")
    check(os.geteuid() == 0, "ROOT_REQUIRED_FOR_PID_IDENTITY")
    check(LAB.is_dir() and not LAB.is_symlink(), "STAGING_LAB_INVALID")
    check((LAB / "current").is_symlink() and
          (LAB / "current").resolve(strict=True) == ORIGINAL,
          "ORIGINAL_STAGING_POINTER_MUST_REMAIN_ACTIVE")
    check(ORIGINAL.is_dir() and STAGED.is_dir() and not STAGED.is_symlink(),
          "STAGING_RELEASES_MISSING")
    check(short_git_sha(ORIGINAL) == OLD_SHA and
          short_git_sha(STAGED) == NEW_SHA, "STAGING_SOURCE_SHA_MISMATCH")
    check((LAB / "shared/staging.sqlite").is_file() and
          (STAGED / "staging-release.json").is_file(),
          "STAGING_DATA_OR_MANIFEST_MISSING")
    check(not (LAB / "proxy/nginx.pid").exists() and
          not (LAB / "proxy/nginx.pid").is_symlink(),
          "UNEXPECTED_OPENRESTY_PID_FILE")

    http_pid, tls_pid = (read_pid(p) for p in PID_FILES)
    check(http_pid != tls_pid, "DUPLICATED_PRIVATE_PIDS")
    check(ports_are_free(), "STAGING_PORTS_ARE_NOT_BOTH_FREE")
    file_identity(HTTPS_LOCK)

    print("POST_STOP_STAGING_POINTER=ORIGINAL")
    print("PRIVATE_HTTP_HTTPS_PORTS=FREE")
    released = try_lock()
    print("HTTPS_FLOCK=" + ("AVAILABLE" if released else "HELD"))

    if not released:
        holders = lock_holders()
        check(bool(holders), "LOCK_HELD_BUT_NO_HOLDERS_IDENTIFIED")
        approved = report_holders(holders, http_pid, tls_pid)
        if sys.argv[1] == "--complete":
            # The ports are already closed and these specific PIDs still belong
            # to the known original private PHP/TLS process groups.
            for pid in sorted(set(approved)):
                if get_state(pid) not in ("", "Z"):
                    check(approved_holder(pid, os.getpgid(pid), get_args(pid),
                                          http_pid, tls_pid),
                          "STAGING_LOCK_HOLDER_CHANGED_BEFORE_TERM")
                    os.kill(pid, signal.SIGTERM)
                    print(f"PRIVATE_STAGING_DESCENDANT_TERM_REQUESTED_PID={pid}")
            for _ in range(30):
                if try_lock():
                    break
                time.sleep(0.2)
            released = try_lock()
            check(released, "HTTPS_FLOCK_STILL_HELD_AFTER_VERIFIED_CHILD_SHUTDOWN")
            print("HTTPS_FLOCK=RELEASED_AFTER_VERIFIED_CHILD_SHUTDOWN")

    if sys.argv[1] == "--inspect":
        print("POST_STOP_INSPECTION=COMPLETE_NO_MUTATIONS")
        return

    check(released, "HTTPS_FLOCK_NOT_RELEASED")
    check(ports_are_free(), "STAGING_PORT_REOPENED_AFTER_STOP")
    check(not still_running_owner(http_pid, "http"), "ORIGINAL_PRIVATE_HTTP_STILL_RUNNING")
    check(not still_running_owner(tls_pid, "tls"), "ORIGINAL_PRIVATE_TLS_STILL_RUNNING")

    # Remove only the two root-owned, recorded staging PID files. Verify their
    # contents have not changed since the initial inspection.
    check(read_pid(PID_FILES[0]) == http_pid and read_pid(PID_FILES[1]) == tls_pid,
          "RECORDED_STAGING_PIDFILES_CHANGED")
    for path in PID_FILES:
        path.unlink()
    print("STAGING_STALE_PID_FILES=RETIRED")
    print("STAGING_HTTPS_FLOCK=AVAILABLE")
    print("STAGING_ACTIVE_POINTER=ORIGINAL_UNCHANGED")
    print("STAGING_DB_AND_IMAGES=UNCHANGED")
    print("PRODUCTION_NGINX_DATABASE_CRON=UNCHANGED")
    print("READY_FOR_EXISTING_OFFLINE_ACTIVATE_SCRIPT=YES")


if __name__ == "__main__":
    try:
        main()
    except (Blocked, OSError, subprocess.SubprocessError, ValueError) as error:
        if isinstance(error, Blocked):
            detail = str(error)
        else:
            detail = error.__class__.__name__
        print("POST_STOP_RECOVERY_BLOCKED=" + detail, file=sys.stderr)
        print("STAGING_DATA_AND_PRODUCTION=PRESERVED", file=sys.stderr)
        sys.exit(3)
