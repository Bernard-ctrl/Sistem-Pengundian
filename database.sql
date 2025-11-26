-- ============================================
-- DATABASE CREATION FOR SISTEM PENGUNDIAN
-- Based on ERD Schema Requirements
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS pengundian;
USE pengundian;

-- ============================================
-- TABLE: PENGGUNA (Users/Voters)
-- ============================================
CREATE TABLE IF NOT EXISTS PENGGUNA (
    id_Pengguna VARCHAR(10) PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: JAWATAN (Positions)
-- ============================================
CREATE TABLE IF NOT EXISTS JAWATAN (
    id_Jawatan VARCHAR(10) PRIMARY KEY,
    nama_Jawatan VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: CALON (Candidates)
-- ============================================
CREATE TABLE IF NOT EXISTS CALON (
    id_Calon VARCHAR(10) PRIMARY KEY,
    nama_Calon VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: UNDIAN (Votes)
-- ============================================
CREATE TABLE IF NOT EXISTS UNDIAN (
    id_Undi INT AUTO_INCREMENT PRIMARY KEY,
    id_Pengguna VARCHAR(10) NOT NULL,
    id_Calon VARCHAR(10) NOT NULL,
    id_Jawatan VARCHAR(10) NOT NULL,
    
    -- Foreign key constraints
    FOREIGN KEY (id_Pengguna) REFERENCES PENGGUNA(id_Pengguna) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_Calon) REFERENCES CALON(id_Calon) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_Jawatan) REFERENCES JAWATAN(id_Jawatan) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Ensure one vote per user per position
    UNIQUE KEY unique_vote (id_Pengguna, id_Jawatan),
    
    -- Index for faster queries
    INDEX idx_pengguna (id_Pengguna),
    INDEX idx_calon (id_Calon),
    INDEX idx_jawatan (id_Jawatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SAMPLE DATA INSERTION (Based on provided image)
-- ============================================

-- Insert sample users (PENGGUNA)
INSERT INTO PENGGUNA (id_Pengguna, nama) VALUES
('D6261', 'Ali'),
('D6262', 'Abu'),
('D6263', 'Ahmad'),
('D6264', 'James');

-- Insert positions (JAWATAN)
INSERT INTO JAWATAN (id_Jawatan, nama_Jawatan) VALUES
('J01', 'Pengerusi'),
('J02', 'Setiausaha'),
('J03', 'Bendahari');

-- Insert candidates (CALON)
INSERT INTO CALON (id_Calon, nama_Calon) VALUES
('C01', 'Omar'),
('C02', 'Hassan'),
('C03', 'Aiman');

-- Insert sample votes (UNDIAN) based on the image data
INSERT INTO UNDIAN (id_Pengguna, id_Calon, id_Jawatan) VALUES
('D6261', 'C01', 'J01'),  -- Ali votes for Omar as Pengerusi
('D6261', 'C02', 'J02'),  -- Ali votes for Hassan as Setiausaha
('D6262', 'C03', 'J03'),  -- Abu votes for Aiman as Bendahari
('D6263', 'C01', 'J02'),  -- Ahmad votes for Omar as Setiausaha
('D6264', 'C03', 'J01');  -- James votes for Aiman as Pengerusi

-- ============================================
-- USEFUL QUERIES
-- ============================================

-- View all votes with details
-- SELECT 
--     u.id_Undi,
--     p.id_Pengguna,
--     p.nama AS nama_pengguna,
--     c.id_Calon,
--     c.nama_Calon,
--     j.id_Jawatan,
--     j.nama_Jawatan
-- FROM UNDIAN u
-- JOIN PENGGUNA p ON u.id_Pengguna = p.id_Pengguna
-- JOIN CALON c ON u.id_Calon = c.id_Calon
-- JOIN JAWATAN j ON u.id_Jawatan = j.id_Jawatan;

-- Count votes per candidate per position
-- SELECT 
--     j.nama_Jawatan,
--     c.nama_Calon,
--     COUNT(*) AS jumlah_undian
-- FROM UNDIAN u
-- JOIN CALON c ON u.id_Calon = c.id_Calon
-- JOIN JAWATAN j ON u.id_Jawatan = j.id_Jawatan
-- GROUP BY j.nama_Jawatan, c.nama_Calon
-- ORDER BY j.nama_Jawatan, jumlah_undian DESC;

-- Check which positions a user has voted for
-- SELECT 
--     p.nama AS nama_pengguna,
--     j.nama_Jawatan,
--     c.nama_Calon
-- FROM UNDIAN u
-- JOIN PENGGUNA p ON u.id_Pengguna = p.id_Pengguna
-- JOIN CALON c ON u.id_Calon = c.id_Calon
-- JOIN JAWATAN j ON u.id_Jawatan = j.id_Jawatan
-- WHERE p.id_Pengguna = 'D6261';
