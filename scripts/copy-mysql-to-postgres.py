#!/usr/bin/env python3
"""Copy every row from a MySQL database into an already-migrated PostgreSQL one.

Written to replace pgloader, whose Lisp MySQL client refuses to connect to
MySQL 8 at all: it rejects the server's advertised caching_sha2_password
during the initial handshake, before any per-account auth switch, so giving
the account mysql_native_password does not help. PyMySQL speaks
caching_sha2_password natively.

The schema is expected to exist already, created by `artisan migrate`, so
this only moves data. Two things make that safe to do in one pass:

  * session_replication_role = replica disables foreign-key triggers for
    this session, so the tables can be copied in any order. It needs a
    superuser, which is why this connects to PostgreSQL as postgres.
  * Columns that are boolean in PostgreSQL but tinyint(1) in MySQL are
    converted explicitly. psycopg would otherwise send Python ints as
    int4 and PostgreSQL would refuse them with "column is of type boolean
    but expression is of type integer".

Nothing is written until every table has been read, and the whole copy runs
in one transaction, so a failure leaves the target exactly as it was.
"""

import os
import sys

import pymysql
import psycopg

# Tables whose contents are runtime state rather than data worth moving.
# `migrations` is deliberately NOT here: the target's own rows are cleared
# beforehand and MySQL's are copied, so the two databases agree about what
# has run.
SKIP = {"cache", "cache_locks", "jobs", "job_batches", "failed_jobs"}


def boolean_columns(pg, table):
    """Names of the columns PostgreSQL stores as boolean for `table`."""
    with pg.cursor() as cur:
        cur.execute(
            "SELECT column_name FROM information_schema.columns "
            "WHERE table_schema = current_schema() "
            "AND table_name = %s AND data_type = 'boolean'",
            (table,),
        )
        return {row[0] for row in cur.fetchall()}


def target_tables(pg):
    with pg.cursor() as cur:
        cur.execute(
            "SELECT table_name FROM information_schema.tables "
            "WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'"
        )
        return {row[0] for row in cur.fetchall()}



def reset_sequences(pg):
    """Move every sequence past the ids that were just inserted.

    Rows are copied with their MySQL ids intact, which does not advance the
    PostgreSQL sequences behind `$table->id()` columns -- they stay unused,
    and the next INSERT hands out id 1 and collides with a copied row.
    pgloader does this as part of its own run; doing the copy by hand means
    doing it here too.
    """
    fixed = []
    with pg.cursor() as cur:
        cur.execute(
            "SELECT t.relname, a.attname, s.oid::regclass::text "
            "FROM pg_class s "
            "JOIN pg_depend d ON d.objid = s.oid AND d.deptype = 'a' "
            "JOIN pg_class t ON t.oid = d.refobjid "
            "JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid "
            "WHERE s.relkind = 'S'"
        )
        for table, column, sequence in cur.fetchall():
            cur.execute('SELECT COALESCE(MAX("{}"), 0) FROM "{}"'.format(column, table))
            maxid = cur.fetchone()[0]
            # is_called=False on an empty table so the first id handed out is 1.
            cur.execute(
                "SELECT setval(%s, GREATEST(%s, 1), %s)",
                (sequence, maxid, maxid > 0),
            )
            fixed.append((table, maxid))
    return fixed


def main():
    my = pymysql.connect(
        host=os.environ["MYSQL_HOST"],
        port=int(os.environ.get("MYSQL_PORT", "3306")),
        user=os.environ["MYSQL_USER"],
        password=os.environ.get("MYSQL_PASSWORD", ""),
        database=os.environ["MYSQL_DB"],
        charset="utf8mb4",
        cursorclass=pymysql.cursors.Cursor,
    )
    pg = psycopg.connect(
        host=os.environ["PG_HOST"],
        port=int(os.environ.get("PG_PORT", "5432")),
        user=os.environ["PG_USER"],
        password=os.environ.get("PG_PASSWORD", ""),
        dbname=os.environ["PG_DB"],
        autocommit=False,
    )

    with my.cursor() as cur:
        cur.execute("SHOW TABLES")
        source_tables = sorted(row[0] for row in cur.fetchall())

    present = target_tables(pg)
    failures = []
    copied = {}

    with pg.cursor() as cur:
        cur.execute("SET session_replication_role = replica")

    for table in source_tables:
        if table in SKIP:
            print("skip   {}".format(table))
            continue
        if table not in present:
            failures.append("{}: missing in PostgreSQL".format(table))
            print("MISS   {}".format(table))
            continue

        with my.cursor() as cur:
            cur.execute("SELECT * FROM `{}`".format(table))
            columns = [d[0] for d in cur.description]
            rows = cur.fetchall()

        if not rows:
            copied[table] = 0
            print("empty  {}".format(table))
            continue

        bools = boolean_columns(pg, table)
        bool_at = [i for i, name in enumerate(columns) if name in bools]
        if bool_at:
            rows = [
                tuple(
                    (None if value is None else bool(value)) if i in bool_at else value
                    for i, value in enumerate(row)
                )
                for row in rows
            ]

        quoted = ", ".join('"{}"'.format(c) for c in columns)
        holders = ", ".join(["%s"] * len(columns))
        statement = 'INSERT INTO "{}" ({}) VALUES ({})'.format(table, quoted, holders)

        try:
            with pg.cursor() as cur:
                cur.executemany(statement, rows)
            copied[table] = len(rows)
            print("ok     {} {}".format(table, len(rows)))
        except Exception as exc:  # noqa: BLE001 - reported, then aborts below
            failures.append("{}: {}".format(table, exc))
            print("FAIL   {}: {}".format(table, exc))
            break

    if failures:
        pg.rollback()
        print("\nROLLED BACK, nothing was written:")
        for line in failures:
            print("  " + line)
        return 1

    for table, maxid in reset_sequences(pg):
        if maxid:
            print("seq    {} -> {}".format(table, maxid))

    pg.commit()

    # Read the counts back from PostgreSQL rather than trusting the inserts.
    mismatches = []
    with pg.cursor() as cur:
        for table, expected in copied.items():
            cur.execute('SELECT count(*) FROM "{}"'.format(table))
            actual = cur.fetchone()[0]
            if actual != expected:
                mismatches.append("{}: expected {}, found {}".format(table, expected, actual))

    if mismatches:
        print("\nCOUNT MISMATCH after commit:")
        for line in mismatches:
            print("  " + line)
        return 1

    print("\ncopied {} tables, {} rows".format(len(copied), sum(copied.values())))
    return 0


if __name__ == "__main__":
    sys.exit(main())
