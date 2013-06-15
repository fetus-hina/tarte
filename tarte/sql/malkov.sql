DROP TABLE IF EXISTS "malkov_word";
CREATE TABLE "malkov_word" (
    "id"        BIGSERIAL   PRIMARY KEY,
    "bot"       TEXT        NOT NULL,
    "text1"     TEXT        NOT NULL,
    "text2"     TEXT        NOT NULL,
    "text3"     TEXT        NULL,
    "is_start"  BOOLEAN     NOT NULL,
    "is_reply"  BOOLEAN     NOT NULL,
    "time"      TIME(0) WITH TIME ZONE NOT NULL,
    "count"     INTEGER     NOT NULL,
    UNIQUE ( "bot", "text1", "text2", "text3", "is_start", "is_reply", "time" )
);
CREATE INDEX "malkov_word_1" ON "malkov_word" ( "bot", "text1", "text2", "time" );

CREATE OR REPLACE FUNCTION timediff(TIME WITH TIME ZONE, TIME WITH TIME ZONE) RETURNS INTERVAL AS $$
DECLARE
    diff INTERVAL;
BEGIN
    diff := ('2000-01-01'::DATE + $1) - ('2000-01-01'::DATE + $2);
    IF diff < '-12:00:00'::INTERVAL THEN
        RETURN diff + '24:00:00'::INTERVAL;
    ELSIF diff < '0'::INTERVAL THEN
        RETURN -diff;
    ELSIF diff <= '12:00:00'::INTERVAL THEN
        RETURN diff;
    ELSE
        RETURN '24:00:00'::INTERVAL - diff;
    END IF;
END
$$ LANGUAGE 'plpgsql' IMMUTABLE RETURNS NULL ON NULL INPUT SECURITY INVOKER;

CREATE OR REPLACE FUNCTION time_distance(TIME WITH TIME ZONE, TIME WITH TIME ZONE) RETURNS NUMERIC AS $$
SELECT (43200 - TO_CHAR(timediff($1, $2), 'SSSS')::INTEGER) / 43200.0
$$ LANGUAGE 'sql' IMMUTABLE RETURNS NULL ON NULL INPUT SECURITY INVOKER;
