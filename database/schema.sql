-- CampusConnect Database Schema

CREATE DATABASE IF NOT EXISTS campusconnect;
USE campusconnect;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    campus VARCHAR(255) NOT NULL,
    role ENUM('student', 'mentor', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Profiles table
CREATE TABLE profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    name VARCHAR(255),
    bio TEXT,
    goal ENUM('study_buddy', 'friendship', 'networking', 'mentorship') DEFAULT 'friendship',
    personality ENUM('introvert', 'extrovert', 'ambivert') DEFAULT 'ambivert',
    religion VARCHAR(100),
    relationship_status ENUM('single', 'in_relationship', 'prefer_not_to_say') DEFAULT 'prefer_not_to_say',
    year INT,
    department VARCHAR(255),
    hobbies TEXT,
    profile_picture VARCHAR(255) DEFAULT NULL ,
    privacy_department BOOLEAN DEFAULT FALSE,
    privacy_status BOOLEAN DEFAULT FALSE,
    privacy_goals BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Confessions table
CREATE TABLE confessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    mood ENUM('happy', 'sad', 'anxious', 'excited', 'angry', 'confused', 'grateful', 'neutral') DEFAULT 'neutral',
    tags JSON,
    campus VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Friend requests table
CREATE TABLE friend_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    requester_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_request (requester_id, receiver_id)
);

-- Mentorships table
CREATE TABLE mentorships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mentor_id INT NOT NULL,
    mentee_id INT NOT NULL,
    status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentee_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Votes table (for confession upvotes/downvotes)
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    confession_id INT NOT NULL,
    vote_type ENUM('upvote', 'downvote') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (confession_id) REFERENCES confessions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (user_id, confession_id)
);

-- Reports table (for reporting inappropriate content)
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    confession_id INT NOT NULL,
    reason ENUM('spam', 'harassment', 'inappropriate', 'hate_speech', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (confession_id) REFERENCES confessions(id) ON DELETE CASCADE
);

-- Announcements table
CREATE TABLE announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Schedules table (for study sessions and meetups)
CREATE TABLE schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    organizer_id INT NOT NULL,
    participant_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    scheduled_at DATETIME NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (email, password, campus, role) VALUES 
('admin@campusconnect.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CampusConnect HQ', 'admin');

-- Insert sample data for testing
INSERT INTO users (email, password, campus, role) VALUES 
('john@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'University of Example', 'student'),
('jane@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'University of Example', 'student'),
('mentor@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'University of Example', 'mentor');

-- Insert sample profiles
INSERT INTO profiles (user_id, name, bio, goal, personality, year, department) VALUES 
(2, 'John Doe', 'Computer Science student looking for study partners', 'study_buddy', 'introvert', 3, 'Computer Science'),
(3, 'Jane Smith', 'Psychology major interested in making new friends', 'friendship', 'extrovert', 2, 'Psychology'),
(4, 'Dr. Mike Wilson', 'Senior student available for mentorship', 'mentorship', 'ambivert', 4, 'Engineering');

-- Insert sample confessions
INSERT INTO confessions (user_id, content, mood, tags, campus, status) VALUES 
(2, 'Feeling overwhelmed with midterm exams coming up. Anyone else struggling?', 'anxious', '["academic", "stress"]', 'University of Example', 'approved'),
(3, 'Just had an amazing day at the campus festival! So grateful for this community.', 'happy', '["events", "community"]', 'University of Example', 'approved');
INSERT INTO confessions (user_id, content, mood, tags, campus, status) VALUES 
(4, 'Looking for mentees who want to improve their coding skills. Let\'s connect!', 'neutral', '["mentorship", "coding"]', 'University of Example', 'approved');
