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
    document_type VARCHAR(50), -- resume / cover_letter / id_proof / aadhaar
    file_url TEXT,
    blur_status VARCHAR(20) DEFAULT 'pending', -- pending / clear / blurry
    processed_status VARCHAR(20) DEFAULT 'pending', -- pending / done / failed
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Stores extracted Aadhaar OCR data for a document
CREATE TABLE aadhaar_data (
    id SERIAL PRIMARY KEY,
    document_id INTEGER REFERENCES documents(id) ON DELETE CASCADE,
    aadhaar_number VARCHAR(20),
    name VARCHAR(255),
    dob VARCHAR(20),
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Stores generated PDF report path for a document
CREATE TABLE pdf_reports (
    id SERIAL PRIMARY KEY,
    document_id INTEGER REFERENCES documents(id) ON DELETE CASCADE,
    pdf_path TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
