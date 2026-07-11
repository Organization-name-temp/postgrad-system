-- Postgres runs every *.sql here once, on first cluster initialization, as the
-- POSTGRES_USER (prs). The primary database (prs) is created from POSTGRES_DB;
-- this adds the dedicated test database the Pest suite runs against, so tests
-- never touch dev data.
CREATE DATABASE prs_test;
