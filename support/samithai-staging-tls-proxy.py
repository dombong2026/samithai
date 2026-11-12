#!/usr/bin/env python3
"""Loopback HTTPS gateway for disposable Samith AI staging only.

Binds 127.0.0.1:8843; forwards to 127.0.0.1:8331.
Not a public or production reverse proxy.
"""
import http.client
import http.server
import os
import ssl
import sys
from urllib.parse import urlsplit

LAB = "/var/lib/samithai-editorial-staging-20261112"
LISTEN_HOST = "127.0.0.1"
LISTEN_PORT = 8843
BACKEND_HOST = "127.0.0.1"
BACKEND_PORT = 8331
MAX_BODY = 16 * 1024 * 1024
MAX_RESPONSE = 20 * 1024 * 1024
HOP = {
    "connection", "keep-alive", "proxy-authenticate", "proxy-authorization",
    "te", "trailer", "transfer-encoding", "upgrade", "content-length",
}

class PreviewProxy(http.server.BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"
    server_version = "SamithAI-PrivatePreview"
    sys_version = ""

    def _reply(self, status, message):
        data = message.encode("utf-8")
        self.send_response_only(status)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("Connection", "close")
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(data)
        self.close_connection = True

    def _handle(self):
        if self.client_address[0] != "127.0.0.1":
            return self._reply(403, "Loopback clients only")
        if not self.path.startswith("/") or self.path.startswith("//"):
            return self._reply(400, "Only relative URL paths are permitted")
        parsed = urlsplit(self.path)
        if parsed.scheme or parsed.netloc or not parsed.path.startswith("/"):
            return self._reply(400, "Invalid request target")
        if "transfer-encoding" in self.headers:
            return self._reply(400, "Streaming request bodies are unsupported")
        try:
            length = int(self.headers.get("Content-Length", "0"))
            if length < 0 or length > MAX_BODY:
                return self._reply(413, "Staging request size limit exceeded")
        except ValueError:
            return self._reply(400, "Invalid content length")
        body = self.rfile.read(length) if length else None
        headers = {}
        for key, value in self.headers.items():
            if key.lower() not in HOP and key.lower() not in {
                "host", "x-forwarded-proto", "x-forwarded-host", "x-forwarded-for"
            }:
                headers[key] = value
        headers["Host"] = f"127.0.0.1:{LISTEN_PORT}"
        headers["X-Forwarded-Proto"] = "https"
        headers["X-Forwarded-Host"] = f"127.0.0.1:{LISTEN_PORT}"
        headers["X-Forwarded-For"] = "127.0.0.1"

        conn = http.client.HTTPConnection(BACKEND_HOST, BACKEND_PORT, timeout=25)
        try:
            conn.request(self.command, self.path, body=body, headers=headers)
            response = conn.getresponse()
            data = response.read(MAX_RESPONSE + 1)
            if len(data) > MAX_RESPONSE:
                return self._reply(502, "Oversized preview response")
            self.send_response_only(response.status, response.reason)
            for name, value in response.getheaders():
                if name.lower() not in HOP:
                    self.send_header(name, value)
            self.send_header("Content-Length", str(len(data)))
            self.send_header("Connection", "close")
            self.send_header("X-Content-Type-Options", "nosniff")
            self.end_headers()
            if self.command != "HEAD":
                self.wfile.write(data)
            self.close_connection = True
        except (OSError, http.client.HTTPException) as exc:
            self.log_error("Private staging backend error: %s", type(exc).__name__)
            try:
                self._reply(502, "Isolated staging backend unavailable")
            except OSError:
                pass
        finally:
            conn.close()

    do_GET = _handle
    do_HEAD = _handle
    do_POST = _handle
    do_PUT = _handle
    do_PATCH = _handle
    do_DELETE = _handle
    do_OPTIONS = _handle

    def log_message(self, fmt, *args):
        # Never record URLs, query values, CSRF tokens, or request bodies.
        sys.stderr.write("STAGING_TLS_REQUEST method=%s\n" % self.command)


if __name__ == "__main__":
    if os.geteuid() != 0:
        raise SystemExit("Root required for isolated TLS key")
    if not os.path.isdir(LAB) or os.path.islink(LAB):
        raise SystemExit("Unexpected staging directory")
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.minimum_version = ssl.TLSVersion.TLSv1_2
    context.load_cert_chain(f"{LAB}/tls/cert.pem", f"{LAB}/tls/key.pem")
    server = http.server.ThreadingHTTPServer((LISTEN_HOST, LISTEN_PORT), PreviewProxy)
    server.daemon_threads = True
    server.socket = context.wrap_socket(server.socket, server_side=True)
    os.umask(0o077)
    print(f"PRIVATE_STAGING_TLS=LISTENING_127.0.0.1:{LISTEN_PORT}", flush=True)
    server.serve_forever(poll_interval=0.2)
