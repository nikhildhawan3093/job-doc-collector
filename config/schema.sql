-- job-doc-collector schema

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password TEXT NOT NULL
);

CREATE TABLE applications (
    id SERIAL PRIMARY KEY,
    candidate_name VARCHAR(255),
    candidate_email VARCHAR(255),
    role VARCHAR(255),
    token TEXT UNIQUE,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE documents (
    id SERIAL PRIMARY KEY,
    application_id INTEGER REFERENCES applications(id),
    document_type VARCHAR(50), -- resume / cover_letter / id_proof
    file_url TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
