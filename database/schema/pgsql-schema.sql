--
-- PostgreSQL database dump
--

\restrict RhqIz5XjA00x8XDqQxdETMAdwts2JkSziMJjNVO2TZ9tF34oXPJU7EjV1GyPbVK

-- Dumped from database version 15.14 (Debian 15.14-1.pgdg13+1)
-- Dumped by pg_dump version 15.14 (Ubuntu 15.14-1.pgdg22.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: gpt_manager; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA gpt_manager;


--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS '';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: agent_prompt_directives; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.agent_prompt_directives (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    prompt_directive_id bigint,
    agent_id bigint,
    section text DEFAULT 'top'::character varying,
    "position" bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: agent_prompt_directives_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.agent_prompt_directives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_prompt_directives_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.agent_prompt_directives_id_seq OWNED BY gpt_manager.agent_prompt_directives.id;


--
-- Name: agent_thread_messageables; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.agent_thread_messageables (
    id bigint NOT NULL,
    agent_thread_message_id bigint,
    messageable_type text,
    messageable_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: agent_thread_messageables_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.agent_thread_messageables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_thread_messageables_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.agent_thread_messageables_id_seq OWNED BY gpt_manager.agent_thread_messageables.id;


--
-- Name: agent_thread_messages; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.agent_thread_messages (
    id bigint NOT NULL,
    agent_thread_id bigint,
    role text,
    title text,
    summary text,
    summarizer_offset integer DEFAULT 0,
    summarizer_total integer DEFAULT 0,
    content text,
    data json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: agent_thread_messages_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.agent_thread_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_thread_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.agent_thread_messages_id_seq OWNED BY gpt_manager.agent_thread_messages.id;


--
-- Name: agent_thread_runs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.agent_thread_runs (
    id bigint NOT NULL,
    agent_thread_id bigint,
    last_message_id bigint,
    job_dispatch_id bigint,
    status text,
    temperature numeric(5,2) DEFAULT NULL::numeric,
    tools json,
    tool_choice text DEFAULT 'auto'::character varying,
    response_format text DEFAULT 'text'::character varying,
    response_schema_id bigint,
    response_fragment_id bigint,
    json_schema_config json,
    response_json_schema json,
    seed text,
    started_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    stopped_at timestamp with time zone,
    refreshed_at timestamp with time zone,
    agent_model text,
    total_cost text,
    input_tokens bigint DEFAULT '0'::bigint,
    output_tokens bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: agent_thread_runs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.agent_thread_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_thread_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.agent_thread_runs_id_seq OWNED BY gpt_manager.agent_thread_runs.id;


--
-- Name: agent_threads; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.agent_threads (
    id bigint NOT NULL,
    team_id bigint NULL,
    user_id bigint,
    agent_id bigint,
    name text,
    summary text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: agent_threads_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.agent_threads_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_threads_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.agent_threads_id_seq OWNED BY gpt_manager.agent_threads.id;


--
-- Name: agents; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.agents (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    knowledge_id bigint,
    name text,
    description text DEFAULT ''::character varying,
    api text,
    model text,
    temperature numeric(5,2) DEFAULT 0.00,
    tools json,
    retry_count bigint DEFAULT '0'::bigint,
    threads_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: agents_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.agents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agents_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.agents_id_seq OWNED BY gpt_manager.agents.id;


--
-- Name: api_logs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.api_logs (
    id bigint NOT NULL,
    audit_request_id bigint,
    user_id bigint,
    api_class text,
    service_name text,
    status_code bigint,
    method text,
    url text,
    full_url text,
    request json,
    response json,
    request_headers json,
    response_headers json,
    stack_trace json,
    started_at timestamp with time zone,
    finished_at timestamp with time zone,
    run_time_ms double precision,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: api_logs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.api_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: api_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.api_logs_id_seq OWNED BY gpt_manager.api_logs.id;


--
-- Name: artifactables; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.artifactables (
    id bigint NOT NULL,
    artifact_id bigint,
    artifactable_id bigint,
    artifactable_type text,
    category text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: artifactables_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.artifactables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: artifactables_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.artifactables_id_seq OWNED BY gpt_manager.artifactables.id;


--
-- Name: artifacts; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.artifacts (
    id bigint NOT NULL,
    original_artifact_id bigint,
    team_id bigint,
    parent_artifact_id bigint,
    child_artifacts_count bigint DEFAULT '0'::bigint,
    schema_definition_id bigint,
    task_definition_id bigint,
    task_run_id bigint,
    task_process_id bigint,
    name text,
    "position" integer DEFAULT 0,
    model text,
    text_content text,
    json_content json,
    meta json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: artifacts_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.artifacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: artifacts_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.artifacts_id_seq OWNED BY gpt_manager.artifacts.id;


--
-- Name: audit_request; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.audit_request (
    id bigint NOT NULL,
    session_id text,
    user_id bigint,
    environment text,
    url text,
    request json,
    response json,
    logs text,
    profile text,
    "time" double precision,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: audit_request_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.audit_request_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_request_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.audit_request_id_seq OWNED BY gpt_manager.audit_request.id;


--
-- Name: audits; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.audits (
    id bigint NOT NULL,
    audit_request_id bigint,
    user_id bigint,
    event text,
    auditable_type text,
    auditable_id character(191) DEFAULT NULL::bpchar,
    old_values json,
    new_values json,
    tags text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: audits_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audits_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.audits_id_seq OWNED BY gpt_manager.audits.id;


--
-- Name: cache; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.cache (
    key text,
    value text,
    expiration integer
);


--
-- Name: cache_locks; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.cache_locks (
    key text,
    owner text,
    expiration integer
);


--
-- Name: content_sources; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.content_sources (
    id bigint NOT NULL,
    team_id bigint,
    name text,
    type text,
    url text DEFAULT ''::character varying,
    config json,
    polling_interval bigint DEFAULT '60'::bigint,
    last_checkpoint text,
    fetched_at timestamp with time zone,
    workflow_inputs_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: content_sources_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.content_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: content_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.content_sources_id_seq OWNED BY gpt_manager.content_sources.id;


--
-- Name: error_log_entry; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.error_log_entry (
    id bigint NOT NULL,
    error_log_id bigint,
    audit_request_id bigint,
    user_id bigint,
    message text DEFAULT ''::character varying,
    full_message text,
    data json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: error_log_entry_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.error_log_entry_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: error_log_entry_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.error_log_entry_id_seq OWNED BY gpt_manager.error_log_entry.id;


--
-- Name: error_logs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.error_logs (
    id bigint NOT NULL,
    root_id bigint,
    parent_id bigint,
    hash text,
    error_class text,
    code text,
    level text,
    message text,
    file text,
    line bigint,
    count bigint,
    last_seen_at timestamp with time zone,
    last_notified_at timestamp with time zone,
    send_notifications boolean DEFAULT true,
    stack_trace json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: error_logs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.error_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: error_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.error_logs_id_seq OWNED BY gpt_manager.error_logs.id;


--
-- Name: job_batches; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.job_batches (
    id bigint NOT NULL,
    name text,
    total_jobs integer,
    pending_jobs integer,
    failed_jobs integer,
    failed_job_ids text,
    options text,
    on_complete text,
    cancelled_at integer,
    created_at timestamp with time zone,
    finished_at integer
);


--
-- Name: job_batches_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.job_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.job_batches_id_seq OWNED BY gpt_manager.job_batches.id;


--
-- Name: job_dispatch; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.job_dispatch (
    id bigint NOT NULL,
    user_id bigint,
    name text,
    ref text,
    job_batch_id bigint,
    running_audit_request_id bigint,
    dispatch_audit_request_id bigint,
    status text,
    ran_at timestamp with time zone,
    completed_at timestamp with time zone,
    timeout_at timestamp with time zone,
    run_time integer,
    count bigint,
    data json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: job_dispatch_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.job_dispatch_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_dispatch_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.job_dispatch_id_seq OWNED BY gpt_manager.job_dispatch.id;


--
-- Name: job_dispatchables; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.job_dispatchables (
    id bigint NOT NULL,
    category text DEFAULT ''::character varying,
    job_dispatch_id bigint,
    model_type text,
    model_id character(36) DEFAULT NULL::bpchar,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: job_dispatchables_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.job_dispatchables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_dispatchables_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.job_dispatchables_id_seq OWNED BY gpt_manager.job_dispatchables.id;


--
-- Name: knowledge; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.knowledge (
    id bigint NOT NULL,
    team_id bigint,
    name text,
    description text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: knowledge_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.knowledge_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: knowledge_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.knowledge_id_seq OWNED BY gpt_manager.knowledge.id;


--
-- Name: migrations; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.migrations (
    id bigint NOT NULL,
    migration text,
    batch integer
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.migrations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.migrations_id_seq OWNED BY gpt_manager.migrations.id;


--
-- Name: model_refs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.model_refs (
    id bigint NOT NULL,
    prefix text,
    ref text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: model_refs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.model_refs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: model_refs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.model_refs_id_seq OWNED BY gpt_manager.model_refs.id;


--
-- Name: object_tag_taggables; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.object_tag_taggables (
    id bigint NOT NULL,
    object_tag_id bigint,
    taggable_type text,
    taggable_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: object_tag_taggables_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.object_tag_taggables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: object_tag_taggables_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.object_tag_taggables_id_seq OWNED BY gpt_manager.object_tag_taggables.id;


--
-- Name: object_tags; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.object_tags (
    id bigint NOT NULL,
    category text,
    name text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: object_tags_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.object_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: object_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.object_tags_id_seq OWNED BY gpt_manager.object_tags.id;


--
-- Name: on_demands__object_attribute_sources; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.on_demands__object_attribute_sources (
    id bigint NOT NULL,
    object_attribute_id bigint,
    source_type text,
    source_id text,
    explanation text,
    stored_file_id character(36) DEFAULT NULL::bpchar,
    agent_thread_message_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: on_demands__object_attribute_sources_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.on_demands__object_attribute_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: on_demands__object_attribute_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.on_demands__object_attribute_sources_id_seq OWNED BY gpt_manager.on_demands__object_attribute_sources.id;


--
-- Name: on_demands__object_attributes; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.on_demands__object_attributes (
    id bigint NOT NULL,
    object_id bigint,
    name text,
    date timestamp with time zone,
    text_value text,
    json_value json,
    reason text,
    confidence text,
    agent_thread_run_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: on_demands__object_attributes_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.on_demands__object_attributes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: on_demands__object_attributes_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.on_demands__object_attributes_id_seq OWNED BY gpt_manager.on_demands__object_attributes.id;


--
-- Name: on_demands__object_relationships; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.on_demands__object_relationships (
    id bigint NOT NULL,
    relationship_name text,
    object_id bigint,
    related_object_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: on_demands__object_relationships_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.on_demands__object_relationships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: on_demands__object_relationships_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.on_demands__object_relationships_id_seq OWNED BY gpt_manager.on_demands__object_relationships.id;


--
-- Name: on_demands__objects; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.on_demands__objects (
    id bigint NOT NULL,
    schema_definition_id bigint,
    root_object_id bigint,
    type text,
    name text,
    date timestamp with time zone,
    description text,
    url text,
    meta json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: on_demands__objects_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.on_demands__objects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: on_demands__objects_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.on_demands__objects_id_seq OWNED BY gpt_manager.on_demands__objects.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.password_reset_tokens (
    email text,
    token text,
    created_at timestamp with time zone
);


--
-- Name: permissions; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.permissions (
    id bigint NOT NULL,
    name text,
    display_name text,
    description text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.permissions_id_seq OWNED BY gpt_manager.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type text,
    tokenable_id bigint,
    name text,
    token text,
    abilities text,
    last_used_at timestamp with time zone,
    expires_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.personal_access_tokens_id_seq OWNED BY gpt_manager.personal_access_tokens.id;


--
-- Name: prompt_directives; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.prompt_directives (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    name text,
    directive_text text,
    agents_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: prompt_directives_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.prompt_directives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: prompt_directives_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.prompt_directives_id_seq OWNED BY gpt_manager.prompt_directives.id;


--
-- Name: resource_package_imports; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.resource_package_imports (
    id character(36) DEFAULT NULL::bpchar,
    team_uuid character(36) DEFAULT NULL::bpchar,
    resource_package_id character(36) DEFAULT NULL::bpchar,
    resource_package_version_id character(36) DEFAULT NULL::bpchar,
    source_object_id character(36) DEFAULT NULL::bpchar,
    local_object_id character(36) DEFAULT NULL::bpchar,
    object_type text,
    can_view boolean DEFAULT false,
    can_edit boolean DEFAULT false,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: resource_package_versions; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.resource_package_versions (
    id character(36) DEFAULT NULL::bpchar,
    resource_package_id character(36) DEFAULT NULL::bpchar,
    version text,
    version_hash text,
    definitions json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: resource_packages; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.resource_packages (
    id character(36) DEFAULT NULL::bpchar,
    team_uuid character(36) DEFAULT NULL::bpchar,
    resource_type text,
    resource_id text,
    name text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: role_permission; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.role_permission (
    id bigint NOT NULL,
    role_id bigint,
    permission_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: role_permission_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.role_permission_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: role_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.role_permission_id_seq OWNED BY gpt_manager.role_permission.id;


--
-- Name: role_user; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.role_user (
    id bigint NOT NULL,
    user_id bigint,
    role_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: role_user_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.role_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: role_user_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.role_user_id_seq OWNED BY gpt_manager.role_user.id;


--
-- Name: roles; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.roles (
    id bigint NOT NULL,
    name text,
    display_name text,
    description text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.roles_id_seq OWNED BY gpt_manager.roles.id;


--
-- Name: schema_associations; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.schema_associations (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    schema_definition_id bigint,
    schema_fragment_id bigint,
    object_type text,
    object_id bigint,
    category text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: schema_associations_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.schema_associations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_associations_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.schema_associations_id_seq OWNED BY gpt_manager.schema_associations.id;


--
-- Name: schema_definitions; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.schema_definitions (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    type text,
    name text,
    description text,
    schema_format text,
    schema json,
    response_example json,
    fragments_count bigint DEFAULT '0'::bigint,
    associations_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: schema_definitions_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.schema_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.schema_definitions_id_seq OWNED BY gpt_manager.schema_definitions.id;


--
-- Name: schema_fragments; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.schema_fragments (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    schema_definition_id bigint,
    name text,
    fragment_selector json,
    associations_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: schema_fragments_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.schema_fragments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_fragments_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.schema_fragments_id_seq OWNED BY gpt_manager.schema_fragments.id;


--
-- Name: schema_history; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.schema_history (
    id bigint NOT NULL,
    schema_definition_id bigint,
    user_id bigint,
    schema json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: schema_history_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.schema_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_history_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.schema_history_id_seq OWNED BY gpt_manager.schema_history.id;


--
-- Name: sessions; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.sessions (
    id text,
    user_id bigint,
    ip_address text,
    user_agent text,
    payload text,
    last_activity integer
);


--
-- Name: stored_file_storables; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.stored_file_storables (
    id bigint NOT NULL,
    stored_file_id character(36) DEFAULT NULL::bpchar,
    storable_type text,
    storable_id bigint,
    category text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: stored_file_storables_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.stored_file_storables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stored_file_storables_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.stored_file_storables_id_seq OWNED BY gpt_manager.stored_file_storables.id;


--
-- Name: stored_files; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.stored_files (
    id character(36) DEFAULT NULL::bpchar,
    disk text,
    filepath text,
    filename text,
    url text,
    mime text,
    size bigint DEFAULT '0'::bigint,
    exif json,
    meta json,
    location json,
    page_number bigint,
    transcode_name text,
    is_transcoding boolean DEFAULT false,
    original_stored_file_id character(36) DEFAULT NULL::bpchar,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: task_artifact_filters; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.task_artifact_filters (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    source_task_definition_id bigint,
    target_task_definition_id bigint,
    include_text boolean DEFAULT true,
    include_files boolean DEFAULT true,
    include_json boolean DEFAULT true,
    include_meta boolean,
    schema_fragment_id bigint,
    meta_fragment_selector json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: task_artifact_filters_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.task_artifact_filters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_artifact_filters_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.task_artifact_filters_id_seq OWNED BY gpt_manager.task_artifact_filters.id;


--
-- Name: task_definition_directives; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.task_definition_directives (
    id bigint NOT NULL,
    task_definition_id bigint,
    prompt_directive_id bigint,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    section text DEFAULT 'top'::character varying,
    "position" bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: task_definition_directives_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.task_definition_directives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_definition_directives_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.task_definition_directives_id_seq OWNED BY gpt_manager.task_definition_directives.id;


--
-- Name: task_definitions; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.task_definitions (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    name text,
    description text,
    task_runner_name text,
    task_runner_config json,
    schema_definition_id bigint,
    agent_id bigint,
    response_format text DEFAULT 'text'::character varying,
    input_artifact_mode text DEFAULT ''::character varying,
    input_artifact_levels json,
    output_artifact_mode text DEFAULT ''::character varying,
    output_artifact_levels json,
    timeout_after_seconds bigint DEFAULT '300'::bigint,
    max_process_retries bigint DEFAULT '3'::bigint,
    task_run_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: task_definitions_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.task_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.task_definitions_id_seq OWNED BY gpt_manager.task_definitions.id;


--
-- Name: task_inputs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.task_inputs (
    id bigint NOT NULL,
    task_definition_id bigint,
    workflow_input_id bigint,
    task_run_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: task_inputs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.task_inputs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_inputs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.task_inputs_id_seq OWNED BY gpt_manager.task_inputs.id;


--
-- Name: task_process_listeners; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.task_process_listeners (
    id bigint NOT NULL,
    task_process_id bigint,
    event_type text,
    event_id text,
    event_fired_at timestamp with time zone,
    event_handled_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: task_process_listeners_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.task_process_listeners_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_process_listeners_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.task_process_listeners_id_seq OWNED BY gpt_manager.task_process_listeners.id;


--
-- Name: task_processes; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.task_processes (
    id bigint NOT NULL,
    task_run_id bigint,
    agent_thread_id bigint,
    last_job_dispatch_id bigint,
    status text DEFAULT 'Pending'::character varying,
    name text,
    activity text,
    percent_complete numeric(5,2) DEFAULT 0.00,
    started_at timestamp with time zone,
    stopped_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    timeout_at timestamp with time zone,
    restart_count bigint DEFAULT '0'::bigint,
    job_dispatch_count bigint DEFAULT '0'::bigint,
    input_artifact_count bigint DEFAULT '0'::bigint,
    output_artifact_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: task_processes_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.task_processes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_processes_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.task_processes_id_seq OWNED BY gpt_manager.task_processes.id;


--
-- Name: task_runs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.task_runs (
    id bigint NOT NULL,
    task_definition_id bigint,
    workflow_run_id bigint,
    workflow_node_id bigint,
    status text DEFAULT 'Pending'::character varying,
    name text,
    step text DEFAULT 'Initial'::character varying,
    percent_complete numeric(5,2) DEFAULT 0.00,
    started_at timestamp with time zone,
    stopped_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    skipped_at timestamp with time zone,
    process_count bigint DEFAULT '0'::bigint,
    input_artifacts_count bigint DEFAULT '0'::bigint,
    output_artifacts_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    task_input_id bigint
);


--
-- Name: task_runs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.task_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.task_runs_id_seq OWNED BY gpt_manager.task_runs.id;


--
-- Name: team_object_attribute_sources; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.team_object_attribute_sources (
    id bigint NOT NULL,
    team_object_attribute_id bigint,
    source_type text,
    source_id text,
    explanation text,
    stored_file_id character(36) DEFAULT NULL::bpchar,
    agent_thread_message_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_object_attribute_sources_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.team_object_attribute_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_object_attribute_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.team_object_attribute_sources_id_seq OWNED BY gpt_manager.team_object_attribute_sources.id;


--
-- Name: team_object_attributes; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.team_object_attributes (
    id bigint NOT NULL,
    team_object_id bigint,
    name text,
    text_value text,
    json_value json,
    reason text,
    confidence text,
    agent_thread_run_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_object_attributes_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.team_object_attributes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_object_attributes_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.team_object_attributes_id_seq OWNED BY gpt_manager.team_object_attributes.id;


--
-- Name: team_object_relationships; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.team_object_relationships (
    id bigint NOT NULL,
    team_object_id bigint,
    related_team_object_id bigint,
    relationship_name text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_object_relationships_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.team_object_relationships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_object_relationships_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.team_object_relationships_id_seq OWNED BY gpt_manager.team_object_relationships.id;


--
-- Name: team_objects; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.team_objects (
    id bigint NOT NULL,
    team_id bigint,
    schema_definition_id bigint,
    root_object_id bigint,
    type text,
    name text,
    date timestamp with time zone,
    description text,
    url text,
    meta json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_objects_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.team_objects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_objects_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.team_objects_id_seq OWNED BY gpt_manager.team_objects.id;


--
-- Name: team_user; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.team_user (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: team_user_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.team_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_user_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.team_user_id_seq OWNED BY gpt_manager.team_user.id;


--
-- Name: teams; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.teams (
    id bigint NOT NULL,
    uuid character(36) DEFAULT NULL::bpchar,
    name text,
    namespace text,
    logo text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: teams_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: teams_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.teams_id_seq OWNED BY gpt_manager.teams.id;


--
-- Name: tortguard__object_attributes; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.tortguard__object_attributes (
    id bigint NOT NULL,
    object_id bigint,
    name text,
    date timestamp with time zone,
    text_value text,
    json_value json,
    description text,
    confidence text,
    message_id bigint,
    source_stored_file_id character(36) DEFAULT NULL::bpchar,
    thread_run_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: tortguard__object_attributes_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.tortguard__object_attributes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tortguard__object_attributes_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.tortguard__object_attributes_id_seq OWNED BY gpt_manager.tortguard__object_attributes.id;


--
-- Name: tortguard__object_relationships; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.tortguard__object_relationships (
    id bigint NOT NULL,
    relationship_name text,
    object_id bigint,
    related_object_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: tortguard__object_relationships_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.tortguard__object_relationships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tortguard__object_relationships_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.tortguard__object_relationships_id_seq OWNED BY gpt_manager.tortguard__object_relationships.id;


--
-- Name: tortguard__objects; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.tortguard__objects (
    id bigint NOT NULL,
    ref text,
    type text,
    name text,
    description text,
    url text,
    meta json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: tortguard__objects_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.tortguard__objects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tortguard__objects_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.tortguard__objects_id_seq OWNED BY gpt_manager.tortguard__objects.id;


--
-- Name: usage_events; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.usage_events (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    object_type text,
    object_id character(36) DEFAULT NULL::bpchar,
    event_type text,
    run_time_ms bigint DEFAULT '0'::bigint,
    input_tokens bigint DEFAULT '0'::bigint,
    output_tokens bigint DEFAULT '0'::bigint,
    input_cost numeric(12,4) DEFAULT 0.0000,
    output_cost numeric(12,4) DEFAULT 0.0000,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: usage_events_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.usage_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usage_events_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.usage_events_id_seq OWNED BY gpt_manager.usage_events.id;


--
-- Name: usage_summaries; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.usage_summaries (
    id bigint NOT NULL,
    object_type text,
    object_id character(36) DEFAULT NULL::bpchar,
    count bigint DEFAULT '1'::bigint,
    run_time_ms bigint DEFAULT '0'::bigint,
    input_tokens bigint DEFAULT '0'::bigint,
    output_tokens bigint DEFAULT '0'::bigint,
    input_cost numeric(12,4) DEFAULT 0.0000,
    output_cost numeric(12,4) DEFAULT 0.0000,
    total_cost numeric(12,4) DEFAULT NULL::numeric,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: usage_summaries_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.usage_summaries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usage_summaries_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.usage_summaries_id_seq OWNED BY gpt_manager.usage_summaries.id;


--
-- Name: users; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.users (
    id bigint NOT NULL,
    name text,
    email text,
    email_verified_at timestamp with time zone,
    password text,
    remember_token text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.users_id_seq OWNED BY gpt_manager.users.id;


--
-- Name: workflow_api_invocations; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.workflow_api_invocations (
    id bigint NOT NULL,
    workflow_run_id bigint,
    name text,
    webhook_url text,
    payload json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: workflow_api_invocations_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.workflow_api_invocations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_api_invocations_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.workflow_api_invocations_id_seq OWNED BY gpt_manager.workflow_api_invocations.id;


--
-- Name: workflow_connections; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.workflow_connections (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    workflow_definition_id bigint,
    source_node_id bigint,
    target_node_id bigint,
    source_output_port text,
    target_input_port text,
    name text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: workflow_connections_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.workflow_connections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_connections_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.workflow_connections_id_seq OWNED BY gpt_manager.workflow_connections.id;


--
-- Name: workflow_definitions; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.workflow_definitions (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    name text,
    description text DEFAULT ''::character varying,
    workflow_runs_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: workflow_definitions_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.workflow_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.workflow_definitions_id_seq OWNED BY gpt_manager.workflow_definitions.id;


--
-- Name: workflow_inputs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.workflow_inputs (
    id bigint NOT NULL,
    content_source_id bigint,
    team_id bigint,
    user_id bigint,
    name text,
    description text,
    content text,
    data json,
    tokens bigint DEFAULT '0'::bigint,
    is_url boolean DEFAULT false,
    team_object_id bigint,
    team_object_type text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: workflow_inputs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.workflow_inputs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_inputs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.workflow_inputs_id_seq OWNED BY gpt_manager.workflow_inputs.id;


--
-- Name: workflow_nodes; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.workflow_nodes (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    workflow_definition_id bigint,
    task_definition_id bigint,
    name text,
    settings json,
    params json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: workflow_nodes_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.workflow_nodes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_nodes_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.workflow_nodes_id_seq OWNED BY gpt_manager.workflow_nodes.id;


--
-- Name: workflow_runs; Type: TABLE; Schema: gpt_manager; Owner: -
--

CREATE TABLE gpt_manager.workflow_runs (
    id bigint NOT NULL,
    workflow_definition_id bigint,
    name text DEFAULT ''::character varying,
    status text DEFAULT 'Pending'::character varying,
    started_at timestamp with time zone,
    stopped_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    has_run_all_tasks boolean DEFAULT false,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: workflow_runs_id_seq; Type: SEQUENCE; Schema: gpt_manager; Owner: -
--

CREATE SEQUENCE gpt_manager.workflow_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: gpt_manager; Owner: -
--

ALTER SEQUENCE gpt_manager.workflow_runs_id_seq OWNED BY gpt_manager.workflow_runs.id;


--
-- Name: agent_prompt_directives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_prompt_directives (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    prompt_directive_id bigint,
    agent_id bigint,
    section text DEFAULT 'top'::character varying,
    "position" bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: agent_prompt_directives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agent_prompt_directives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_prompt_directives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_prompt_directives_id_seq OWNED BY public.agent_prompt_directives.id;


--
-- Name: agent_thread_messageables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_thread_messageables (
    id bigint NOT NULL,
    agent_thread_message_id bigint,
    messageable_type text,
    messageable_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: agent_thread_messageables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agent_thread_messageables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_thread_messageables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_thread_messageables_id_seq OWNED BY public.agent_thread_messageables.id;


--
-- Name: agent_thread_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_thread_messages (
    id bigint NOT NULL,
    agent_thread_id bigint,
    role text,
    title text,
    summary text,
    summarizer_offset integer DEFAULT 0,
    summarizer_total integer DEFAULT 0,
    content text,
    data json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    api_response_id text
);


--
-- Name: agent_thread_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agent_thread_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_thread_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_thread_messages_id_seq OWNED BY public.agent_thread_messages.id;


--
-- Name: agent_thread_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_thread_runs (
    id bigint NOT NULL,
    agent_thread_id bigint,
    last_message_id bigint,
    job_dispatch_id bigint,
    status text,
    response_format text DEFAULT 'text'::character varying,
    response_schema_id bigint,
    response_fragment_id bigint,
    json_schema_config json,
    response_json_schema json,
    seed text,
    started_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    stopped_at timestamp with time zone,
    refreshed_at timestamp with time zone,
    agent_model text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    api_options json,
    mcp_server_id bigint,
    timeout integer DEFAULT 60
);


--
-- Name: agent_thread_runs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agent_thread_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_thread_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_thread_runs_id_seq OWNED BY public.agent_thread_runs.id;


--
-- Name: agent_threads; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_threads (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    agent_id bigint,
    name text,
    summary text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: agent_threads_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agent_threads_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_threads_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_threads_id_seq OWNED BY public.agent_threads.id;


--
-- Name: agents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agents (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    knowledge_id bigint,
    name text,
    description text DEFAULT ''::character varying,
    model text,
    retry_count bigint DEFAULT '0'::bigint,
    threads_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    api_options json
);


--
-- Name: agents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agents_id_seq OWNED BY public.agents.id;


--
-- Name: api_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.api_logs (
    id bigint NOT NULL,
    audit_request_id bigint,
    user_id bigint,
    api_class text,
    service_name text,
    status_code bigint,
    method text,
    url text,
    full_url text,
    request json,
    response json,
    request_headers json,
    response_headers json,
    stack_trace json,
    started_at timestamp with time zone,
    finished_at timestamp with time zone,
    run_time_ms double precision,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: api_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.api_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: api_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.api_logs_id_seq OWNED BY public.api_logs.id;


--
-- Name: artifactables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.artifactables (
    id bigint NOT NULL,
    artifact_id bigint,
    artifactable_id bigint,
    artifactable_type text,
    category text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: artifactables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.artifactables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: artifactables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.artifactables_id_seq OWNED BY public.artifactables.id;


--
-- Name: artifacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.artifacts (
    id bigint NOT NULL,
    original_artifact_id bigint,
    team_id bigint,
    parent_artifact_id bigint,
    child_artifacts_count bigint DEFAULT '0'::bigint,
    schema_definition_id bigint,
    task_definition_id bigint,
    task_run_id bigint,
    task_process_id bigint,
    name text,
    "position" integer DEFAULT 0,
    model text,
    text_content text,
    json_content json,
    meta json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: artifacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.artifacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: artifacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.artifacts_id_seq OWNED BY public.artifacts.id;


--
-- Name: assistant_actions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.assistant_actions (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    agent_thread_id bigint,
    context text,
    action_type text,
    target_type text,
    target_id text,
    status text DEFAULT 'pending'::character varying,
    title text,
    description text,
    payload json,
    preview_data json,
    result_data json,
    error_message text,
    started_at timestamp without time zone,
    completed_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    duration integer
);


--
-- Name: assistant_actions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.assistant_actions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: assistant_actions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.assistant_actions_id_seq OWNED BY public.assistant_actions.id;


--
-- Name: audit_request; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_request (
    id bigint NOT NULL,
    session_id text,
    user_id bigint,
    environment text,
    url text,
    request json,
    response json,
    logs text,
    profile text,
    "time" double precision,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: audit_request_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_request_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_request_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_request_id_seq OWNED BY public.audit_request.id;


--
-- Name: audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audits (
    id bigint NOT NULL,
    audit_request_id bigint,
    user_id bigint,
    event text,
    auditable_type text,
    auditable_id character(191) DEFAULT NULL::bpchar,
    old_values json,
    new_values json,
    tags text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: audits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audits_id_seq OWNED BY public.audits.id;


--
-- Name: auth_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auth_tokens (
    id bigint NOT NULL,
    team_id bigint,
    service text,
    type text,
    name text,
    access_token text,
    refresh_token text,
    id_token text,
    scopes json,
    expires_at timestamp without time zone,
    metadata json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone
);


--
-- Name: auth_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auth_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auth_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auth_tokens_id_seq OWNED BY public.auth_tokens.id;


--
-- Name: billing_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.billing_history (
    id bigint NOT NULL,
    team_id bigint,
    subscription_id bigint,
    stripe_invoice_id text,
    stripe_payment_intent_id text,
    type text,
    status text,
    amount numeric(10,2) DEFAULT NULL::numeric,
    tax_amount numeric(10,2) DEFAULT '0'::numeric,
    total_amount numeric(10,2) DEFAULT NULL::numeric,
    currency text DEFAULT 'USD'::character varying,
    description text,
    line_items json,
    period_start timestamp without time zone,
    period_end timestamp without time zone,
    due_date timestamp without time zone,
    paid_at timestamp without time zone,
    invoice_url text,
    billing_date timestamp without time zone,
    metadata json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone,
    stripe_charge_id text
);


--
-- Name: billing_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.billing_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: billing_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.billing_history_id_seq OWNED BY public.billing_history.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key text NOT NULL,
    value text,
    expiration integer
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key text NOT NULL,
    owner text,
    expiration integer
);


--
-- Name: content_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.content_sources (
    id bigint NOT NULL,
    team_id bigint,
    name text,
    type text,
    url text DEFAULT ''::character varying,
    config json,
    polling_interval bigint DEFAULT '60'::bigint,
    last_checkpoint text,
    fetched_at timestamp with time zone,
    workflow_inputs_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: content_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.content_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: content_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.content_sources_id_seq OWNED BY public.content_sources.id;


--
-- Name: demand_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.demand_templates (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    stored_file_id character(36) DEFAULT NULL::bpchar,
    name text,
    description text,
    category text,
    metadata json,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone,
    template_variables json
);


--
-- Name: demand_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.demand_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: demand_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.demand_templates_id_seq OWNED BY public.demand_templates.id;


--
-- Name: error_log_entry; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.error_log_entry (
    id bigint NOT NULL,
    error_log_id bigint,
    audit_request_id bigint,
    user_id bigint,
    message text DEFAULT ''::character varying,
    full_message text,
    data json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: error_log_entry_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.error_log_entry_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: error_log_entry_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.error_log_entry_id_seq OWNED BY public.error_log_entry.id;


--
-- Name: error_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.error_logs (
    id bigint NOT NULL,
    root_id bigint,
    parent_id bigint,
    hash text,
    error_class text,
    code text,
    level text,
    message text,
    file text,
    line bigint,
    count bigint,
    last_seen_at timestamp with time zone,
    last_notified_at timestamp with time zone,
    send_notifications boolean DEFAULT true,
    stack_trace json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: error_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.error_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: error_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.error_logs_id_seq OWNED BY public.error_logs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id bigint NOT NULL,
    name text,
    total_jobs integer,
    pending_jobs integer,
    failed_jobs integer,
    failed_job_ids text,
    options text,
    on_complete text,
    cancelled_at integer,
    created_at timestamp with time zone,
    finished_at integer
);


--
-- Name: job_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_batches_id_seq OWNED BY public.job_batches.id;


--
-- Name: job_dispatch; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_dispatch (
    id bigint NOT NULL,
    user_id bigint,
    name text,
    ref text,
    job_batch_id bigint,
    running_audit_request_id bigint,
    dispatch_audit_request_id bigint,
    status text,
    ran_at timestamp with time zone,
    completed_at timestamp with time zone,
    timeout_at timestamp with time zone,
    count bigint,
    data json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    run_time_ms integer
);


--
-- Name: job_dispatch_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_dispatch_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_dispatch_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_dispatch_id_seq OWNED BY public.job_dispatch.id;


--
-- Name: job_dispatchables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_dispatchables (
    id bigint NOT NULL,
    category text DEFAULT ''::character varying,
    job_dispatch_id bigint,
    model_type text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    model_id bigint
);


--
-- Name: job_dispatchables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_dispatchables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_dispatchables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_dispatchables_id_seq OWNED BY public.job_dispatchables.id;


--
-- Name: knowledge; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.knowledge (
    id bigint NOT NULL,
    team_id bigint,
    name text,
    description text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: knowledge_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.knowledge_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: knowledge_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.knowledge_id_seq OWNED BY public.knowledge.id;


--
-- Name: mcp_servers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mcp_servers (
    id bigint NOT NULL,
    team_id bigint,
    name text,
    description text,
    server_url text,
    headers json,
    allowed_tools json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone
);


--
-- Name: mcp_servers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mcp_servers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mcp_servers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mcp_servers_id_seq OWNED BY public.mcp_servers.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id bigint NOT NULL,
    migration text,
    batch integer
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_refs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_refs (
    id bigint NOT NULL,
    prefix text,
    ref text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: model_refs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.model_refs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: model_refs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.model_refs_id_seq OWNED BY public.model_refs.id;


--
-- Name: object_tag_taggables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.object_tag_taggables (
    id bigint NOT NULL,
    object_tag_id bigint,
    taggable_type text,
    taggable_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: object_tag_taggables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.object_tag_taggables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: object_tag_taggables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.object_tag_taggables_id_seq OWNED BY public.object_tag_taggables.id;


--
-- Name: object_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.object_tags (
    id bigint NOT NULL,
    category text,
    name text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: object_tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.object_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: object_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.object_tags_id_seq OWNED BY public.object_tags.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email text NOT NULL,
    token text,
    created_at timestamp with time zone
);


--
-- Name: payment_methods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_methods (
    id bigint NOT NULL,
    team_id bigint,
    stripe_payment_method_id text,
    type text,
    card_brand text,
    card_last_four text,
    card_exp_month integer,
    card_exp_year integer,
    is_default boolean DEFAULT false,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone
);


--
-- Name: payment_methods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payment_methods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payment_methods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payment_methods_id_seq OWNED BY public.payment_methods.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name text,
    display_name text,
    description text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type text,
    tokenable_id bigint,
    name text,
    token text,
    abilities text,
    last_used_at timestamp with time zone,
    expires_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: prompt_directives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prompt_directives (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    name text,
    directive_text text,
    agents_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: prompt_directives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.prompt_directives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: prompt_directives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.prompt_directives_id_seq OWNED BY public.prompt_directives.id;


--
-- Name: resource_package_imports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.resource_package_imports (
    id character(36) DEFAULT NULL::bpchar NOT NULL,
    team_uuid character(36) DEFAULT NULL::bpchar,
    resource_package_id character(36) DEFAULT NULL::bpchar,
    resource_package_version_id character(36) DEFAULT NULL::bpchar,
    source_object_id character(36) DEFAULT NULL::bpchar,
    local_object_id character(36) DEFAULT NULL::bpchar,
    object_type text,
    can_view boolean DEFAULT false,
    can_edit boolean DEFAULT false,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: resource_package_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.resource_package_versions (
    id character(36) DEFAULT NULL::bpchar NOT NULL,
    resource_package_id character(36) DEFAULT NULL::bpchar,
    version text,
    version_hash text,
    definitions json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: resource_packages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.resource_packages (
    id character(36) DEFAULT NULL::bpchar NOT NULL,
    team_uuid character(36) DEFAULT NULL::bpchar,
    resource_type text,
    resource_id text,
    name text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: role_permission; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_permission (
    id bigint NOT NULL,
    role_id bigint,
    permission_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: role_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.role_permission_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: role_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.role_permission_id_seq OWNED BY public.role_permission.id;


--
-- Name: role_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_user (
    id bigint NOT NULL,
    user_id bigint,
    role_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: role_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.role_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: role_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.role_user_id_seq OWNED BY public.role_user.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name text,
    display_name text,
    description text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: schema_associations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schema_associations (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    schema_definition_id bigint,
    schema_fragment_id bigint,
    object_type text,
    object_id bigint,
    category text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: schema_associations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.schema_associations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_associations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.schema_associations_id_seq OWNED BY public.schema_associations.id;


--
-- Name: schema_definitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schema_definitions (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    type text,
    name text,
    description text,
    schema_format text,
    schema json,
    response_example json,
    fragments_count bigint DEFAULT '0'::bigint,
    associations_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    meta json
);


--
-- Name: schema_definitions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.schema_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.schema_definitions_id_seq OWNED BY public.schema_definitions.id;


--
-- Name: schema_fragments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schema_fragments (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    schema_definition_id bigint,
    name text,
    fragment_selector json,
    associations_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: schema_fragments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.schema_fragments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_fragments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.schema_fragments_id_seq OWNED BY public.schema_fragments.id;


--
-- Name: schema_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schema_history (
    id bigint NOT NULL,
    schema_definition_id bigint,
    user_id bigint,
    schema json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: schema_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.schema_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.schema_history_id_seq OWNED BY public.schema_history.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id text NOT NULL,
    user_id bigint,
    ip_address text,
    user_agent text,
    payload text,
    last_activity integer
);


--
-- Name: stored_file_storables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stored_file_storables (
    id bigint NOT NULL,
    stored_file_id character(36) DEFAULT NULL::bpchar,
    storable_type text,
    storable_id bigint,
    category text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: stored_file_storables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stored_file_storables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stored_file_storables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stored_file_storables_id_seq OWNED BY public.stored_file_storables.id;


--
-- Name: stored_files; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stored_files (
    id character(36) DEFAULT NULL::bpchar NOT NULL,
    disk text,
    filepath text,
    filename text,
    url text,
    mime text,
    size bigint DEFAULT '0'::bigint,
    exif json,
    meta json,
    location json,
    page_number bigint,
    transcode_name text,
    is_transcoding boolean DEFAULT false,
    original_stored_file_id character(36) DEFAULT NULL::bpchar,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    team_id bigint,
    user_id bigint
);


--
-- Name: subscription_plans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_plans (
    id bigint NOT NULL,
    name text,
    slug text,
    description text,
    stripe_price_id text,
    monthly_price numeric(10,2) DEFAULT '0'::numeric,
    yearly_price numeric(10,2) DEFAULT '0'::numeric,
    is_active boolean DEFAULT true,
    features json,
    usage_limits json,
    sort_order integer DEFAULT 0,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone
);


--
-- Name: subscription_plans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_plans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_plans_id_seq OWNED BY public.subscription_plans.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    team_id bigint,
    subscription_plan_id bigint,
    stripe_customer_id text,
    stripe_subscription_id text,
    status text DEFAULT 'inactive'::character varying,
    billing_cycle text DEFAULT 'monthly'::character varying,
    monthly_amount numeric(10,2) DEFAULT '0'::numeric,
    yearly_amount numeric(10,2) DEFAULT '0'::numeric,
    trial_ends_at timestamp without time zone,
    current_period_start timestamp without time zone,
    current_period_end timestamp without time zone,
    canceled_at timestamp without time zone,
    ends_at timestamp without time zone,
    cancel_at_period_end boolean DEFAULT false,
    metadata json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone
);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: task_artifact_filters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_artifact_filters (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    source_task_definition_id bigint,
    target_task_definition_id bigint,
    include_text boolean DEFAULT true,
    include_files boolean DEFAULT true,
    include_json boolean DEFAULT true,
    include_meta boolean,
    schema_fragment_id bigint,
    meta_fragment_selector json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: task_artifact_filters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_artifact_filters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_artifact_filters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_artifact_filters_id_seq OWNED BY public.task_artifact_filters.id;


--
-- Name: task_definition_directives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_definition_directives (
    id bigint NOT NULL,
    task_definition_id bigint,
    prompt_directive_id bigint,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    section text DEFAULT 'top'::character varying,
    "position" bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: task_definition_directives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_definition_directives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_definition_directives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_definition_directives_id_seq OWNED BY public.task_definition_directives.id;


--
-- Name: task_definitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_definitions (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    name text,
    description text,
    task_runner_name text,
    task_runner_config json,
    schema_definition_id bigint,
    agent_id bigint,
    response_format text DEFAULT 'text'::character varying,
    input_artifact_mode text DEFAULT ''::character varying,
    input_artifact_levels json,
    output_artifact_mode text DEFAULT ''::character varying,
    output_artifact_levels json,
    timeout_after_seconds bigint DEFAULT '300'::bigint,
    max_process_retries bigint DEFAULT '3'::bigint,
    task_run_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    task_queue_type_id bigint
);


--
-- Name: task_definitions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_definitions_id_seq OWNED BY public.task_definitions.id;


--
-- Name: task_inputs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_inputs (
    id bigint NOT NULL,
    task_definition_id bigint,
    workflow_input_id bigint,
    task_run_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: task_inputs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_inputs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_inputs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_inputs_id_seq OWNED BY public.task_inputs.id;


--
-- Name: task_process_listeners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_process_listeners (
    id bigint NOT NULL,
    task_process_id bigint,
    event_type text,
    event_fired_at timestamp with time zone,
    event_handled_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    event_id bigint
);


--
-- Name: task_process_listeners_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_process_listeners_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_process_listeners_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_process_listeners_id_seq OWNED BY public.task_process_listeners.id;


--
-- Name: task_processes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_processes (
    id bigint NOT NULL,
    task_run_id bigint,
    agent_thread_id bigint,
    last_job_dispatch_id bigint,
    status text DEFAULT 'Pending'::character varying,
    name text,
    activity text,
    percent_complete numeric(5,2) DEFAULT 0.00,
    started_at timestamp with time zone,
    stopped_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    timeout_at timestamp with time zone,
    restart_count bigint DEFAULT '0'::bigint,
    job_dispatch_count bigint DEFAULT '0'::bigint,
    input_artifact_count bigint DEFAULT '0'::bigint,
    output_artifact_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    incomplete_at timestamp without time zone,
    meta json,
    is_ready boolean DEFAULT false
);


--
-- Name: task_processes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_processes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_processes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_processes_id_seq OWNED BY public.task_processes.id;


--
-- Name: task_queue_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_queue_types (
    id bigint NOT NULL,
    name text,
    description text,
    max_workers integer DEFAULT 10,
    queue_name text DEFAULT 'default'::character varying,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: task_queue_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_queue_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_queue_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_queue_types_id_seq OWNED BY public.task_queue_types.id;


--
-- Name: task_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_runs (
    id bigint NOT NULL,
    task_definition_id bigint,
    workflow_run_id bigint,
    workflow_node_id bigint,
    status text DEFAULT 'Pending'::character varying,
    name text,
    step text DEFAULT 'Initial'::character varying,
    percent_complete numeric(5,2) DEFAULT 0.00,
    started_at timestamp with time zone,
    stopped_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    skipped_at timestamp with time zone,
    process_count bigint DEFAULT '0'::bigint,
    input_artifacts_count bigint DEFAULT '0'::bigint,
    output_artifacts_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    task_input_id bigint
);


--
-- Name: task_runs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_runs_id_seq OWNED BY public.task_runs.id;


--
-- Name: team_object_attribute_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_object_attribute_sources (
    id bigint NOT NULL,
    team_object_attribute_id bigint,
    source_type text,
    source_id text,
    explanation text,
    stored_file_id character(36) DEFAULT NULL::bpchar,
    agent_thread_message_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_object_attribute_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_object_attribute_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_object_attribute_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_object_attribute_sources_id_seq OWNED BY public.team_object_attribute_sources.id;


--
-- Name: team_object_attributes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_object_attributes (
    id bigint NOT NULL,
    team_object_id bigint,
    name text,
    text_value text,
    json_value json,
    reason text,
    confidence text,
    agent_thread_run_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_object_attributes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_object_attributes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_object_attributes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_object_attributes_id_seq OWNED BY public.team_object_attributes.id;


--
-- Name: team_object_relationships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_object_relationships (
    id bigint NOT NULL,
    team_object_id bigint,
    related_team_object_id bigint,
    relationship_name text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_object_relationships_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_object_relationships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_object_relationships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_object_relationships_id_seq OWNED BY public.team_object_relationships.id;


--
-- Name: team_objects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_objects (
    id bigint NOT NULL,
    team_id bigint,
    schema_definition_id bigint,
    root_object_id bigint,
    type text,
    name text,
    date timestamp with time zone,
    description text,
    url text,
    meta json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: team_objects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_objects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_objects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_objects_id_seq OWNED BY public.team_objects.id;


--
-- Name: team_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_user (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: team_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_user_id_seq OWNED BY public.team_user.id;


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams (
    id bigint NOT NULL,
    uuid character(36) DEFAULT NULL::bpchar,
    name text,
    namespace text,
    logo text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    stripe_customer_id text
);


--
-- Name: teams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.teams_id_seq OWNED BY public.teams.id;


--
-- Name: ui_demand_workflow_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ui_demand_workflow_runs (
    id bigint NOT NULL,
    ui_demand_id bigint,
    workflow_run_id bigint,
    workflow_type text,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: ui_demand_workflow_runs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ui_demand_workflow_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ui_demand_workflow_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ui_demand_workflow_runs_id_seq OWNED BY public.ui_demand_workflow_runs.id;


--
-- Name: ui_demands; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ui_demands (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    title text,
    description text,
    status text DEFAULT 'draft'::character varying,
    metadata json,
    completed_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    deleted_at timestamp without time zone,
    team_object_id bigint,
    workflow_run_id bigint
);


--
-- Name: ui_demands_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ui_demands_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ui_demands_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ui_demands_id_seq OWNED BY public.ui_demands.id;


--
-- Name: usage_event_subscribers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usage_event_subscribers (
    id bigint NOT NULL,
    usage_event_id bigint,
    subscriber_type text,
    subscriber_id text,
    subscriber_id_int bigint,
    subscribed_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: usage_event_subscribers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usage_event_subscribers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usage_event_subscribers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usage_event_subscribers_id_seq OWNED BY public.usage_event_subscribers.id;


--
-- Name: usage_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usage_events (
    id bigint NOT NULL,
    team_id bigint,
    user_id bigint,
    object_type text,
    object_id text,
    event_type text,
    run_time_ms bigint DEFAULT '0'::bigint,
    input_tokens bigint DEFAULT '0'::bigint,
    output_tokens bigint DEFAULT '0'::bigint,
    input_cost numeric(12,4) DEFAULT 0.0000,
    output_cost numeric(12,4) DEFAULT 0.0000,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    api_name text,
    request_count bigint DEFAULT '0'::bigint,
    data_volume bigint DEFAULT '0'::bigint,
    metadata json,
    object_id_int bigint
);


--
-- Name: usage_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usage_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usage_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usage_events_id_seq OWNED BY public.usage_events.id;


--
-- Name: usage_summaries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usage_summaries (
    id bigint NOT NULL,
    object_type text,
    object_id text,
    count bigint DEFAULT '1'::bigint,
    run_time_ms bigint DEFAULT '0'::bigint,
    input_tokens bigint DEFAULT '0'::bigint,
    output_tokens bigint DEFAULT '0'::bigint,
    input_cost numeric(12,4) DEFAULT 0.0000,
    output_cost numeric(12,4) DEFAULT 0.0000,
    total_cost numeric(12,4) DEFAULT NULL::numeric,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    request_count bigint DEFAULT '0'::bigint,
    data_volume bigint DEFAULT '0'::bigint,
    object_id_int bigint
);


--
-- Name: usage_summaries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usage_summaries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usage_summaries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usage_summaries_id_seq OWNED BY public.usage_summaries.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name text,
    email text,
    email_verified_at timestamp with time zone,
    password text,
    remember_token text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: workflow_api_invocations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_api_invocations (
    id bigint NOT NULL,
    workflow_run_id bigint,
    name text,
    webhook_url text,
    payload json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: workflow_api_invocations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_api_invocations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_api_invocations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_api_invocations_id_seq OWNED BY public.workflow_api_invocations.id;


--
-- Name: workflow_connections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_connections (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    workflow_definition_id bigint,
    source_node_id bigint,
    target_node_id bigint,
    source_output_port text,
    target_input_port text,
    name text DEFAULT ''::character varying,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: workflow_connections_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_connections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_connections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_connections_id_seq OWNED BY public.workflow_connections.id;


--
-- Name: workflow_definitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_definitions (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    team_id bigint,
    name text,
    description text DEFAULT ''::character varying,
    workflow_runs_count bigint DEFAULT '0'::bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    max_workers integer DEFAULT 20
);


--
-- Name: workflow_definitions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_definitions_id_seq OWNED BY public.workflow_definitions.id;


--
-- Name: workflow_input_associations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_input_associations (
    id bigint NOT NULL,
    workflow_input_id bigint,
    associable_type text,
    associable_id bigint,
    category text DEFAULT 'write_demand_instructions'::character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: workflow_input_associations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_input_associations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_input_associations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_input_associations_id_seq OWNED BY public.workflow_input_associations.id;


--
-- Name: workflow_inputs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_inputs (
    id bigint NOT NULL,
    content_source_id bigint,
    team_id bigint,
    user_id bigint,
    name text,
    description text,
    content text,
    data json,
    tokens bigint DEFAULT '0'::bigint,
    is_url boolean DEFAULT false,
    team_object_id bigint,
    team_object_type text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: workflow_inputs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_inputs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_inputs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_inputs_id_seq OWNED BY public.workflow_inputs.id;


--
-- Name: workflow_listeners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_listeners (
    id bigint NOT NULL,
    team_id bigint,
    workflow_run_id bigint,
    listener_type text,
    listener_id bigint,
    workflow_type text,
    status text DEFAULT 'pending'::character varying,
    metadata json,
    started_at timestamp without time zone,
    completed_at timestamp without time zone,
    failed_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: workflow_listeners_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_listeners_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_listeners_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_listeners_id_seq OWNED BY public.workflow_listeners.id;


--
-- Name: workflow_nodes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_nodes (
    id bigint NOT NULL,
    resource_package_import_id character(36) DEFAULT NULL::bpchar,
    workflow_definition_id bigint,
    task_definition_id bigint,
    name text,
    settings json,
    params json,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: workflow_nodes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_nodes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_nodes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_nodes_id_seq OWNED BY public.workflow_nodes.id;


--
-- Name: workflow_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_runs (
    id bigint NOT NULL,
    workflow_definition_id bigint,
    name text DEFAULT ''::character varying,
    status text DEFAULT 'Pending'::character varying,
    started_at timestamp with time zone,
    stopped_at timestamp with time zone,
    completed_at timestamp with time zone,
    failed_at timestamp with time zone,
    has_run_all_tasks boolean DEFAULT false,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    active_workers_count integer DEFAULT 0
);


--
-- Name: workflow_runs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_runs_id_seq OWNED BY public.workflow_runs.id;


--
-- Name: agent_prompt_directives id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.agent_prompt_directives ALTER COLUMN id SET DEFAULT nextval('gpt_manager.agent_prompt_directives_id_seq'::regclass);


--
-- Name: agent_thread_messageables id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.agent_thread_messageables ALTER COLUMN id SET DEFAULT nextval('gpt_manager.agent_thread_messageables_id_seq'::regclass);


--
-- Name: agent_thread_messages id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.agent_thread_messages ALTER COLUMN id SET DEFAULT nextval('gpt_manager.agent_thread_messages_id_seq'::regclass);


--
-- Name: agent_thread_runs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.agent_thread_runs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.agent_thread_runs_id_seq'::regclass);


--
-- Name: agent_threads id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.agent_threads ALTER COLUMN id SET DEFAULT nextval('gpt_manager.agent_threads_id_seq'::regclass);


--
-- Name: agents id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.agents ALTER COLUMN id SET DEFAULT nextval('gpt_manager.agents_id_seq'::regclass);


--
-- Name: api_logs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.api_logs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.api_logs_id_seq'::regclass);


--
-- Name: artifactables id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.artifactables ALTER COLUMN id SET DEFAULT nextval('gpt_manager.artifactables_id_seq'::regclass);


--
-- Name: artifacts id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.artifacts ALTER COLUMN id SET DEFAULT nextval('gpt_manager.artifacts_id_seq'::regclass);


--
-- Name: audit_request id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.audit_request ALTER COLUMN id SET DEFAULT nextval('gpt_manager.audit_request_id_seq'::regclass);


--
-- Name: audits id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.audits ALTER COLUMN id SET DEFAULT nextval('gpt_manager.audits_id_seq'::regclass);


--
-- Name: content_sources id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.content_sources ALTER COLUMN id SET DEFAULT nextval('gpt_manager.content_sources_id_seq'::regclass);


--
-- Name: error_log_entry id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.error_log_entry ALTER COLUMN id SET DEFAULT nextval('gpt_manager.error_log_entry_id_seq'::regclass);


--
-- Name: error_logs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.error_logs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.error_logs_id_seq'::regclass);


--
-- Name: job_batches id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.job_batches ALTER COLUMN id SET DEFAULT nextval('gpt_manager.job_batches_id_seq'::regclass);


--
-- Name: job_dispatch id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.job_dispatch ALTER COLUMN id SET DEFAULT nextval('gpt_manager.job_dispatch_id_seq'::regclass);


--
-- Name: job_dispatchables id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.job_dispatchables ALTER COLUMN id SET DEFAULT nextval('gpt_manager.job_dispatchables_id_seq'::regclass);


--
-- Name: knowledge id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.knowledge ALTER COLUMN id SET DEFAULT nextval('gpt_manager.knowledge_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.migrations ALTER COLUMN id SET DEFAULT nextval('gpt_manager.migrations_id_seq'::regclass);


--
-- Name: model_refs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.model_refs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.model_refs_id_seq'::regclass);


--
-- Name: object_tag_taggables id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.object_tag_taggables ALTER COLUMN id SET DEFAULT nextval('gpt_manager.object_tag_taggables_id_seq'::regclass);


--
-- Name: object_tags id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.object_tags ALTER COLUMN id SET DEFAULT nextval('gpt_manager.object_tags_id_seq'::regclass);


--
-- Name: on_demands__object_attribute_sources id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.on_demands__object_attribute_sources ALTER COLUMN id SET DEFAULT nextval('gpt_manager.on_demands__object_attribute_sources_id_seq'::regclass);


--
-- Name: on_demands__object_attributes id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.on_demands__object_attributes ALTER COLUMN id SET DEFAULT nextval('gpt_manager.on_demands__object_attributes_id_seq'::regclass);


--
-- Name: on_demands__object_relationships id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.on_demands__object_relationships ALTER COLUMN id SET DEFAULT nextval('gpt_manager.on_demands__object_relationships_id_seq'::regclass);


--
-- Name: on_demands__objects id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.on_demands__objects ALTER COLUMN id SET DEFAULT nextval('gpt_manager.on_demands__objects_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.permissions ALTER COLUMN id SET DEFAULT nextval('gpt_manager.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('gpt_manager.personal_access_tokens_id_seq'::regclass);


--
-- Name: prompt_directives id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.prompt_directives ALTER COLUMN id SET DEFAULT nextval('gpt_manager.prompt_directives_id_seq'::regclass);


--
-- Name: role_permission id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.role_permission ALTER COLUMN id SET DEFAULT nextval('gpt_manager.role_permission_id_seq'::regclass);


--
-- Name: role_user id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.role_user ALTER COLUMN id SET DEFAULT nextval('gpt_manager.role_user_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.roles ALTER COLUMN id SET DEFAULT nextval('gpt_manager.roles_id_seq'::regclass);


--
-- Name: schema_associations id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.schema_associations ALTER COLUMN id SET DEFAULT nextval('gpt_manager.schema_associations_id_seq'::regclass);


--
-- Name: schema_definitions id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.schema_definitions ALTER COLUMN id SET DEFAULT nextval('gpt_manager.schema_definitions_id_seq'::regclass);


--
-- Name: schema_fragments id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.schema_fragments ALTER COLUMN id SET DEFAULT nextval('gpt_manager.schema_fragments_id_seq'::regclass);


--
-- Name: schema_history id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.schema_history ALTER COLUMN id SET DEFAULT nextval('gpt_manager.schema_history_id_seq'::regclass);


--
-- Name: stored_file_storables id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.stored_file_storables ALTER COLUMN id SET DEFAULT nextval('gpt_manager.stored_file_storables_id_seq'::regclass);


--
-- Name: task_artifact_filters id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.task_artifact_filters ALTER COLUMN id SET DEFAULT nextval('gpt_manager.task_artifact_filters_id_seq'::regclass);


--
-- Name: task_definition_directives id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.task_definition_directives ALTER COLUMN id SET DEFAULT nextval('gpt_manager.task_definition_directives_id_seq'::regclass);


--
-- Name: task_definitions id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.task_definitions ALTER COLUMN id SET DEFAULT nextval('gpt_manager.task_definitions_id_seq'::regclass);


--
-- Name: task_inputs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.task_inputs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.task_inputs_id_seq'::regclass);


--
-- Name: task_process_listeners id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.task_process_listeners ALTER COLUMN id SET DEFAULT nextval('gpt_manager.task_process_listeners_id_seq'::regclass);


--
-- Name: task_processes id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.task_processes ALTER COLUMN id SET DEFAULT nextval('gpt_manager.task_processes_id_seq'::regclass);


--
-- Name: task_runs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.task_runs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.task_runs_id_seq'::regclass);


--
-- Name: team_object_attribute_sources id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.team_object_attribute_sources ALTER COLUMN id SET DEFAULT nextval('gpt_manager.team_object_attribute_sources_id_seq'::regclass);


--
-- Name: team_object_attributes id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.team_object_attributes ALTER COLUMN id SET DEFAULT nextval('gpt_manager.team_object_attributes_id_seq'::regclass);


--
-- Name: team_object_relationships id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.team_object_relationships ALTER COLUMN id SET DEFAULT nextval('gpt_manager.team_object_relationships_id_seq'::regclass);


--
-- Name: team_objects id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.team_objects ALTER COLUMN id SET DEFAULT nextval('gpt_manager.team_objects_id_seq'::regclass);


--
-- Name: team_user id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.team_user ALTER COLUMN id SET DEFAULT nextval('gpt_manager.team_user_id_seq'::regclass);


--
-- Name: teams id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.teams ALTER COLUMN id SET DEFAULT nextval('gpt_manager.teams_id_seq'::regclass);


--
-- Name: tortguard__object_attributes id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.tortguard__object_attributes ALTER COLUMN id SET DEFAULT nextval('gpt_manager.tortguard__object_attributes_id_seq'::regclass);


--
-- Name: tortguard__object_relationships id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.tortguard__object_relationships ALTER COLUMN id SET DEFAULT nextval('gpt_manager.tortguard__object_relationships_id_seq'::regclass);


--
-- Name: tortguard__objects id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.tortguard__objects ALTER COLUMN id SET DEFAULT nextval('gpt_manager.tortguard__objects_id_seq'::regclass);


--
-- Name: usage_events id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.usage_events ALTER COLUMN id SET DEFAULT nextval('gpt_manager.usage_events_id_seq'::regclass);


--
-- Name: usage_summaries id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.usage_summaries ALTER COLUMN id SET DEFAULT nextval('gpt_manager.usage_summaries_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.users ALTER COLUMN id SET DEFAULT nextval('gpt_manager.users_id_seq'::regclass);


--
-- Name: workflow_api_invocations id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.workflow_api_invocations ALTER COLUMN id SET DEFAULT nextval('gpt_manager.workflow_api_invocations_id_seq'::regclass);


--
-- Name: workflow_connections id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.workflow_connections ALTER COLUMN id SET DEFAULT nextval('gpt_manager.workflow_connections_id_seq'::regclass);


--
-- Name: workflow_definitions id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.workflow_definitions ALTER COLUMN id SET DEFAULT nextval('gpt_manager.workflow_definitions_id_seq'::regclass);


--
-- Name: workflow_inputs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.workflow_inputs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.workflow_inputs_id_seq'::regclass);


--
-- Name: workflow_nodes id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.workflow_nodes ALTER COLUMN id SET DEFAULT nextval('gpt_manager.workflow_nodes_id_seq'::regclass);


--
-- Name: workflow_runs id; Type: DEFAULT; Schema: gpt_manager; Owner: -
--

ALTER TABLE ONLY gpt_manager.workflow_runs ALTER COLUMN id SET DEFAULT nextval('gpt_manager.workflow_runs_id_seq'::regclass);


--
-- Name: agent_prompt_directives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_prompt_directives ALTER COLUMN id SET DEFAULT nextval('public.agent_prompt_directives_id_seq'::regclass);


--
-- Name: agent_thread_messageables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_thread_messageables ALTER COLUMN id SET DEFAULT nextval('public.agent_thread_messageables_id_seq'::regclass);


--
-- Name: agent_thread_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_thread_messages ALTER COLUMN id SET DEFAULT nextval('public.agent_thread_messages_id_seq'::regclass);


--
-- Name: agent_thread_runs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_thread_runs ALTER COLUMN id SET DEFAULT nextval('public.agent_thread_runs_id_seq'::regclass);


--
-- Name: agent_threads id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_threads ALTER COLUMN id SET DEFAULT nextval('public.agent_threads_id_seq'::regclass);


--
-- Name: agents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agents ALTER COLUMN id SET DEFAULT nextval('public.agents_id_seq'::regclass);


--
-- Name: api_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_logs ALTER COLUMN id SET DEFAULT nextval('public.api_logs_id_seq'::regclass);


--
-- Name: artifactables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artifactables ALTER COLUMN id SET DEFAULT nextval('public.artifactables_id_seq'::regclass);


--
-- Name: artifacts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artifacts ALTER COLUMN id SET DEFAULT nextval('public.artifacts_id_seq'::regclass);


--
-- Name: assistant_actions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assistant_actions ALTER COLUMN id SET DEFAULT nextval('public.assistant_actions_id_seq'::regclass);


--
-- Name: audit_request id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_request ALTER COLUMN id SET DEFAULT nextval('public.audit_request_id_seq'::regclass);


--
-- Name: audits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audits ALTER COLUMN id SET DEFAULT nextval('public.audits_id_seq'::regclass);


--
-- Name: auth_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auth_tokens ALTER COLUMN id SET DEFAULT nextval('public.auth_tokens_id_seq'::regclass);


--
-- Name: billing_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_history ALTER COLUMN id SET DEFAULT nextval('public.billing_history_id_seq'::regclass);


--
-- Name: content_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_sources ALTER COLUMN id SET DEFAULT nextval('public.content_sources_id_seq'::regclass);


--
-- Name: demand_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demand_templates ALTER COLUMN id SET DEFAULT nextval('public.demand_templates_id_seq'::regclass);


--
-- Name: error_log_entry id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.error_log_entry ALTER COLUMN id SET DEFAULT nextval('public.error_log_entry_id_seq'::regclass);


--
-- Name: error_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.error_logs ALTER COLUMN id SET DEFAULT nextval('public.error_logs_id_seq'::regclass);


--
-- Name: job_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches ALTER COLUMN id SET DEFAULT nextval('public.job_batches_id_seq'::regclass);


--
-- Name: job_dispatch id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_dispatch ALTER COLUMN id SET DEFAULT nextval('public.job_dispatch_id_seq'::regclass);


--
-- Name: job_dispatchables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_dispatchables ALTER COLUMN id SET DEFAULT nextval('public.job_dispatchables_id_seq'::regclass);


--
-- Name: knowledge id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.knowledge ALTER COLUMN id SET DEFAULT nextval('public.knowledge_id_seq'::regclass);


--
-- Name: mcp_servers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_servers ALTER COLUMN id SET DEFAULT nextval('public.mcp_servers_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: model_refs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_refs ALTER COLUMN id SET DEFAULT nextval('public.model_refs_id_seq'::regclass);


--
-- Name: object_tag_taggables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.object_tag_taggables ALTER COLUMN id SET DEFAULT nextval('public.object_tag_taggables_id_seq'::regclass);


--
-- Name: object_tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.object_tags ALTER COLUMN id SET DEFAULT nextval('public.object_tags_id_seq'::regclass);


--
-- Name: payment_methods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_methods ALTER COLUMN id SET DEFAULT nextval('public.payment_methods_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: prompt_directives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prompt_directives ALTER COLUMN id SET DEFAULT nextval('public.prompt_directives_id_seq'::regclass);


--
-- Name: role_permission id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_permission ALTER COLUMN id SET DEFAULT nextval('public.role_permission_id_seq'::regclass);


--
-- Name: role_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_user ALTER COLUMN id SET DEFAULT nextval('public.role_user_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: schema_associations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_associations ALTER COLUMN id SET DEFAULT nextval('public.schema_associations_id_seq'::regclass);


--
-- Name: schema_definitions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_definitions ALTER COLUMN id SET DEFAULT nextval('public.schema_definitions_id_seq'::regclass);


--
-- Name: schema_fragments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_fragments ALTER COLUMN id SET DEFAULT nextval('public.schema_fragments_id_seq'::regclass);


--
-- Name: schema_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_history ALTER COLUMN id SET DEFAULT nextval('public.schema_history_id_seq'::regclass);


--
-- Name: stored_file_storables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_file_storables ALTER COLUMN id SET DEFAULT nextval('public.stored_file_storables_id_seq'::regclass);


--
-- Name: subscription_plans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_plans ALTER COLUMN id SET DEFAULT nextval('public.subscription_plans_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: task_artifact_filters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_artifact_filters ALTER COLUMN id SET DEFAULT nextval('public.task_artifact_filters_id_seq'::regclass);


--
-- Name: task_definition_directives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_definition_directives ALTER COLUMN id SET DEFAULT nextval('public.task_definition_directives_id_seq'::regclass);


--
-- Name: task_definitions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_definitions ALTER COLUMN id SET DEFAULT nextval('public.task_definitions_id_seq'::regclass);


--
-- Name: task_inputs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_inputs ALTER COLUMN id SET DEFAULT nextval('public.task_inputs_id_seq'::regclass);


--
-- Name: task_process_listeners id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_process_listeners ALTER COLUMN id SET DEFAULT nextval('public.task_process_listeners_id_seq'::regclass);


--
-- Name: task_processes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_processes ALTER COLUMN id SET DEFAULT nextval('public.task_processes_id_seq'::regclass);


--
-- Name: task_queue_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_queue_types ALTER COLUMN id SET DEFAULT nextval('public.task_queue_types_id_seq'::regclass);


--
-- Name: task_runs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_runs ALTER COLUMN id SET DEFAULT nextval('public.task_runs_id_seq'::regclass);


--
-- Name: team_object_attribute_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_object_attribute_sources ALTER COLUMN id SET DEFAULT nextval('public.team_object_attribute_sources_id_seq'::regclass);


--
-- Name: team_object_attributes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_object_attributes ALTER COLUMN id SET DEFAULT nextval('public.team_object_attributes_id_seq'::regclass);


--
-- Name: team_object_relationships id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_object_relationships ALTER COLUMN id SET DEFAULT nextval('public.team_object_relationships_id_seq'::regclass);


--
-- Name: team_objects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_objects ALTER COLUMN id SET DEFAULT nextval('public.team_objects_id_seq'::regclass);


--
-- Name: team_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_user ALTER COLUMN id SET DEFAULT nextval('public.team_user_id_seq'::regclass);


--
-- Name: teams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams ALTER COLUMN id SET DEFAULT nextval('public.teams_id_seq'::regclass);


--
-- Name: ui_demand_workflow_runs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demand_workflow_runs ALTER COLUMN id SET DEFAULT nextval('public.ui_demand_workflow_runs_id_seq'::regclass);


--
-- Name: ui_demands id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demands ALTER COLUMN id SET DEFAULT nextval('public.ui_demands_id_seq'::regclass);


--
-- Name: usage_event_subscribers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_event_subscribers ALTER COLUMN id SET DEFAULT nextval('public.usage_event_subscribers_id_seq'::regclass);


--
-- Name: usage_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_events ALTER COLUMN id SET DEFAULT nextval('public.usage_events_id_seq'::regclass);


--
-- Name: usage_summaries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_summaries ALTER COLUMN id SET DEFAULT nextval('public.usage_summaries_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: workflow_api_invocations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_api_invocations ALTER COLUMN id SET DEFAULT nextval('public.workflow_api_invocations_id_seq'::regclass);


--
-- Name: workflow_connections id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_connections ALTER COLUMN id SET DEFAULT nextval('public.workflow_connections_id_seq'::regclass);


--
-- Name: workflow_definitions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_definitions ALTER COLUMN id SET DEFAULT nextval('public.workflow_definitions_id_seq'::regclass);


--
-- Name: workflow_input_associations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_input_associations ALTER COLUMN id SET DEFAULT nextval('public.workflow_input_associations_id_seq'::regclass);


--
-- Name: workflow_inputs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_inputs ALTER COLUMN id SET DEFAULT nextval('public.workflow_inputs_id_seq'::regclass);


--
-- Name: workflow_listeners id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_listeners ALTER COLUMN id SET DEFAULT nextval('public.workflow_listeners_id_seq'::regclass);


--
-- Name: workflow_nodes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_nodes ALTER COLUMN id SET DEFAULT nextval('public.workflow_nodes_id_seq'::regclass);


--
-- Name: workflow_runs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_runs ALTER COLUMN id SET DEFAULT nextval('public.workflow_runs_id_seq'::regclass);


--
-- Name: assistant_actions assistant_actions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assistant_actions
    ADD CONSTRAINT assistant_actions_pkey PRIMARY KEY (id);


--
-- Name: auth_tokens auth_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auth_tokens
    ADD CONSTRAINT auth_tokens_pkey PRIMARY KEY (id);


--
-- Name: auth_tokens auth_tokens_team_id_service_type_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auth_tokens
    ADD CONSTRAINT auth_tokens_team_id_service_type_name_unique UNIQUE (team_id, service, type, name);


--
-- Name: billing_history billing_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_history
    ADD CONSTRAINT billing_history_pkey PRIMARY KEY (id);


--
-- Name: demand_templates demand_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demand_templates
    ADD CONSTRAINT demand_templates_pkey PRIMARY KEY (id);


--
-- Name: agent_prompt_directives idx_27661_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_prompt_directives
    ADD CONSTRAINT idx_27661_primary PRIMARY KEY (id);


--
-- Name: agent_thread_messageables idx_27668_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_thread_messageables
    ADD CONSTRAINT idx_27668_primary PRIMARY KEY (id);


--
-- Name: agent_thread_messages idx_27673_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_thread_messages
    ADD CONSTRAINT idx_27673_primary PRIMARY KEY (id);


--
-- Name: agent_thread_runs idx_27682_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_thread_runs
    ADD CONSTRAINT idx_27682_primary PRIMARY KEY (id);


--
-- Name: agent_threads idx_27693_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_threads
    ADD CONSTRAINT idx_27693_primary PRIMARY KEY (id);


--
-- Name: agents idx_27701_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT idx_27701_primary PRIMARY KEY (id);


--
-- Name: api_logs idx_27712_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_logs
    ADD CONSTRAINT idx_27712_primary PRIMARY KEY (id);


--
-- Name: artifactables idx_27719_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artifactables
    ADD CONSTRAINT idx_27719_primary PRIMARY KEY (id);


--
-- Name: artifacts idx_27725_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artifacts
    ADD CONSTRAINT idx_27725_primary PRIMARY KEY (id);


--
-- Name: audits idx_27741_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audits
    ADD CONSTRAINT idx_27741_primary PRIMARY KEY (id);


--
-- Name: cache idx_27747_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT idx_27747_primary PRIMARY KEY (key);


--
-- Name: cache_locks idx_27752_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT idx_27752_primary PRIMARY KEY (key);


--
-- Name: content_sources idx_27756_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_sources
    ADD CONSTRAINT idx_27756_primary PRIMARY KEY (id);


--
-- Name: error_log_entry idx_27766_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.error_log_entry
    ADD CONSTRAINT idx_27766_primary PRIMARY KEY (id);


--
-- Name: error_logs idx_27774_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.error_logs
    ADD CONSTRAINT idx_27774_primary PRIMARY KEY (id);


--
-- Name: job_batches idx_27782_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT idx_27782_primary PRIMARY KEY (id);


--
-- Name: job_dispatch idx_27789_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_dispatch
    ADD CONSTRAINT idx_27789_primary PRIMARY KEY (id);


--
-- Name: job_dispatchables idx_27796_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_dispatchables
    ADD CONSTRAINT idx_27796_primary PRIMARY KEY (id);


--
-- Name: knowledge idx_27802_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.knowledge
    ADD CONSTRAINT idx_27802_primary PRIMARY KEY (id);


--
-- Name: migrations idx_27809_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT idx_27809_primary PRIMARY KEY (id);


--
-- Name: model_refs idx_27814_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_refs
    ADD CONSTRAINT idx_27814_primary PRIMARY KEY (id);


--
-- Name: object_tag_taggables idx_27819_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.object_tag_taggables
    ADD CONSTRAINT idx_27819_primary PRIMARY KEY (id);


--
-- Name: object_tags idx_27824_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.object_tags
    ADD CONSTRAINT idx_27824_primary PRIMARY KEY (id);


--
-- Name: password_reset_tokens idx_27854_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT idx_27854_primary PRIMARY KEY (email);


--
-- Name: permissions idx_27858_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT idx_27858_primary PRIMARY KEY (id);


--
-- Name: personal_access_tokens idx_27865_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT idx_27865_primary PRIMARY KEY (id);


--
-- Name: prompt_directives idx_27872_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prompt_directives
    ADD CONSTRAINT idx_27872_primary PRIMARY KEY (id);


--
-- Name: resource_package_imports idx_27879_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_package_imports
    ADD CONSTRAINT idx_27879_primary PRIMARY KEY (id);


--
-- Name: resource_package_versions idx_27884_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_package_versions
    ADD CONSTRAINT idx_27884_primary PRIMARY KEY (id);


--
-- Name: resource_packages idx_27889_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_packages
    ADD CONSTRAINT idx_27889_primary PRIMARY KEY (id);


--
-- Name: role_permission idx_27895_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_permission
    ADD CONSTRAINT idx_27895_primary PRIMARY KEY (id);


--
-- Name: role_user idx_27900_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT idx_27900_primary PRIMARY KEY (id);


--
-- Name: roles idx_27905_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT idx_27905_primary PRIMARY KEY (id);


--
-- Name: schema_associations idx_27912_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_associations
    ADD CONSTRAINT idx_27912_primary PRIMARY KEY (id);


--
-- Name: schema_definitions idx_27918_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_definitions
    ADD CONSTRAINT idx_27918_primary PRIMARY KEY (id);


--
-- Name: schema_fragments idx_27927_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_fragments
    ADD CONSTRAINT idx_27927_primary PRIMARY KEY (id);


--
-- Name: schema_history idx_27935_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_history
    ADD CONSTRAINT idx_27935_primary PRIMARY KEY (id);


--
-- Name: sessions idx_27941_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT idx_27941_primary PRIMARY KEY (id);


--
-- Name: stored_file_storables idx_27947_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_file_storables
    ADD CONSTRAINT idx_27947_primary PRIMARY KEY (id);


--
-- Name: stored_files idx_27951_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_files
    ADD CONSTRAINT idx_27951_primary PRIMARY KEY (id);


--
-- Name: task_artifact_filters idx_27959_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_artifact_filters
    ADD CONSTRAINT idx_27959_primary PRIMARY KEY (id);


--
-- Name: task_definition_directives idx_27969_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_definition_directives
    ADD CONSTRAINT idx_27969_primary PRIMARY KEY (id);


--
-- Name: task_definitions idx_27976_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_definitions
    ADD CONSTRAINT idx_27976_primary PRIMARY KEY (id);


--
-- Name: task_inputs idx_27989_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_inputs
    ADD CONSTRAINT idx_27989_primary PRIMARY KEY (id);


--
-- Name: task_process_listeners idx_27995_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_process_listeners
    ADD CONSTRAINT idx_27995_primary PRIMARY KEY (id);


--
-- Name: task_processes idx_28000_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_processes
    ADD CONSTRAINT idx_28000_primary PRIMARY KEY (id);


--
-- Name: task_runs idx_28013_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_runs
    ADD CONSTRAINT idx_28013_primary PRIMARY KEY (id);


--
-- Name: team_object_attribute_sources idx_28026_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_object_attribute_sources
    ADD CONSTRAINT idx_28026_primary PRIMARY KEY (id);


--
-- Name: team_object_attributes idx_28033_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_object_attributes
    ADD CONSTRAINT idx_28033_primary PRIMARY KEY (id);


--
-- Name: team_object_relationships idx_28040_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_object_relationships
    ADD CONSTRAINT idx_28040_primary PRIMARY KEY (id);


--
-- Name: team_objects idx_28045_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_objects
    ADD CONSTRAINT idx_28045_primary PRIMARY KEY (id);


--
-- Name: team_user idx_28052_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_user
    ADD CONSTRAINT idx_28052_primary PRIMARY KEY (id);


--
-- Name: teams idx_28057_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT idx_28057_primary PRIMARY KEY (id);


--
-- Name: usage_events idx_28083_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_events
    ADD CONSTRAINT idx_28083_primary PRIMARY KEY (id);


--
-- Name: usage_summaries idx_28093_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_summaries
    ADD CONSTRAINT idx_28093_primary PRIMARY KEY (id);


--
-- Name: users idx_28104_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT idx_28104_primary PRIMARY KEY (id);


--
-- Name: workflow_api_invocations idx_28111_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_api_invocations
    ADD CONSTRAINT idx_28111_primary PRIMARY KEY (id);


--
-- Name: workflow_connections idx_28118_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_connections
    ADD CONSTRAINT idx_28118_primary PRIMARY KEY (id);


--
-- Name: workflow_definitions idx_28126_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_definitions
    ADD CONSTRAINT idx_28126_primary PRIMARY KEY (id);


--
-- Name: workflow_inputs idx_28133_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_inputs
    ADD CONSTRAINT idx_28133_primary PRIMARY KEY (id);


--
-- Name: workflow_nodes idx_28142_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_nodes
    ADD CONSTRAINT idx_28142_primary PRIMARY KEY (id);


--
-- Name: workflow_runs idx_28149_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_runs
    ADD CONSTRAINT idx_28149_primary PRIMARY KEY (id);


--
-- Name: mcp_servers mcp_servers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_servers
    ADD CONSTRAINT mcp_servers_pkey PRIMARY KEY (id);


--
-- Name: payment_methods payment_methods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_methods
    ADD CONSTRAINT payment_methods_pkey PRIMARY KEY (id);


--
-- Name: subscription_plans subscription_plans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_plans
    ADD CONSTRAINT subscription_plans_pkey PRIMARY KEY (id);


--
-- Name: subscription_plans subscription_plans_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_plans
    ADD CONSTRAINT subscription_plans_slug_unique UNIQUE (slug);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: task_queue_types task_queue_types_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_queue_types
    ADD CONSTRAINT task_queue_types_name_unique UNIQUE (name);


--
-- Name: task_queue_types task_queue_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_queue_types
    ADD CONSTRAINT task_queue_types_pkey PRIMARY KEY (id);


--
-- Name: ui_demand_workflow_runs ui_demand_workflow_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demand_workflow_runs
    ADD CONSTRAINT ui_demand_workflow_runs_pkey PRIMARY KEY (id);


--
-- Name: ui_demand_workflow_runs ui_demand_workflow_runs_ui_demand_id_workflow_run_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demand_workflow_runs
    ADD CONSTRAINT ui_demand_workflow_runs_ui_demand_id_workflow_run_id_unique UNIQUE (ui_demand_id, workflow_run_id);


--
-- Name: ui_demands ui_demands_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demands
    ADD CONSTRAINT ui_demands_pkey PRIMARY KEY (id);


--
-- Name: usage_event_subscribers usage_event_subscriber_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_event_subscribers
    ADD CONSTRAINT usage_event_subscriber_unique UNIQUE (usage_event_id, subscriber_type, subscriber_id_int);


--
-- Name: usage_event_subscribers usage_event_subscribers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_event_subscribers
    ADD CONSTRAINT usage_event_subscribers_pkey PRIMARY KEY (id);


--
-- Name: workflow_input_associations workflow_input_associations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_input_associations
    ADD CONSTRAINT workflow_input_associations_pkey PRIMARY KEY (id);


--
-- Name: workflow_listeners workflow_listener_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_listeners
    ADD CONSTRAINT workflow_listener_unique UNIQUE (workflow_run_id, listener_type, listener_id, workflow_type);


--
-- Name: workflow_listeners workflow_listeners_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_listeners
    ADD CONSTRAINT workflow_listeners_pkey PRIMARY KEY (id);


--
-- Name: idx_16564_audit_request_environment_index; Type: INDEX; Schema: gpt_manager; Owner: -
--

CREATE INDEX idx_16564_audit_request_environment_index ON gpt_manager.audit_request USING btree (environment);


--
-- Name: idx_16564_audit_request_session_id_index; Type: INDEX; Schema: gpt_manager; Owner: -
--

CREATE INDEX idx_16564_audit_request_session_id_index ON gpt_manager.audit_request USING btree (session_id);


--
-- Name: idx_16564_audit_request_url_index; Type: INDEX; Schema: gpt_manager; Owner: -
--

CREATE INDEX idx_16564_audit_request_url_index ON gpt_manager.audit_request USING btree (url);


--
-- Name: idx_16564_audit_request_user_id_index; Type: INDEX; Schema: gpt_manager; Owner: -
--

CREATE INDEX idx_16564_audit_request_user_id_index ON gpt_manager.audit_request USING btree (user_id);


--
-- Name: idx_16564_primary; Type: INDEX; Schema: gpt_manager; Owner: -
--

CREATE UNIQUE INDEX idx_16564_primary ON gpt_manager.audit_request USING btree (id);


--
-- Name: agent_thread_messages_api_response_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_thread_messages_api_response_id_index ON public.agent_thread_messages USING btree (api_response_id);


--
-- Name: assistant_actions_agent_thread_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX assistant_actions_agent_thread_id_status_index ON public.assistant_actions USING btree (agent_thread_id, status);


--
-- Name: assistant_actions_target_type_target_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX assistant_actions_target_type_target_id_index ON public.assistant_actions USING btree (target_type, target_id);


--
-- Name: assistant_actions_team_id_context_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX assistant_actions_team_id_context_index ON public.assistant_actions USING btree (team_id, context);


--
-- Name: auth_tokens_deleted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auth_tokens_deleted_at_index ON public.auth_tokens USING btree (deleted_at);


--
-- Name: auth_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auth_tokens_expires_at_index ON public.auth_tokens USING btree (expires_at);


--
-- Name: auth_tokens_service_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auth_tokens_service_type_index ON public.auth_tokens USING btree (service, type);


--
-- Name: auth_tokens_team_id_service_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auth_tokens_team_id_service_type_index ON public.auth_tokens USING btree (team_id, service, type);


--
-- Name: billing_history_stripe_charge_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX billing_history_stripe_charge_id_index ON public.billing_history USING btree (stripe_charge_id);


--
-- Name: billing_history_stripe_invoice_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX billing_history_stripe_invoice_id_index ON public.billing_history USING btree (stripe_invoice_id);


--
-- Name: billing_history_stripe_payment_intent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX billing_history_stripe_payment_intent_id_index ON public.billing_history USING btree (stripe_payment_intent_id);


--
-- Name: billing_history_team_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX billing_history_team_id_status_index ON public.billing_history USING btree (team_id, status);


--
-- Name: billing_history_team_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX billing_history_team_id_type_index ON public.billing_history USING btree (team_id, type);


--
-- Name: demand_templates_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demand_templates_category_index ON public.demand_templates USING btree (category);


--
-- Name: demand_templates_team_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demand_templates_team_id_is_active_index ON public.demand_templates USING btree (team_id, is_active);


--
-- Name: demand_templates_team_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demand_templates_team_id_user_id_index ON public.demand_templates USING btree (team_id, user_id);


--
-- Name: idx_27661_agent_prompt_directives_agent_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27661_agent_prompt_directives_agent_id_foreign ON public.agent_prompt_directives USING btree (agent_id);


--
-- Name: idx_27661_agent_prompt_directives_prompt_directive_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27661_agent_prompt_directives_prompt_directive_id_foreign ON public.agent_prompt_directives USING btree (prompt_directive_id);


--
-- Name: idx_27661_agent_prompt_directives_resource_package_import_id_in; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27661_agent_prompt_directives_resource_package_import_id_in ON public.agent_prompt_directives USING btree (resource_package_import_id);


--
-- Name: idx_27668_messageables_message_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27668_messageables_message_id_foreign ON public.agent_thread_messageables USING btree (agent_thread_message_id);


--
-- Name: idx_27668_messageables_messageable_type_messageable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27668_messageables_messageable_type_messageable_id_index ON public.agent_thread_messageables USING btree (messageable_type, messageable_id);


--
-- Name: idx_27673_messages_thread_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27673_messages_thread_id_foreign ON public.agent_thread_messages USING btree (agent_thread_id);


--
-- Name: idx_27682_thread_runs_job_dispatch_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27682_thread_runs_job_dispatch_id_foreign ON public.agent_thread_runs USING btree (job_dispatch_id);


--
-- Name: idx_27682_thread_runs_last_message_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27682_thread_runs_last_message_id_foreign ON public.agent_thread_runs USING btree (last_message_id);


--
-- Name: idx_27682_thread_runs_thread_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27682_thread_runs_thread_id_foreign ON public.agent_thread_runs USING btree (agent_thread_id);


--
-- Name: idx_27693_threads_agent_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27693_threads_agent_id_foreign ON public.agent_threads USING btree (agent_id);


--
-- Name: idx_27693_threads_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27693_threads_team_id_foreign ON public.agent_threads USING btree (team_id);


--
-- Name: idx_27693_threads_user_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27693_threads_user_id_foreign ON public.agent_threads USING btree (user_id);


--
-- Name: idx_27701_agents_knowledge_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27701_agents_knowledge_id_foreign ON public.agents USING btree (knowledge_id);


--
-- Name: idx_27701_agents_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27701_agents_resource_package_import_id_index ON public.agents USING btree (resource_package_import_id);


--
-- Name: idx_27701_agents_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27701_agents_team_id_foreign ON public.agents USING btree (team_id);


--
-- Name: idx_27712_api_logs_api_class_status_code_method_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27712_api_logs_api_class_status_code_method_index ON public.api_logs USING btree (api_class, status_code, method);


--
-- Name: idx_27712_api_logs_service_name_status_code_method_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27712_api_logs_service_name_status_code_method_index ON public.api_logs USING btree (service_name, status_code, method);


--
-- Name: idx_27712_api_logs_url_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27712_api_logs_url_index ON public.api_logs USING btree (url);


--
-- Name: idx_27712_api_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27712_api_logs_user_id_index ON public.api_logs USING btree (user_id);


--
-- Name: idx_27719_artifactables_artifact_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27719_artifactables_artifact_id_foreign ON public.artifactables USING btree (artifact_id);


--
-- Name: idx_27719_artifactables_artifactable_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27719_artifactables_artifactable_index ON public.artifactables USING btree (artifactable_id, artifactable_type);


--
-- Name: idx_27725_artifacts_original_artifact_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27725_artifacts_original_artifact_id_foreign ON public.artifacts USING btree (original_artifact_id);


--
-- Name: idx_27725_artifacts_parent_artifact_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27725_artifacts_parent_artifact_id_foreign ON public.artifacts USING btree (parent_artifact_id);


--
-- Name: idx_27725_artifacts_schema_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27725_artifacts_schema_definition_id_foreign ON public.artifacts USING btree (schema_definition_id);


--
-- Name: idx_27725_artifacts_task_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27725_artifacts_task_definition_id_foreign ON public.artifacts USING btree (task_definition_id);


--
-- Name: idx_27725_artifacts_task_process_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27725_artifacts_task_process_id_foreign ON public.artifacts USING btree (task_process_id);


--
-- Name: idx_27725_artifacts_task_run_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27725_artifacts_task_run_id_foreign ON public.artifacts USING btree (task_run_id);


--
-- Name: idx_27725_artifacts_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27725_artifacts_team_id_foreign ON public.artifacts USING btree (team_id);


--
-- Name: idx_27734_audit_request_environment_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27734_audit_request_environment_index ON public.audit_request USING btree (environment);


--
-- Name: idx_27734_audit_request_session_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27734_audit_request_session_id_index ON public.audit_request USING btree (session_id);


--
-- Name: idx_27734_audit_request_url_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27734_audit_request_url_index ON public.audit_request USING btree (url);


--
-- Name: idx_27734_audit_request_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27734_audit_request_user_id_index ON public.audit_request USING btree (user_id);


--
-- Name: idx_27741_audits_audit_request_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27741_audits_audit_request_id_foreign ON public.audits USING btree (audit_request_id);


--
-- Name: idx_27741_audits_auditable_type_auditable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27741_audits_auditable_type_auditable_id_index ON public.audits USING btree (auditable_type, auditable_id);


--
-- Name: idx_27741_audits_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27741_audits_user_id_index ON public.audits USING btree (user_id);


--
-- Name: idx_27756_content_sources_team_id_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27756_content_sources_team_id_name_unique ON public.content_sources USING btree (team_id, name);


--
-- Name: idx_27766_error_log_entry_audit_request_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27766_error_log_entry_audit_request_id_foreign ON public.error_log_entry USING btree (audit_request_id);


--
-- Name: idx_27766_error_log_entry_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27766_error_log_entry_created_at_index ON public.error_log_entry USING btree (created_at);


--
-- Name: idx_27766_error_log_entry_error_log_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27766_error_log_entry_error_log_id_foreign ON public.error_log_entry USING btree (error_log_id);


--
-- Name: idx_27766_error_log_entry_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27766_error_log_entry_user_id_created_at_index ON public.error_log_entry USING btree (user_id, created_at);


--
-- Name: idx_27774_error_logs_error_class_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27774_error_logs_error_class_index ON public.error_logs USING btree (error_class);


--
-- Name: idx_27774_error_logs_hash_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27774_error_logs_hash_unique ON public.error_logs USING btree (hash);


--
-- Name: idx_27774_error_logs_level_code_error_class_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27774_error_logs_level_code_error_class_index ON public.error_logs USING btree (level, code, error_class);


--
-- Name: idx_27774_error_logs_message_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27774_error_logs_message_index ON public.error_logs USING btree (message);


--
-- Name: idx_27774_error_logs_parent_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27774_error_logs_parent_id_foreign ON public.error_logs USING btree (parent_id);


--
-- Name: idx_27774_error_logs_root_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27774_error_logs_root_id_foreign ON public.error_logs USING btree (root_id);


--
-- Name: idx_27789_category; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27789_category ON public.job_dispatch USING btree (name);


--
-- Name: idx_27789_job_dispatch_job_batch_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27789_job_dispatch_job_batch_id_foreign ON public.job_dispatch USING btree (job_batch_id);


--
-- Name: idx_27789_job_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27789_job_id ON public.job_dispatch USING btree (ref);


--
-- Name: idx_27796_job_dispatchables_job_dispatch_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27796_job_dispatchables_job_dispatch_id_foreign ON public.job_dispatchables USING btree (job_dispatch_id);


--
-- Name: idx_27802_knowledge_team_id_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27802_knowledge_team_id_name_unique ON public.knowledge USING btree (team_id, name);


--
-- Name: idx_27814_model_refs_prefix_ref_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27814_model_refs_prefix_ref_unique ON public.model_refs USING btree (prefix, ref);


--
-- Name: idx_27819_object_tag_taggables_taggable_type_taggable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27819_object_tag_taggables_taggable_type_taggable_id_index ON public.object_tag_taggables USING btree (taggable_type, taggable_id);


--
-- Name: idx_27819_object_tag_taggables_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27819_object_tag_taggables_unique ON public.object_tag_taggables USING btree (object_tag_id, taggable_id, taggable_type);


--
-- Name: idx_27824_object_tags_category_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27824_object_tags_category_name_unique ON public.object_tags USING btree (category, name);


--
-- Name: idx_27858_permissions_display_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27858_permissions_display_name_unique ON public.permissions USING btree (display_name);


--
-- Name: idx_27865_personal_access_tokens_token_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27865_personal_access_tokens_token_unique ON public.personal_access_tokens USING btree (token);


--
-- Name: idx_27865_personal_access_tokens_tokenable_type_tokenable_id_in; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27865_personal_access_tokens_tokenable_type_tokenable_id_in ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: idx_27872_prompt_directives_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27872_prompt_directives_resource_package_import_id_index ON public.prompt_directives USING btree (resource_package_import_id);


--
-- Name: idx_27872_prompt_directives_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27872_prompt_directives_team_id_foreign ON public.prompt_directives USING btree (team_id);


--
-- Name: idx_27879_resource_package_imports_resource_package_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27879_resource_package_imports_resource_package_id_foreign ON public.resource_package_imports USING btree (resource_package_id);


--
-- Name: idx_27879_resource_package_imports_resource_package_version_id_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27879_resource_package_imports_resource_package_version_id_ ON public.resource_package_imports USING btree (resource_package_version_id);


--
-- Name: idx_27879_resource_package_imports_team_uuid_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27879_resource_package_imports_team_uuid_foreign ON public.resource_package_imports USING btree (team_uuid);


--
-- Name: idx_27884_resource_package_versions_resource_package_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27884_resource_package_versions_resource_package_id_foreign ON public.resource_package_versions USING btree (resource_package_id);


--
-- Name: idx_27895_role_permission_permission_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27895_role_permission_permission_id_foreign ON public.role_permission USING btree (permission_id);


--
-- Name: idx_27895_role_permission_role_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27895_role_permission_role_id_foreign ON public.role_permission USING btree (role_id);


--
-- Name: idx_27900_role_user_role_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27900_role_user_role_id_foreign ON public.role_user USING btree (role_id);


--
-- Name: idx_27900_role_user_user_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27900_role_user_user_id_foreign ON public.role_user USING btree (user_id);


--
-- Name: idx_27905_roles_display_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27905_roles_display_name_unique ON public.roles USING btree (display_name);


--
-- Name: idx_27912_schema_associations_object_type_object_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27912_schema_associations_object_type_object_id_index ON public.schema_associations USING btree (object_type, object_id);


--
-- Name: idx_27912_schema_associations_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27912_schema_associations_resource_package_import_id_index ON public.schema_associations USING btree (resource_package_import_id);


--
-- Name: idx_27912_schema_associations_schema_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27912_schema_associations_schema_definition_id_foreign ON public.schema_associations USING btree (schema_definition_id);


--
-- Name: idx_27912_schema_associations_schema_fragment_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27912_schema_associations_schema_fragment_id_foreign ON public.schema_associations USING btree (schema_fragment_id);


--
-- Name: idx_27918_prompt_schemas_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27918_prompt_schemas_team_id_foreign ON public.schema_definitions USING btree (team_id);


--
-- Name: idx_27918_schema_definitions_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27918_schema_definitions_resource_package_import_id_index ON public.schema_definitions USING btree (resource_package_import_id);


--
-- Name: idx_27927_prompt_schema_fragments_prompt_schema_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27927_prompt_schema_fragments_prompt_schema_id_foreign ON public.schema_fragments USING btree (schema_definition_id);


--
-- Name: idx_27927_schema_fragments_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27927_schema_fragments_resource_package_import_id_index ON public.schema_fragments USING btree (resource_package_import_id);


--
-- Name: idx_27935_prompt_schema_history_prompt_schema_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27935_prompt_schema_history_prompt_schema_id_foreign ON public.schema_history USING btree (schema_definition_id);


--
-- Name: idx_27935_prompt_schema_history_user_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27935_prompt_schema_history_user_id_foreign ON public.schema_history USING btree (user_id);


--
-- Name: idx_27941_sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27941_sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: idx_27941_sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27941_sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: idx_27947_stored_file_storables_storable_type_storable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27947_stored_file_storables_storable_type_storable_id_index ON public.stored_file_storables USING btree (storable_type, storable_id);


--
-- Name: idx_27947_stored_file_storables_stored_file_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27947_stored_file_storables_stored_file_id_foreign ON public.stored_file_storables USING btree (stored_file_id);


--
-- Name: idx_27947_stored_file_storables_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27947_stored_file_storables_unique ON public.stored_file_storables USING btree (storable_id, storable_type, stored_file_id);


--
-- Name: idx_27951_file_filepath_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27951_file_filepath_index ON public.stored_files USING btree (filepath);


--
-- Name: idx_27951_stored_files_is_transcoding_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27951_stored_files_is_transcoding_index ON public.stored_files USING btree (is_transcoding);


--
-- Name: idx_27951_stored_files_original_stored_file_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27951_stored_files_original_stored_file_id_foreign ON public.stored_files USING btree (original_stored_file_id);


--
-- Name: idx_27959_task_artifact_filters_resource_package_import_id_inde; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27959_task_artifact_filters_resource_package_import_id_inde ON public.task_artifact_filters USING btree (resource_package_import_id);


--
-- Name: idx_27959_task_artifact_filters_schema_fragment_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27959_task_artifact_filters_schema_fragment_id_foreign ON public.task_artifact_filters USING btree (schema_fragment_id);


--
-- Name: idx_27959_task_artifact_filters_target_task_definition_id_forei; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27959_task_artifact_filters_target_task_definition_id_forei ON public.task_artifact_filters USING btree (target_task_definition_id);


--
-- Name: idx_27959_task_artifact_filters_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27959_task_artifact_filters_unique ON public.task_artifact_filters USING btree (source_task_definition_id, target_task_definition_id);


--
-- Name: idx_27969_task_definition_directives_prompt_directive_id_foreig; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27969_task_definition_directives_prompt_directive_id_foreig ON public.task_definition_directives USING btree (prompt_directive_id);


--
-- Name: idx_27969_task_definition_directives_resource_package_import_in; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27969_task_definition_directives_resource_package_import_in ON public.task_definition_directives USING btree (resource_package_import_id);


--
-- Name: idx_27969_task_definition_directives_task_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27969_task_definition_directives_task_definition_id_foreign ON public.task_definition_directives USING btree (task_definition_id);


--
-- Name: idx_27976_task_definitions_agent_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27976_task_definitions_agent_id_foreign ON public.task_definitions USING btree (agent_id);


--
-- Name: idx_27976_task_definitions_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27976_task_definitions_resource_package_import_id_index ON public.task_definitions USING btree (resource_package_import_id);


--
-- Name: idx_27976_task_definitions_schema_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27976_task_definitions_schema_definition_id_foreign ON public.task_definitions USING btree (schema_definition_id);


--
-- Name: idx_27976_task_definitions_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27976_task_definitions_team_id_foreign ON public.task_definitions USING btree (team_id);


--
-- Name: idx_27989_task_inputs_task_definition_id_workflow_input_id_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_27989_task_inputs_task_definition_id_workflow_input_id_uniq ON public.task_inputs USING btree (task_definition_id, workflow_input_id);


--
-- Name: idx_27989_task_inputs_workflow_input_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27989_task_inputs_workflow_input_id_foreign ON public.task_inputs USING btree (workflow_input_id);


--
-- Name: idx_27995_task_process_listeners_task_process_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_27995_task_process_listeners_task_process_id_foreign ON public.task_process_listeners USING btree (task_process_id);


--
-- Name: idx_28000_task_processes_last_job_dispatch_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28000_task_processes_last_job_dispatch_id_foreign ON public.task_processes USING btree (last_job_dispatch_id);


--
-- Name: idx_28000_task_processes_task_run_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28000_task_processes_task_run_id_foreign ON public.task_processes USING btree (task_run_id);


--
-- Name: idx_28000_task_processes_thread_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28000_task_processes_thread_id_foreign ON public.task_processes USING btree (agent_thread_id);


--
-- Name: idx_28013_task_runs_task_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28013_task_runs_task_definition_id_foreign ON public.task_runs USING btree (task_definition_id);


--
-- Name: idx_28013_task_runs_task_input_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28013_task_runs_task_input_id_foreign ON public.task_runs USING btree (task_input_id);


--
-- Name: idx_28013_task_runs_task_workflow_node_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28013_task_runs_task_workflow_node_id_foreign ON public.task_runs USING btree (workflow_node_id);


--
-- Name: idx_28013_task_runs_task_workflow_run_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28013_task_runs_task_workflow_run_id_foreign ON public.task_runs USING btree (workflow_run_id);


--
-- Name: idx_28026_team_object_attribute_sources_agent_thread_message_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28026_team_object_attribute_sources_agent_thread_message_id ON public.team_object_attribute_sources USING btree (agent_thread_message_id);


--
-- Name: idx_28026_team_object_attribute_sources_stored_file_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28026_team_object_attribute_sources_stored_file_id_foreign ON public.team_object_attribute_sources USING btree (stored_file_id);


--
-- Name: idx_28026_team_object_attribute_sources_team_object_attribute_i; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28026_team_object_attribute_sources_team_object_attribute_i ON public.team_object_attribute_sources USING btree (team_object_attribute_id);


--
-- Name: idx_28033_team_object_attributes_agent_thread_run_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28033_team_object_attributes_agent_thread_run_id_foreign ON public.team_object_attributes USING btree (agent_thread_run_id);


--
-- Name: idx_28033_team_object_attributes_team_object_id_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_28033_team_object_attributes_team_object_id_name_unique ON public.team_object_attributes USING btree (team_object_id, name);


--
-- Name: idx_28040_team_object_relationship_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_28040_team_object_relationship_unique ON public.team_object_relationships USING btree (team_object_id, related_team_object_id, relationship_name);


--
-- Name: idx_28040_team_object_relationships_related_team_object_id_fore; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28040_team_object_relationships_related_team_object_id_fore ON public.team_object_relationships USING btree (related_team_object_id);


--
-- Name: idx_28045_team_objects_root_object_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28045_team_objects_root_object_id_foreign ON public.team_objects USING btree (root_object_id);


--
-- Name: idx_28045_team_objects_schema_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28045_team_objects_schema_definition_id_foreign ON public.team_objects USING btree (schema_definition_id);


--
-- Name: idx_28045_team_objects_team_id_type_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28045_team_objects_team_id_type_name_index ON public.team_objects USING btree (team_id, type, name);


--
-- Name: idx_28052_team_user_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28052_team_user_team_id_foreign ON public.team_user USING btree (team_id);


--
-- Name: idx_28052_team_user_user_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28052_team_user_user_id_foreign ON public.team_user USING btree (user_id);


--
-- Name: idx_28057_teams_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_28057_teams_name_unique ON public.teams USING btree (name);


--
-- Name: idx_28057_teams_uuid_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_28057_teams_uuid_unique ON public.teams USING btree (uuid);


--
-- Name: idx_28083_usage_events_event_type_object_type_object_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28083_usage_events_event_type_object_type_object_id_index ON public.usage_events USING btree (event_type, object_type, object_id);


--
-- Name: idx_28083_usage_events_object_id_object_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28083_usage_events_object_id_object_type_index ON public.usage_events USING btree (object_id, object_type);


--
-- Name: idx_28083_usage_events_team_id_object_type_object_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28083_usage_events_team_id_object_type_object_id_index ON public.usage_events USING btree (team_id, object_type, object_id);


--
-- Name: idx_28083_usage_events_user_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28083_usage_events_user_id_foreign ON public.usage_events USING btree (user_id);


--
-- Name: idx_28093_usage_summaries_object_id_object_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28093_usage_summaries_object_id_object_type_index ON public.usage_summaries USING btree (object_id, object_type);


--
-- Name: idx_28104_users_email_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_28104_users_email_unique ON public.users USING btree (email);


--
-- Name: idx_28111_workflow_api_invocations_workflow_run_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28111_workflow_api_invocations_workflow_run_id_foreign ON public.workflow_api_invocations USING btree (workflow_run_id);


--
-- Name: idx_28118_task_workflow_connections_source_node_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28118_task_workflow_connections_source_node_id_foreign ON public.workflow_connections USING btree (source_node_id);


--
-- Name: idx_28118_task_workflow_connections_target_node_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28118_task_workflow_connections_target_node_id_foreign ON public.workflow_connections USING btree (target_node_id);


--
-- Name: idx_28118_task_workflow_connections_task_workflow_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28118_task_workflow_connections_task_workflow_id_foreign ON public.workflow_connections USING btree (workflow_definition_id);


--
-- Name: idx_28118_workflow_connections_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28118_workflow_connections_resource_package_import_id_index ON public.workflow_connections USING btree (resource_package_import_id);


--
-- Name: idx_28126_task_workflows_team_id_name_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_28126_task_workflows_team_id_name_unique ON public.workflow_definitions USING btree (team_id, name);


--
-- Name: idx_28126_workflow_definitions_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28126_workflow_definitions_resource_package_import_id_index ON public.workflow_definitions USING btree (resource_package_import_id);


--
-- Name: idx_28133_input_sources_team_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28133_input_sources_team_id_foreign ON public.workflow_inputs USING btree (team_id);


--
-- Name: idx_28133_input_sources_user_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28133_input_sources_user_id_foreign ON public.workflow_inputs USING btree (user_id);


--
-- Name: idx_28133_workflow_inputs_content_source_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28133_workflow_inputs_content_source_id_foreign ON public.workflow_inputs USING btree (content_source_id);


--
-- Name: idx_28142_task_workflow_nodes_task_definition_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28142_task_workflow_nodes_task_definition_id_foreign ON public.workflow_nodes USING btree (task_definition_id);


--
-- Name: idx_28142_task_workflow_nodes_task_workflow_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28142_task_workflow_nodes_task_workflow_id_foreign ON public.workflow_nodes USING btree (workflow_definition_id);


--
-- Name: idx_28142_workflow_nodes_resource_package_import_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28142_workflow_nodes_resource_package_import_id_index ON public.workflow_nodes USING btree (resource_package_import_id);


--
-- Name: idx_28149_task_workflow_runs_task_workflow_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_28149_task_workflow_runs_task_workflow_id_foreign ON public.workflow_runs USING btree (workflow_definition_id);


--
-- Name: payment_methods_stripe_payment_method_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payment_methods_stripe_payment_method_id_index ON public.payment_methods USING btree (stripe_payment_method_id);


--
-- Name: payment_methods_team_id_is_default_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payment_methods_team_id_is_default_index ON public.payment_methods USING btree (team_id, is_default);


--
-- Name: subscription_plans_is_active_sort_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_plans_is_active_sort_order_index ON public.subscription_plans USING btree (is_active, sort_order);


--
-- Name: subscriptions_stripe_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_stripe_customer_id_index ON public.subscriptions USING btree (stripe_customer_id);


--
-- Name: subscriptions_stripe_subscription_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_stripe_subscription_id_index ON public.subscriptions USING btree (stripe_subscription_id);


--
-- Name: subscriptions_team_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_team_id_status_index ON public.subscriptions USING btree (team_id, status);


--
-- Name: task_processes_is_ready_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX task_processes_is_ready_index ON public.task_processes USING btree (is_ready);


--
-- Name: teams_stripe_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX teams_stripe_customer_id_index ON public.teams USING btree (stripe_customer_id);


--
-- Name: ui_demand_workflow_runs_ui_demand_id_workflow_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ui_demand_workflow_runs_ui_demand_id_workflow_type_index ON public.ui_demand_workflow_runs USING btree (ui_demand_id, workflow_type);


--
-- Name: ui_demands_team_id_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ui_demands_team_id_status_created_at_index ON public.ui_demands USING btree (team_id, status, created_at);


--
-- Name: ui_demands_team_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ui_demands_team_id_user_id_index ON public.ui_demands USING btree (team_id, user_id);


--
-- Name: usage_event_subscribers_subscriber_type_subscriber_id_int_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX usage_event_subscribers_subscriber_type_subscriber_id_int_index ON public.usage_event_subscribers USING btree (subscriber_type, subscriber_id_int);


--
-- Name: usage_event_subscribers_usage_event_id_subscriber_type_subscrib; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX usage_event_subscribers_usage_event_id_subscriber_type_subscrib ON public.usage_event_subscribers USING btree (usage_event_id, subscriber_type, subscriber_id_int);


--
-- Name: usage_events_object_type_object_id_int_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX usage_events_object_type_object_id_int_index ON public.usage_events USING btree (object_type, object_id_int);


--
-- Name: usage_summaries_object_type_object_id_int_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX usage_summaries_object_type_object_id_int_index ON public.usage_summaries USING btree (object_type, object_id_int);


--
-- Name: workflow_input_associations_associable_type_associable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workflow_input_associations_associable_type_associable_id_index ON public.workflow_input_associations USING btree (associable_type, associable_id);


--
-- Name: workflow_input_associations_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workflow_input_associations_category_index ON public.workflow_input_associations USING btree (category);


--
-- Name: workflow_listeners_listener_type_listener_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workflow_listeners_listener_type_listener_id_index ON public.workflow_listeners USING btree (listener_type, listener_id);


--
-- Name: workflow_listeners_morph_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workflow_listeners_morph_index ON public.workflow_listeners USING btree (listener_type, listener_id);


--
-- Name: workflow_listeners_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workflow_listeners_type_status_index ON public.workflow_listeners USING btree (workflow_type, status);


--
-- Name: agent_thread_runs agent_thread_runs_mcp_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_thread_runs
    ADD CONSTRAINT agent_thread_runs_mcp_server_id_foreign FOREIGN KEY (mcp_server_id) REFERENCES public.mcp_servers(id) ON DELETE SET NULL;


--
-- Name: assistant_actions assistant_actions_agent_thread_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assistant_actions
    ADD CONSTRAINT assistant_actions_agent_thread_id_foreign FOREIGN KEY (agent_thread_id) REFERENCES public.agent_threads(id) ON DELETE CASCADE;


--
-- Name: assistant_actions assistant_actions_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assistant_actions
    ADD CONSTRAINT assistant_actions_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: assistant_actions assistant_actions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assistant_actions
    ADD CONSTRAINT assistant_actions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: auth_tokens auth_tokens_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auth_tokens
    ADD CONSTRAINT auth_tokens_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: billing_history billing_history_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_history
    ADD CONSTRAINT billing_history_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE SET NULL;


--
-- Name: billing_history billing_history_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_history
    ADD CONSTRAINT billing_history_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: demand_templates demand_templates_stored_file_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demand_templates
    ADD CONSTRAINT demand_templates_stored_file_id_foreign FOREIGN KEY (stored_file_id) REFERENCES public.stored_files(id) ON DELETE CASCADE;


--
-- Name: demand_templates demand_templates_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demand_templates
    ADD CONSTRAINT demand_templates_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: demand_templates demand_templates_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demand_templates
    ADD CONSTRAINT demand_templates_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: mcp_servers mcp_servers_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_servers
    ADD CONSTRAINT mcp_servers_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: payment_methods payment_methods_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_methods
    ADD CONSTRAINT payment_methods_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: subscriptions subscriptions_subscription_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_subscription_plan_id_foreign FOREIGN KEY (subscription_plan_id) REFERENCES public.subscription_plans(id) ON DELETE CASCADE;


--
-- Name: subscriptions subscriptions_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: task_definitions task_definitions_task_queue_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_definitions
    ADD CONSTRAINT task_definitions_task_queue_type_id_foreign FOREIGN KEY (task_queue_type_id) REFERENCES public.task_queue_types(id) ON DELETE SET NULL;


--
-- Name: ui_demand_workflow_runs ui_demand_workflow_runs_ui_demand_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demand_workflow_runs
    ADD CONSTRAINT ui_demand_workflow_runs_ui_demand_id_foreign FOREIGN KEY (ui_demand_id) REFERENCES public.ui_demands(id) ON DELETE CASCADE;


--
-- Name: ui_demand_workflow_runs ui_demand_workflow_runs_workflow_run_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demand_workflow_runs
    ADD CONSTRAINT ui_demand_workflow_runs_workflow_run_id_foreign FOREIGN KEY (workflow_run_id) REFERENCES public.workflow_runs(id) ON DELETE CASCADE;


--
-- Name: ui_demands ui_demands_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demands
    ADD CONSTRAINT ui_demands_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: ui_demands ui_demands_team_object_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demands
    ADD CONSTRAINT ui_demands_team_object_id_foreign FOREIGN KEY (team_object_id) REFERENCES public.team_objects(id) ON DELETE CASCADE;


--
-- Name: ui_demands ui_demands_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demands
    ADD CONSTRAINT ui_demands_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ui_demands ui_demands_workflow_run_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_demands
    ADD CONSTRAINT ui_demands_workflow_run_id_foreign FOREIGN KEY (workflow_run_id) REFERENCES public.workflow_runs(id) ON DELETE SET NULL;


--
-- Name: usage_event_subscribers usage_event_subscribers_usage_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_event_subscribers
    ADD CONSTRAINT usage_event_subscribers_usage_event_id_foreign FOREIGN KEY (usage_event_id) REFERENCES public.usage_events(id) ON DELETE CASCADE;


--
-- Name: workflow_input_associations workflow_input_associations_workflow_input_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_input_associations
    ADD CONSTRAINT workflow_input_associations_workflow_input_id_foreign FOREIGN KEY (workflow_input_id) REFERENCES public.workflow_inputs(id) ON DELETE CASCADE;


--
-- Name: workflow_listeners workflow_listeners_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_listeners
    ADD CONSTRAINT workflow_listeners_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: workflow_listeners workflow_listeners_workflow_run_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_listeners
    ADD CONSTRAINT workflow_listeners_workflow_run_id_foreign FOREIGN KEY (workflow_run_id) REFERENCES public.workflow_runs(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict RhqIz5XjA00x8XDqQxdETMAdwts2JkSziMJjNVO2TZ9tF34oXPJU7EjV1GyPbVK

--
-- PostgreSQL database dump
--

\restrict U1ngHLdj8H7JvqwvQqRekuPb3fkfTIfL89r5iUJkmr9FdDlavuKENEwwEbQGmhL

-- Dumped from database version 15.14 (Debian 15.14-1.pgdg13+1)
-- Dumped by pg_dump version 15.14 (Ubuntu 15.14-1.pgdg22.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0000_danx_auditing_tables	1
2	0000_danx_files_tables	1
3	0001_01_01_000000_create_users_table	1
4	0001_01_01_000001_create_cache_table	1
5	2024_04_21_014518_create_teams_table	1
6	2024_04_21_014520_create_knowledge_table	1
7	2024_04_21_014524_create_agents_table	1
8	2024_04_21_015904_create_threads_table	1
9	2024_04_21_015910_create_messages_table	1
10	2024_04_21_041443_create_thread_runs_table	1
11	2024_04_21_041459_create_artifacts_table	1
12	2024_04_21_045209_add_team_id_to_users	1
13	2024_05_09_041448_create_input_sources	1
14	2024_05_09_042721_create_workflows	1
15	2024_05_09_042843_create_workflow_jobs	1
16	2024_05_09_042844_create_workflow_job_dependencies	1
17	2024_05_09_043131_create_workflow_runs	1
18	2024_05_09_043132_create_workflow_assignments	1
19	2024_05_09_043134_create_workflow_job_runs	1
20	2024_05_09_043135_create_workflow_tasks	1
21	2024_05_16_033638_create_personal_access_tokens_table	1
22	2024_05_26_021708_add_team_user_table	2
23	0001_danx_job_dispatch_data_field	3
24	2024_05_30_033435_refactor_input_sources_to_workflow_inputs	4
25	2024_05_30_221634_create_object_tags_table	5
26	2024_05_30_225721_create_content_source_table	5
27	2024_06_07_074914_add_logo_to_teams	6
28	2024_06_07_141934_add_schema_file_to_teams	7
29	2024_06_25_051901_add_job_dispatch_id_to_thread_runs	8
30	2024_06_26_220600_add_assignments_count_to_agents	8
31	2024_07_04_152615_change_unique_includes_deleted_to_agents	8
32	2024_07_04_203751_add_response_fields_to_agents	8
33	2024_07_10_201134_nullable_team_fields	9
34	2024_07_13_160123_add_response_sample_to_agents	9
35	2024_07_15_062329_group_by_json_field_to_workflow_job_dependencies	9
36	2024_07_15_070643_add_include_fields_to_workflow_job_dependencies	9
37	2024_07_25_081507_add_summarizer_offset_to_messages	10
38	2024_07_26_004653_drop_is_transcoded_to_workflow_inputs	10
39	2024_07_29_185243_ai_model_to_threads	11
40	2024_08_02_204226_config_to_workflow_job_dependencies	12
41	2024_08_05_033533_counts_to_workflow_runs	13
42	2024_08_11_043921_add_schema_format_to_agents	14
43	2024_08_12_075632_add_team_objects_to_workflow_inputs	14
44	2024_08_12_173000_add_timeout_after_to_workflow_jobs	14
45	2024_08_12_211631_add_response_schema_to_workflow_jobs	14
46	2024_08_14_005809_add_enable_artifact_sources_to_agents	14
47	2024_08_14_054311_create_messageables_table	14
48	2024_08_15_084124_add_retry_count_to_agents	15
49	2024_08_25_204941_create_agent_schemas	16
50	2024_08_25_204946_create_agent_directives	16
51	2024_08_30_004155_migrate_agent_prompts	16
52	2024_08_30_005137_cleanup_old_prompting_fields	16
53	2024_08_30_155828_migrate_response_schema_to_workflow_jobs	16
54	2024_08_31_033742_add_runs_count_to_workflow_jobs	16
55	2024_08_31_034913_drop_schema_file_to_teams	16
56	2024_11_23_192758_prompt_schema_history	16
57	2024_12_16_220320_drop_artifact_id_from_worklfow_tasks	16
58	2024_12_18_210629_add_save_response_to_db_to_agents	16
59	2024_12_19_171419_add_schema_sub_selection_to_agents	16
60	0002_danx_create_job_dispatchable_table	17
61	2025_01_18_223623_create_prompt_schema_fragments_table	17
62	2025_01_19_204402_create_task_definition_table	17
63	2025_01_19_204408_create_task_definition_agent_table	17
64	2025_01_19_224024_create_task_runs_table	17
65	2025_01_19_224035_create_task_processes_table	17
66	2025_01_20_224806_create_task_process_listeners	17
67	2025_01_25_233222_replace_sub_selection_with_fragments_to_agents	17
68	2025_01_27_230935_rename_content_fields_to_artifacts	17
69	2025_01_27_234426_rename_thread_to_agent_threads	17
70	2025_01_30_045107_artifact_model_not_required	17
71	2025_02_04_005654_add_token_costs_to_thread_runs	17
72	2025_02_05_034132_create_task_inputs_table	17
73	2025_02_05_160239_create_usage_events_table	17
74	2025_02_05_162941_create_usage_summaries_table	17
75	2025_02_05_164152_drop_usage_columns_from_tasks	17
76	2025_02_05_180636_add_counts_to_task_processes	17
77	2025_02_05_202531_add_step_and_activity_to_task_processes	17
78	2025_02_07_045720_add_grouping_mode_and_key_to_task_definitions	17
79	2025_02_07_191309_create_schema_associations_table	17
80	2025_02_08_010357_remove_input_output_schemas_from_task_definition_agents	17
81	2025_02_09_220452_rename_prompt_schema_to_schema_definitions	17
82	2025_02_10_004055_allow_null_schemea_fragment_id_to_schema_associations	17
89	2025_02_20_184509_add_page_number_to_stored_files	20
90	2025_02_12_205006_create_task_workflows_table	21
91	2025_02_13_023819_create_task_workflow_nodes	21
92	2025_02_13_023847_create_task_workflow_connections	21
93	2025_02_13_025038_create_task_workflow_runs	21
94	2025_02_13_034949_add_task_workflow_run_id_to_task_runs	21
95	2025_02_14_205440_add_team_to_task_workflows	21
96	2025_03_04_015332_convert_to_ms_timestamps	22
97	2025_03_06_041709_add_input_output_artifacts_count_to_task_runs	22
98	2025_03_07_032835_add_config_to_task_definitions	22
99	2025_03_07_032850_remove_obsolete_workflow_fields	22
100	2025_03_08_054142_remove_save_response_to_db_from_agents	22
101	2025_03_08_060506_add_meta_to_artifacts	22
102	2025_03_13_002736_add_response_schema_to_agent_thread_runs	23
103	2025_03_14_064408_add_has_run_all_tasks_to_task_workflow_run	24
104	2025_03_14_231724_drop_old_workflow_tables	25
105	2025_03_15_001627_create_team_objects_tables	26
106	2025_03_15_002145_create_team_object_attributes	26
107	2025_03_15_002152_create_team_object_attribute_sources	26
108	2025_03_15_002201_create_team_object_relationships	26
109	2025_03_15_210226_remove_agent_count_from_schema_definitions	26
110	2025_03_15_215022_rename_task_workflow_to_workflow	26
111	2025_03_16_051414_add_owner_team_id_fields	26
112	2025_03_16_202946_nullable_fired_at_to_task_process_listeners	27
113	2025_03_17_231429_create_resource_packages	27
114	2025_03_18_000537_change_imports_to_resource_packages	27
115	2025_03_18_053225_add_json_schema_config_to_agent_thread_runs	27
116	2025_03_18_070551_add_response_json_schema_to_agent_thread_runs	28
117	2025_03_18_191143_add_schema_definition_id_to_artifacts	29
118	2025_03_20_041541_add_roles_and_permissions_tables	30
119	2025_03_20_123054_create_artifact_filters_table	31
120	2025_03_25_004450_medium_text_to_artifacts	31
121	2025_03_25_231201_add_schema_fragment_id_to_task_artifact_filters	32
122	2025_03_26_152656_add_can_view_edit_to_resource_package_version_imports	32
123	2025_03_26_205153_medium_text_to_agent_thread_messages	33
124	2025_03_26_234012_add_position_to_artifacts	34
125	2025_03_27_031900_add_resource_package_to_task_artifact_filters	35
126	2025_03_31_051309_create_workflow_api_invocations	36
127	2025_04_01_220325_add_schema_and_agent_to_task_definitions	37
128	2025_04_02_214127_512_description_fields	38
129	2025_04_02_223141_remove_task_definition_agents	38
130	2025_04_03_050927_create_task_definition_directives	38
131	2025_04_04_030646_add_response_format_to_task_definitions	38
132	2025_04_04_070048_fix_name_on_resource_package_import_id_to_task_definition_directives	39
133	2025_04_09_202749_add_source_and_target_to_artifactables	40
134	2025_04_10_195514_rename_task_runner_class_on_task_definitions	40
135	2025_04_13_201055_add_name_to_workflow_runs	41
136	2025_04_14_180835_add_parent_artifact_id_to_artifacts	42
137	2025_04_14_202831_add_source_artifact_levels_to_task_definitions	42
138	2025_04_15_014240_add_original_artifact_id_to_artifacts	42
139	2025_04_15_020454_remove_source_task_defintion_id_to_artifactables	42
140	2025_04_15_140436_add_meta_to_artifact_filters	43
141	2025_04_15_193858_add_task_process_id_to_artifacts	44
142	2025_04_16_055238_longer_activity_field_to_task_processes	45
143	2025_04_16_145211_add_output_artifact_levels_to_task_definitions	46
144	2025_04_17_004314_add_skipped_at_to_task_runs	47
145	0003_danx_stored_files_is_transcoding_field	48
146	0004_danx_refs_table	48
147	2025_04_24_160345_add_restart_count_to_task_processes	49
148	2025_04_25_030155_add_task_run_id_to_artifacts	50
149	2025_04_25_162906_add_stopped_at_to_agent_thread_runs	51
150	0005_danx_api_logs_add_timestamps	52
151	2025_05_09_223154_convert_char_to_bigint	53
152	2025_05_09_223154_convert_char_to_bigint_2	54
153	2025_05_15_212224_fix_total_costs_type	55
184	2025_06_17_222221_add_max_workers_to_workflow_definitions_table	56
185	2025_06_17_224850_add_max_workers_to_task_definitions_table	56
186	2025_06_19_213247_remove_dispatched_status_from_task_processes	56
187	2025_06_19_221322_create_task_queue_types_table	56
188	2025_06_19_221822_add_task_queue_type_id_to_task_definitions	56
189	2025_06_19_221932_seed_default_task_queue_types	56
190	2025_06_19_224648_remove_max_workers_from_task_definitions	56
191	2025_06_20_000000_add_incomplete_at_to_task_processes_table	56
192	2025_06_21_000001_add_active_workers_count_to_workflow_runs_table	56
193	2025_06_21_223846_update_job_dispatch_run_time_to_generated_column	56
217	2025_06_25_001801_create_assistant_actions_table	57
218	2025_06_25_014837_job_dispatch_run_time_ms	57
219	2025_06_25_050129_add_duration_to_assistant_actions_table	57
220	2025_06_27_032749_add_responses_api_support_to_agent_thread_runs	58
221	2025_06_27_052334_add_api_configuration_to_agents_table	58
222	2025_06_27_152845_remove_tools_and_tool_choice_from_agents_and_agent_thread_runs	58
223	2025_06_27_162348_add_api_response_id_to_agent_thread_messages	58
224	2025_06_27_223035_remove_temperature_columns_from_agents_and_agent_thread_runs	58
225	2025_07_10_185528_create_mcp_servers_table	58
226	2025_07_11_074153_fix_mcp_servers_label_unique_constraint	58
227	2025_07_11_074507_drop_unused_columns_from_mcp_servers_table	58
228	2025_07_11_201429_add_mcp_server_id_to_agent_thread_runs_table	58
250	2025_07_12_004611_add_missing_usage_fields_to_usage_events	59
251	2025_07_15_060900_modify_usage_tables_object_id_field	59
252	2025_07_15_062321_remove_legacy_usage_fields_from_agent_thread_runs	59
253	2025_07_15_062539_add_object_id_int_to_usage_tables	59
254	2025_07_16_051024_drop_api_from_agents	59
255	2025_07_16_081844_create_ui_demands_table	59
256	2025_07_16_233835_add_meta_to_task_processes_table	59
257	2025_07_24_201908_add_workflow_fields_to_ui_demands_table	59
258	2025_07_25_224057_create_workflow_listeners_table	59
259	2025_07_28_021208_add_team_user_fields_to_stored_files_table	59
260	2025_07_30_172705_add_timeout_to_agent_thread_runs_table	59
261	2025_08_05_221310_create_subscription_plans_table	59
262	2025_08_05_221315_create_subscriptions_table	59
263	2025_08_05_221319_create_payment_methods_table	59
264	2025_08_05_221323_create_billing_history_table	59
265	2025_08_07_174708_add_stripe_customer_id_to_teams_table	59
266	2025_08_09_023457_add_stripe_charge_id_to_billing_history_table	59
267	2025_08_11_190000_create_auth_tokens_table	59
268	2025_08_13_181340_add_workflow_run_id_to_ui_demands_table	59
269	2025_08_13_221502_remove_submitted_at_from_ui_demands_table	59
270	2025_08_13_231613_create_ui_demand_workflow_runs_table	59
271	2025_08_14_222225_create_demand_templates_table	59
272	2025_08_15_154548_make_stored_file_id_nullable_in_demand_templates_table	59
273	2025_08_17_045639_add_template_variables_to_demand_templates_table	59
274	2025_08_27_183131_add_soft_deletes_to_auth_tokens_table	59
275	2025_08_30_070210_add_is_ready_to_task_processes_table	60
276	2025_08_30_221836_create_usage_event_subscribers_table	61
283	2025_09_03_004959_add_meta_to_schema_definitions_table	62
316	2025_09_05_051812_create_workflow_input_associations_table	63
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 316, true);


--
-- PostgreSQL database dump complete
--

\unrestrict U1ngHLdj8H7JvqwvQqRekuPb3fkfTIfL89r5iUJkmr9FdDlavuKENEwwEbQGmhL

