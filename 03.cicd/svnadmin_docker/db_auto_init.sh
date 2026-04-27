#!/bin/bash
set -e

DB_PATH="/home/svnadmin/svnadmin.db"
DEFAULT_DATA_DIR="/opt/svnadmin/default-data"
LOG_FILE="/var/www/html/logs/db_init.log"

log() {
    mkdir -p "$(dirname "$LOG_FILE")"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

seed_database_if_missing() {
    if [ -s "$DB_PATH" ]; then
        return 0
    fi

    mkdir -p "$(dirname "$DB_PATH")"

    if [ -s "$DEFAULT_DATA_DIR/svnadmin.db" ]; then
        cp -f "$DEFAULT_DATA_DIR/svnadmin.db" "$DB_PATH"
        log "Initialized database from default data: $DEFAULT_DATA_DIR/svnadmin.db"
        return 0
    fi

    if [ -s "/var/www/html/templete/database/sqlite/svnadmin.db" ]; then
        cp -f "/var/www/html/templete/database/sqlite/svnadmin.db" "$DB_PATH"
        log "Initialized database from application seed."
        return 0
    fi

    log "Database file is missing and no seed database was found: $DB_PATH"
    return 1
}

ensure_passwd_file() {
    if [ ! -f "/home/svnadmin/passwd" ]; then
        printf '[users]\n' > /home/svnadmin/passwd
        log "Created passwd file."
    elif ! grep -q '^\[users\]' /home/svnadmin/passwd 2>/dev/null; then
        cp /home/svnadmin/passwd /home/svnadmin/passwd.backup.$(date '+%Y%m%d%H%M%S')
        {
            printf '[users]\n'
            cat /home/svnadmin/passwd
        } > /home/svnadmin/passwd.tmp
        mv /home/svnadmin/passwd.tmp /home/svnadmin/passwd
        log "Fixed passwd file header."
    fi
}

ensure_authz_file() {
    if [ ! -f "/home/svnadmin/authz" ] || [ ! -s "/home/svnadmin/authz" ]; then
        {
            printf '[aliases]\n\n'
            printf '[groups]\n\n'
            printf '[/]\n'
        } > /home/svnadmin/authz
        log "Created authz file."
        return 0
    fi

    cp /home/svnadmin/authz /home/svnadmin/authz.backup.$(date '+%Y%m%d%H%M%S')

    awk '
        BEGIN { aliases=0; groups=0; root=0 }
        /^\[aliases\]$/ {
            if (aliases++ == 0) { print; next }
            next
        }
        /^\[groups\]$/ {
            if (groups++ == 0) { print; next }
            next
        }
        /^\[\/\]$/ {
            root=1
            print
            next
        }
        { print }
        END {
            if (aliases == 0) {
                print ""
                print "[aliases]"
            }
            if (groups == 0) {
                print ""
                print "[groups]"
            }
            if (root == 0) {
                print ""
                print "[/]"
            }
        }
    ' /home/svnadmin/authz > /home/svnadmin/authz.tmp

    mv /home/svnadmin/authz.tmp /home/svnadmin/authz
    log "Checked authz section headers."
}

main() {
    log "=== SVNAdmin safe data initialization started ==="

    seed_database_if_missing

    chown apache:apache "$DB_PATH" 2>/dev/null || true
    chmod 664 "$DB_PATH" 2>/dev/null || true

    ensure_passwd_file
    ensure_authz_file

    chown apache:apache /home/svnadmin/passwd /home/svnadmin/authz 2>/dev/null || true
    chmod 664 /home/svnadmin/passwd /home/svnadmin/authz 2>/dev/null || true

    log "WeCom and LDAP schema upgrades are handled by 04.update/wecom-ldap-upgrade/migrate.php"
    log "=== SVNAdmin safe data initialization completed ==="
}

main "$@"
