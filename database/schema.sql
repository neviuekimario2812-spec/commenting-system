-- =========================================
-- Commenting System: database tables
-- =========================================

-- 1. USERS
CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    display_name    VARCHAR(50)  NOT NULL,
    profile_picture VARCHAR(255),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. COMMENTS
CREATE TABLE comments (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content    TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

-- 3. COMMENT IMAGES
CREATE TABLE comment_images (
    id         SERIAL PRIMARY KEY,
    comment_id INTEGER NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 4. LIKES
CREATE TABLE likes (
    id         SERIAL PRIMARY KEY,
    comment_id INTEGER NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (comment_id, user_id)
);

-- 5. REPLIES
CREATE TABLE replies (
    id         SERIAL PRIMARY KEY,
    comment_id INTEGER NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- INDEXES (to make searching faster)
CREATE INDEX idx_comments_user_id        ON comments(user_id);
CREATE INDEX idx_comments_created_at     ON comments(created_at);
CREATE INDEX idx_comment_images_comment  ON comment_images(comment_id);
CREATE INDEX idx_likes_comment_id        ON likes(comment_id);
CREATE INDEX idx_replies_comment_id      ON replies(comment_id);