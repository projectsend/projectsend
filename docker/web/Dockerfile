FROM nginx:1.27-alpine

# Matches the host user's uid/gid so nginx can read the bind-mounted
# storage/app/files (X-Accel-Redirect) and storage/app/public (via the
# public/storage symlink) trees without needing looser permissions.
ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN delgroup nginx 2>/dev/null || true \
    && deluser nginx 2>/dev/null || true \
    && addgroup -g "$WWWGROUP" nginx \
    && adduser -D -H -u "$WWWUSER" -G nginx nginx
