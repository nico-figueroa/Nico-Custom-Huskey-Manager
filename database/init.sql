-- Create the database
CREATE DATABASE IF NOT EXISTS password_manager;

-- Use the database
USE password_manager;

-- Drop tables if they exist (now that database is selected)
--DROP TABLE IF EXISTS vault_permissions;
--DROP TABLE IF EXISTS vault_passwords;
--DROP TABLE IF EXISTS vaults;
--DROP TABLE IF EXISTS users;
--DROP TABLE IF EXISTS roles;

-- Create the roles table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('Owner', 'Editor', 'Viewer') NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    default_role_id INT,    
    approved INT,
    FOREIGN KEY (default_role_id) REFERENCES roles(role_id) ON DELETE CASCADE 
);

-- Create the vaults table
CREATE TABLE IF NOT EXISTS vaults (
    vault_id INT AUTO_INCREMENT PRIMARY KEY,    
    vault_name VARCHAR(255) NOT NULL    
);

-- Create the passwords table
CREATE TABLE IF NOT EXISTS vault_passwords (
    password_id INT AUTO_INCREMENT PRIMARY KEY,
    vault_id INT,
    username VARCHAR(255) NOT NULL,
    website VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    notes TEXT,
    file_path VARCHAR(512), -- New column for file path
    FOREIGN KEY (vault_id) REFERENCES vaults(vault_id) ON DELETE CASCADE
);

-- Create the permissions table
CREATE TABLE IF NOT EXISTS vault_permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    vault_id INT,
    role_id INT,    
    FOREIGN KEY (vault_id) REFERENCES vaults(vault_id) ON DELETE CASCADE,   
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE        
);

-- Create the failed_logins table for brute force detection
CREATE TABLE IF NOT EXISTS failed_logins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    username VARCHAR(255),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create an index on username for faster retrieval
CREATE UNIQUE INDEX idx_username ON users(username);

-- Insert sample roles
INSERT INTO roles (role_id, role)
VALUES
    (1, 'Owner'),
    (2, 'Editor'),
    (3, 'Viewer');

-- Insert sample users
-- Passwords are stored as bcrypt hashes using PHP password_hash() so login uses password_verify().
-- Plaintext sample credentials for testing:
-- username: password!
-- admin: Sup3rS3cr3t@dm1n
-- johndoe: SecureP@ssw0rd
-- janedoe: Doe12345
-- alice_smith: SmithPass123
-- bob_johnson: Johnson1234
-- sarah_wilson: Wilson9876
-- mike_brown: BrownPass456
-- emily_davis: DavisSecure789
-- kevin_clark: Clark123!
-- laura_jones: JonesPass432
-- chris_miller: MillerSecure123
-- nicofig: Sup3rStr0ngP@ssw0rd
INSERT INTO users (user_id, username, first_name, last_name, email, password, default_role_id, approved)
VALUES
    (1, 'username', 'User', 'Name', 'user@info310.net', '$2b$12$evGGYYWX6jRgNkzQWG40leJeQxImnonlk0XTfyeizhIMj6KmO0Rly', 3, 1),
    (2, 'admin', 'Super', 'Admin', 'admin@info310.com', '$2b$12$qCHsj6UIE1knH7wyVv/9QusXOI9gkvkTw6dQ.ZgqTs0SZZ9AMBBce', 1, 1),
    (3, 'johndoe', 'John', 'Doe', 'john.doe@info310.com', '$2b$12$CeIpHlgP6OfmxsI8lDLqxOfpGQwEivmOt9/bZyW8wGeffPj82Mm9S', 3, 1),
    (4, 'janedoe', 'Jane', 'Doe', 'jane.doe@info310.com', '$2b$12$Rs4zgAsIP6T6IVjToCclleRmDv3k29eUtuCWh1bHFGzz449.WHEMe', 3, 1),
    (5, 'alice_smith', 'Alice', 'Smith', 'alice.smith@info310.com', '$2b$12$Zu3/qJqsDtpHGvEL4JPGju3xUijVPLL13Hm1JSSScXKWgX0OCcyf.', 3, 1),
    (6, 'bob_johnson', 'Bob', 'Johnson', 'bob.johnson@info310.com', '$2b$12$wM8ImYeZJs1ICrqxIGNUJOgoCMLKdz2.IS4eErByvA.Mnko1Zlz/i', 3, 1),
    (7, 'sarah_wilson', 'Sarah', 'Wilson', 'sarah.wilson@info310.com', '$2b$12$4sLfSGwsnGvBbEPVEhKQ/uF8Gm9QwTKSh9y229I2Ll9sfwVhpsf/y', 3, 1),
    (8, 'mike_brown', 'Mike', 'Brown', 'mike.brown@info310.com', '$2b$12$V6OJm64i1mdyF0t6zzj5LO1O/ZfkYZZ/bWh5IvO7nELh2gSD8tn02', 3, 1),
    (9, 'emily_davis', 'Emily', 'Davis', 'emily.davis@info310.com', '$2b$12$m6I2ZfXFt9H7/xn/mYgi9.Axd5xarMV9KaZUy5mWwcOHv.8qY3S5a', 3, 1),
    (10, 'kevin_clark', 'Kevin', 'Clark', 'kevin.clark@info310.com', '$2b$12$QsFO6DZv3XmdHseLTJtIUeJcAJBsFDCWd77FremPCC5.K7hD1uV/u', 3, 1),
    (11, 'laura_jones', 'Laura', 'Jones', 'laura.jones@info310.com', '$2b$12$RGwJfF6GJY/2t/xIuPXnO.a77EYVhK61pmNODTjCDiF7XDJ8jzIpC', 3, 1),
    (12, 'chris_miller', 'Chris', 'Miller', 'chris.miller@info310.com', '$2b$12$moJdLEyI//7GCTCIx7cVNemrrWdfCTyj5a93nBwMbZfyWK45HOPRC', 3, 1),
    (13, 'nicofig', 'Nico', 'Figueroa', 'nicofig@uw.edu', '$2b$12$kbZvKqExQ48t/KzruSjpJO.n6WSa/rv9/m4ms74FcjVk4cSs0HtZm', 3, 1);


--    (1, 'username', 'User', 'Name', 'user@info310.net', 'password!', 3, 1),
--    (2, 'admin', 'Super', 'Admin', 'admin@info310.com', 'Sup3rS3cr3t@dm1n', 1, 1),
--    (3, 'johndoe', 'John', 'Doe', 'john.doe@info310.com', 'SecureP@ssw0rd', 3, 1),
--    (4, 'janedoe', 'Jane', 'Doe', 'jane.doe@info310.com', 'Doe12345', 3, 1),
--    (5, 'alice_smith', 'Alice', 'Smith', 'alice.smith@info310.com', 'SmithPass123', 3, 1),
--    (6, 'bob_johnson', 'Bob', 'Johnson', 'bob.johnson@info310.com', 'Johnson1234', 3, 1),
--    (7, 'sarah_wilson', 'Sarah', 'Wilson', 'sarah.wilson@info310.com', 'Wilson9876', 3, 1),
--    (8, 'mike_brown', 'Mike', 'Brown', 'mike.brown@info310.com', 'BrownPass456', 3, 1),
--    (9, 'emily_davis', 'Emily', 'Davis', 'emily.davis@info310.com', 'DavisSecure789', 3, 1),
--    (10, 'kevin_clark', 'Kevin', 'Clark', 'kevin.clark@info310.com', 'Clark123!', 3, 1),
--    (11, 'laura_jones', 'Laura', 'Jones', 'laura.jones@info310.com', 'JonesPass432', 3, 1),
--    (12, 'chris_miller', 'Chris', 'Miller', 'chris.miller@info310.com', 'MillerSecure123', 3, 1),
--    (13, 'nicofig', 'Nico', 'Figueroa', 'nicofig@uw.edu', 'Sup3rStr0ngP@ssw0rd', 3, 1);



-- Insert sample vaults
INSERT INTO vaults (vault_id, vault_name)
VALUES
    (1, 'Developers Vault'),
    (2, 'Executives Vault'),
    (3, 'HR Vault');

-- Insert sample passwords
INSERT INTO vault_passwords (password_id, vault_id, username, website, password, notes, file_path)
VALUES
    -- Fake logins for "Developers Vault"
    (3, 1, 'developer1', 'github.com', 'dev_password1', 'Developer notes for this password', ''),
    (4, 1, 'developer2', 'stackoverflow.com', 'dev_password2', 'Developer notes for this password', ''),
    (5, 1, 'developer3', 'gitlab.com', 'dev_password3', 'Developer notes for this password', './uploads/dev_notes.pdf'),
    -- Fake logins for "Executives Vault"
    (6, 2, 'executive1', 'companyportal.com', 'exec_password1', 'Executive notes for this password', ''),
    (7, 2, 'executive2', 'boardmeeting.com', 'exec_password2', 'Executive notes for this password', './uploads/exec_notes.txt'),
    -- Fake logins for "HR Vault"
    (8, 3, 'hr1', 'hrportal.com', 'hr_password1', 'HR notes for this password', ''),
    (9, 3, 'hr2', 'payroll.com', 'hr_password2', 'HR notes for this password', ''),
    (10, 3, 'hr3', 'benefits.com', 'hr_password3', 'HR notes for this password', './uploads/hr_notes.pdf');


-- Assign roles and permissions
INSERT INTO vault_permissions (permission_id, user_id, vault_id, role_id)
VALUES
    -- Assigning users to the "Developers Vault"
    (2, 1, 1, 3),  -- Username (Viewer)
    (3, 3, 1, 2),  -- Jane Doe (Editor)
    (4, 5, 1, 3),  -- Alice Smith (Viewer)
    (5, 6, 1, 3),  -- Bob Johnson (Viewer)
    (6, 10, 1, 3), -- Kevin Clark (Viewer)
    (7, 11, 1, 3), -- Laura Jones (Viewer)
    -- Assigning users to the "Executives Vault"
    (8, 2, 2, 1),  -- Super Admin (Owner)
    (9, 7, 2, 2),  -- Sarah Wilson (Editor)
    (10, 8, 2, 3), -- Mike Brown (Viewer)
    -- Assigning users to the "HR Vault"
    (11, 4, 3, 1), -- Jane Doe (Owner)
    (12, 9, 3, 2), -- Emily Davis (Editor)
    (13, 12, 3, 3) -- Chris Miller (Viewer)
