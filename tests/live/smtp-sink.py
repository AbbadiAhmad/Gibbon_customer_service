#!/usr/bin/env python3
"""A throw-away SMTP server for the live tests: accepts every mail and appends it to a file. Nothing is delivered.

Usage: python3 tests/live/smtp-sink.py [port] [output file]      (default: 2525, /tmp/servicedesk-mail.txt)
Point Gibbon at it with tests/live/mail-on.sh.
"""
import socketserver
import sys

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 2525
OUT = sys.argv[2] if len(sys.argv) > 2 else '/tmp/servicedesk-mail.txt'


class Handler(socketserver.StreamRequestHandler):
    def send(self, line):
        self.wfile.write((line + '\r\n').encode())

    def handle(self):
        self.send('220 sink ready')
        while True:
            raw = self.rfile.readline()
            if not raw:
                return
            command = raw.decode(errors='replace').strip()
            verb = command[:4].upper()
            if verb in ('EHLO', 'HELO'):
                self.send('250 sink')
            elif verb == 'DATA':
                self.send('354 end with .')
                lines = []
                while True:
                    line = self.rfile.readline().decode(errors='replace')
                    if line in ('.\r\n', '.\n', ''):
                        break
                    lines.append(line)
                with open(OUT, 'a') as handle:
                    handle.write('=' * 30 + ' MAIL ' + '=' * 30 + '\n' + ''.join(lines) + '\n')
                self.send('250 stored')
            elif verb == 'QUIT':
                self.send('221 bye')
                return
            else:
                self.send('250 ok')


socketserver.ThreadingTCPServer.allow_reuse_address = True
with socketserver.ThreadingTCPServer(('127.0.0.1', PORT), Handler) as server:
    print('SMTP sink on 127.0.0.1:%d, writing to %s' % (PORT, OUT), flush=True)
    server.serve_forever()
