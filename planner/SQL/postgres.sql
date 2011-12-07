CREATE SEQUENCE planner_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE planner (
    id integer DEFAULT nextval('planner_ids'::regclass) NOT NULL,
    user_id integer NOT NULL,
    "starred" smallint NOT NULL DEFAULT 0,
    "datetime" timestamp without time zone DEFAULT NULL,
    "text" text NOT NULL,
    "done" smallint NOT NULL DEFAULT 0,
    "deleted" smallint NOT NULL DEFAULT 0
);
    
ALTER TABLE ONLY planner
    ADD CONSTRAINT planner_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;
