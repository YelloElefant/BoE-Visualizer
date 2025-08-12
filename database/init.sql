-- setup database for the grade thing
-- basic tables for papers, students, grades etc

-- need this for generating IDs
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- papers/courses table
CREATE TABLE papers (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    paper_code VARCHAR(255) NOT NULL UNIQUE,
    paper_name VARCHAR(500),
    semester VARCHAR(50),
    year INTEGER,
    location VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- students table
CREATE TABLE students (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    student_id VARCHAR(100),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    email VARCHAR(320),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id)
);

-- submissions - who got what grade
CREATE TABLE submissions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    paper_id UUID NOT NULL REFERENCES papers(id) ON DELETE CASCADE,
    student_id UUID NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    grade VARCHAR(10),
    score DECIMAL(5,2),
    max_score DECIMAL(5,2) DEFAULT 100,
    percentage DECIMAL(5,2),
    status VARCHAR(50) DEFAULT 'submitted',
    submission_date TIMESTAMP WITH TIME ZONE,
    graded_date TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(paper_id, student_id)
);

-- track csv uploads
CREATE TABLE csv_uploads (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    paper_id UUID NOT NULL REFERENCES papers(id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    original_headers TEXT[], -- Store original CSV headers
    records_imported INTEGER DEFAULT 0,
    records_updated INTEGER DEFAULT 0,
    upload_timestamp TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(255),
    notes TEXT
);

-- extra fields from csv that we dont really need
CREATE TABLE submission_fields (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    submission_id UUID NOT NULL REFERENCES submissions(id) ON DELETE CASCADE,
    field_name VARCHAR(255) NOT NULL,
    field_value TEXT,
    field_type VARCHAR(50) DEFAULT 'text',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(submission_id, field_name)
);

-- indexes to make queries faster
CREATE INDEX idx_papers_paper_code ON papers(paper_code);
CREATE INDEX idx_papers_year_semester ON papers(year, semester);
CREATE INDEX idx_students_student_id ON students(student_id);
CREATE INDEX idx_students_name ON students(last_name, first_name);
CREATE INDEX idx_students_email ON students(email);
CREATE INDEX idx_submissions_paper_student ON submissions(paper_id, student_id);
CREATE INDEX idx_submissions_grade ON submissions(grade);
CREATE INDEX idx_submissions_score ON submissions(score);
CREATE INDEX idx_csv_uploads_paper ON csv_uploads(paper_id);
CREATE INDEX idx_submission_fields_submission ON submission_fields(submission_id);
CREATE INDEX idx_submission_fields_name ON submission_fields(field_name);

-- auto update timestamp function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- triggers to auto update timestamps
CREATE TRIGGER update_papers_updated_at 
    BEFORE UPDATE ON papers 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_students_updated_at 
    BEFORE UPDATE ON students 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_submissions_updated_at 
    BEFORE UPDATE ON submissions 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- parse paper codes like "COMPX123-22A (HAM)"
CREATE OR REPLACE FUNCTION parse_paper_code(p_paper_code TEXT)
RETURNS TABLE(
    code TEXT,
    name TEXT,
    semester TEXT,
    year INTEGER,
    location TEXT
) AS $$
BEGIN
    -- regex magic to split paper codes
    -- basically grab course, year, semester, location
    
    RETURN QUERY
    SELECT 
        TRIM(split_part(p_paper_code, '-', 1)) as code,
        TRIM(split_part(p_paper_code, '-', 1)) as name,
        CASE 
            WHEN p_paper_code ~ '\d{2}[AB]' THEN 
                CASE WHEN p_paper_code ~ '\d{2}A' THEN 'A' ELSE 'B' END
            ELSE NULL
        END as semester,
        CASE 
            WHEN p_paper_code ~ '\d{2}[AB]' THEN 
                2000 + CAST(substring(p_paper_code from '\d{2}') AS INTEGER)
            ELSE NULL
        END as year,
        CASE 
            WHEN p_paper_code ~ '\([^)]+\)' THEN
                TRIM(substring(p_paper_code from '\(([^)]+)\)'))
            ELSE NULL
        END as location;
END;
$$ LANGUAGE plpgsql;

-- get stats for a paper (averages, pass rates etc)
CREATE OR REPLACE FUNCTION get_paper_stats(p_paper_id UUID)
RETURNS TABLE(
    total_students BIGINT,
    average_score DECIMAL,
    highest_score DECIMAL,
    lowest_score DECIMAL,
    pass_rate DECIMAL,
    grade_distribution JSON
) AS $$
BEGIN
    RETURN QUERY
    WITH stats AS (
        SELECT 
            COUNT(*) as total,
            AVG(s.score) as avg_score,
            MAX(s.score) as max_score,
            MIN(s.score) as min_score,
            COUNT(CASE WHEN s.score >= 50 THEN 1 END) * 100.0 / COUNT(*) as passes
        FROM submissions s
        WHERE s.paper_id = p_paper_id
    ),
    grades AS (
        SELECT 
            s.grade,
            COUNT(*) as grade_count
        FROM submissions s
        WHERE s.paper_id = p_paper_id
        GROUP BY s.grade
    ),
    grade_json AS (
        SELECT json_object_agg(
            COALESCE(grade, 'Ungraded'), 
            grade_count
        ) as grade_dist
        FROM grades
    )
    SELECT 
        stats.total,
        ROUND(stats.avg_score, 2),
        stats.max_score,
        stats.min_score,
        ROUND(stats.passes, 2),
        COALESCE(grade_json.grade_dist, '{}'::json)
    FROM stats, grade_json;
END;
$$ LANGUAGE plpgsql;

-- export paper data back to csv
CREATE OR REPLACE FUNCTION export_paper_csv(p_paper_id UUID)
RETURNS TEXT AS $$
DECLARE
    result TEXT := '';
    headers TEXT := '';
    row_data TEXT;
    rec RECORD;
BEGIN
    -- figure out what columns we have
    WITH all_fields AS (
        SELECT DISTINCT field_name
        FROM submission_fields sf
        JOIN submissions s ON sf.submission_id = s.id
        WHERE s.paper_id = p_paper_id
        ORDER BY field_name
    )
    SELECT 'Student ID,First Name,Last Name,Email,Grade,Score,Max Score,Percentage' || 
           CASE WHEN COUNT(*) > 0 THEN ',' || string_agg(field_name, ',') ELSE '' END
    INTO headers
    FROM all_fields;
    
    result := headers || E'\n';
    
    -- build the actual data rows
    FOR rec IN
        WITH student_data AS (
            SELECT 
                s.id as submission_id,
                st.student_id,
                st.first_name,
                st.last_name,
                st.email,
                s.grade,
                s.score,
                s.max_score,
                s.percentage
            FROM submissions s
            JOIN students st ON s.student_id = st.id
            WHERE s.paper_id = p_paper_id
            ORDER BY st.last_name, st.first_name
        ),
        field_data AS (
            SELECT 
                sd.submission_id,
                sd.student_id,
                sd.first_name,
                sd.last_name,
                sd.email,
                sd.grade,
                sd.score,
                sd.max_score,
                sd.percentage,
                json_object_agg(
                    COALESCE(sf.field_name, 'null'), 
                    COALESCE(sf.field_value, '')
                ) as extra_fields
            FROM student_data sd
            LEFT JOIN submission_fields sf ON sd.submission_id = sf.submission_id
            GROUP BY sd.submission_id, sd.student_id, sd.first_name, sd.last_name, 
                     sd.email, sd.grade, sd.score, sd.max_score, sd.percentage
        )
        SELECT * FROM field_data
    LOOP
        row_data := quote_csv(COALESCE(rec.student_id, '')) || ',' ||
                   quote_csv(COALESCE(rec.first_name, '')) || ',' ||
                   quote_csv(COALESCE(rec.last_name, '')) || ',' ||
                   quote_csv(COALESCE(rec.email, '')) || ',' ||
                   quote_csv(COALESCE(rec.grade, '')) || ',' ||
                   quote_csv(COALESCE(rec.score::text, '')) || ',' ||
                   quote_csv(COALESCE(rec.max_score::text, '')) || ',' ||
                   quote_csv(COALESCE(rec.percentage::text, ''));
        
        result := result || row_data || E'\n';
    END LOOP;
    
    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- helper to quote csv values properly
CREATE OR REPLACE FUNCTION quote_csv(input_text TEXT)
RETURNS TEXT AS $$
BEGIN
    IF input_text IS NULL THEN
        RETURN '';
    END IF;
    
    -- wrap in quotes if it has commas or quotes
    IF input_text ~ '[,"\n\r]' THEN
        RETURN '"' || replace(input_text, '"', '""') || '"';
    ELSE
        RETURN input_text;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- some helpful views
CREATE VIEW paper_summary AS
SELECT 
    p.id,
    p.paper_code,
    p.paper_name,
    p.semester,
    p.year,
    p.location,
    COUNT(s.id) as total_submissions,
    AVG(s.score) as average_score,
    MAX(s.score) as highest_score,
    MIN(s.score) as lowest_score,
    p.created_at,
    p.updated_at
FROM papers p
LEFT JOIN submissions s ON p.id = s.paper_id
GROUP BY p.id, p.paper_code, p.paper_name, p.semester, p.year, p.location, p.created_at, p.updated_at;

CREATE VIEW student_submissions AS
SELECT 
    s.id as submission_id,
    p.paper_code,
    p.paper_name,
    st.student_id,
    st.first_name,
    st.last_name,
    st.email,
    s.grade,
    s.score,
    s.max_score,
    s.percentage,
    s.status,
    s.submission_date,
    s.graded_date
FROM submissions s
JOIN papers p ON s.paper_id = p.id
JOIN students st ON s.student_id = st.id;

-- Comments for documentation
COMMENT ON TABLE papers IS 'Stores academic paper/course information';
COMMENT ON TABLE students IS 'Stores student personal information';
COMMENT ON TABLE submissions IS 'Stores individual student submissions and grades for papers';
COMMENT ON TABLE csv_uploads IS 'Audit trail for CSV file uploads';
COMMENT ON TABLE submission_fields IS 'Flexible storage for additional CSV columns';
COMMENT ON FUNCTION parse_paper_code IS 'Extracts structured information from paper codes';
COMMENT ON FUNCTION get_paper_stats IS 'Returns statistical summary for a paper';
COMMENT ON FUNCTION export_paper_csv IS 'Exports paper data back to CSV format';
