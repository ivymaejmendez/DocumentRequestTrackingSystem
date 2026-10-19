CREATE DATABASE IF NOT EXISTS document_request_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE document_request_system;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 role ENUM('admin','user') NOT NULL DEFAULT 'user',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE document_types (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 description TEXT,
 processing_days INT DEFAULT 3,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE document_requests (
 id INT AUTO_INCREMENT PRIMARY KEY,
 reference_no VARCHAR(50) NOT NULL UNIQUE,
 user_id INT NOT NULL,
 document_type_id INT NOT NULL,
 purpose TEXT NOT NULL,
 quantity INT NOT NULL DEFAULT 1,
 status ENUM('Pending','Processing','Ready for Pickup','Completed','Rejected') NOT NULL DEFAULT 'Pending',
 remarks TEXT,
 requested_at DATETIME NOT NULL,
 completed_at DATETIME NULL,
 updated_at DATETIME NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (document_type_id) REFERENCES document_types(id)
);

CREATE TABLE request_status_history (
 id INT AUTO_INCREMENT PRIMARY KEY,
 request_id INT NOT NULL,
 status VARCHAR(50) NOT NULL,
 remarks TEXT,
 changed_by INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
 FOREIGN KEY (changed_by) REFERENCES users(id)
);

INSERT INTO users(name,email,password,role) VALUES
('System Administrator','admin@example.com', '$2y$10$.wWDlEeg1t.yWhae3rdMre3MgmjnYX2z.vWrJ.sN4jLRfd..PBFYq', 'admin'),
('Demo User','user@example.com', '$2y$10$.wWDlEeg1t.yWhae3rdMre3MgmjnYX2z.vWrJ.sN4jLRfd..PBFYq', 'user');

INSERT INTO document_types(name,description,processing_days) VALUES
('Certificate of Enrollment','Proof of current enrollment',3),
('Transcript of Records','Official academic transcript',5),
('Certificate of Good Moral','Good moral certificate',3),
('Certificate of Graduation','Graduation certificate',5);
