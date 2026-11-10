#!/bin/sh
# NHMP 130: restrict published data-store ports to Docker-internal, loopback, Tailscale and WireGuard sources.
# Idempotent (checks with -C before inserting). Docker DNATs published ports, so DOCKER-USER sees the container port.
# 3306 = MySQL (host ports 3306/3309/3310/3311 all map here), 6379 = Redis, 9000 = MinIO S3 API.
CHAIN=DOCKER-USER
iptables -N $CHAIN 2>/dev/null || true
add() { iptables -C $CHAIN "$@" 2>/dev/null || iptables -A $CHAIN "$@"; }
for PORT in 3306 6379 9000; do
  for SRC in 127.0.0.0/8 172.16.0.0/12 100.64.0.0/10 10.0.0.0/24; do
    add -s $SRC -p tcp --dport $PORT -j ACCEPT
  done
  add -p tcp --dport $PORT -j DROP
done
