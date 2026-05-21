BEGIN;

-- ------------------------------------------------------------
-- Extensions
-- ------------------------------------------------------------

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------

CREATE TABLE users (
    id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    name            VARCHAR(150) NOT NULL,

    role            VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,

    CHECK (role IN ('user', 'admin'))
);

-- ------------------------------------------------------------
-- Family trees
-- ------------------------------------------------------------

CREATE TABLE family_trees (
    id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,

    name            VARCHAR(150) NOT NULL,
    description     TEXT,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Persons
-- ------------------------------------------------------------

CREATE TABLE persons (
    id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tree_id         INTEGER NOT NULL REFERENCES family_trees(id) ON DELETE CASCADE,

    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100),
    maiden_name     VARCHAR(100),
    gender          VARCHAR(20) DEFAULT 'unknown',

    birth_date      DATE,
    birth_place     VARCHAR(255),

    death_date      DATE,
    death_place     VARCHAR(255),

    is_living       BOOLEAN NOT NULL DEFAULT TRUE,

    occupation      VARCHAR(255),
    notes           TEXT,

    photo_path      TEXT,
    avatar_color    VARCHAR(20) DEFAULT '#5f8f86',
    x_position      INTEGER,
    y_position      INTEGER,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CHECK (gender IN ('male', 'female', 'other', 'unknown'))
);

ALTER TABLE family_trees
ADD COLUMN root_person_id INTEGER REFERENCES persons(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- Partnerships
-- ------------------------------------------------------------

CREATE TABLE partnerships (
    id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tree_id         INTEGER NOT NULL REFERENCES family_trees(id) ON DELETE CASCADE,

    person1_id      INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,
    person2_id      INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,

    status          VARCHAR(30) NOT NULL DEFAULT 'current',
    start_date      DATE,
    end_date        DATE,
    notes           TEXT,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CHECK (person1_id <> person2_id),
    CHECK (status IN ('current', 'former', 'spouse'))
);

-- ------------------------------------------------------------
-- Parent-child relationships
-- ------------------------------------------------------------

CREATE TABLE parent_child (
    id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tree_id         INTEGER NOT NULL REFERENCES family_trees(id) ON DELETE CASCADE,

    parent_id       INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,
    child_id        INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,
    partnership_id  INTEGER REFERENCES partnerships(id) ON DELETE SET NULL,

    relation_type   VARCHAR(30) NOT NULL DEFAULT 'biological',

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CHECK (parent_id <> child_id),
    CHECK (relation_type IN ('biological', 'adoptive', 'step', 'unknown')),
    UNIQUE (parent_id, child_id)
);

-- ------------------------------------------------------------
-- Media files
-- ------------------------------------------------------------

CREATE TABLE media_files (
    id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    person_id       INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,

    file_name       VARCHAR(255) NOT NULL,
    original_name   VARCHAR(255),
    file_path       TEXT NOT NULL,
    mime_type       VARCHAR(100),

    description     TEXT,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Indexes
-- ------------------------------------------------------------

CREATE INDEX idx_users_deleted_at
ON users(deleted_at);

CREATE INDEX idx_family_trees_user_id
ON family_trees(user_id);

CREATE INDEX idx_family_trees_root_person_id
ON family_trees(root_person_id);

CREATE INDEX idx_persons_tree_id
ON persons(tree_id);

CREATE INDEX idx_persons_name
ON persons(tree_id, last_name, first_name);

CREATE INDEX idx_persons_position
ON persons(tree_id, x_position, y_position);

CREATE INDEX idx_partnerships_tree_id
ON partnerships(tree_id);

CREATE INDEX idx_partnerships_person1
ON partnerships(person1_id);

CREATE INDEX idx_partnerships_person2
ON partnerships(person2_id);

CREATE INDEX idx_parent_child_tree_id
ON parent_child(tree_id);

CREATE INDEX idx_parent_child_parent
ON parent_child(parent_id);

CREATE INDEX idx_parent_child_child
ON parent_child(child_id);

CREATE INDEX idx_media_files_person_id
ON media_files(person_id);

-- ------------------------------------------------------------
-- Automatically update updated_at
-- ------------------------------------------------------------

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_family_trees_updated_at
BEFORE UPDATE ON family_trees
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_persons_updated_at
BEFORE UPDATE ON persons
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_partnerships_updated_at
BEFORE UPDATE ON partnerships
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_media_files_updated_at
BEFORE UPDATE ON media_files
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

-- ------------------------------------------------------------
-- Demo data
-- ------------------------------------------------------------

INSERT INTO users (email, password_hash, name, role)
VALUES
    ('admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$T2ZaVml5c2hqOUhOajlrWQ$O1KSf5BRGObONMBTq0r/eZGpmH+Bqr/svCy0342Oisc', 'Administrator', 'admin'),
    ('user@example.com', '$argon2id$v=19$m=65536,t=4,p=1$ZnVJVW9sRXpGSUtMNkhubQ$oX4hCfejjPk3gB1Crp6RV3uwn5/qKj9nJicCh95L82Y', 'Jan Kowalski', 'user');

INSERT INTO family_trees (user_id, name, description)
VALUES
    (2, 'Rodzina Kowalskich', 'Przykładowe drzewo do testowania widoków aplikacji.');

INSERT INTO persons (
    tree_id, first_name, last_name, maiden_name, gender, birth_date, birth_place,
    is_living, occupation, notes, avatar_color, x_position, y_position
)
VALUES
    (1, 'Antoni', 'Kowalski', NULL, 'male', '1948-03-14', 'Kraków', TRUE, 'emerytowany nauczyciel', 'Najstarsza osoba w demonstracyjnym drzewie.', '#607d8b', 210, 84),
    (1, 'Maria', 'Kowalska', 'Nowak', 'female', '1951-08-22', 'Tarnów', TRUE, 'bibliotekarka', NULL, '#9a7b64', 546, 84),
    (1, 'Piotr', 'Kowalski', NULL, 'male', '1976-06-10', 'Kraków', TRUE, 'architekt', NULL, '#5f8f86', 378, 336),
    (1, 'Anna', 'Kowalska', 'Wiśniewska', 'female', '1979-11-03', 'Warszawa', TRUE, 'lekarka', NULL, '#8b6f91', 672, 336),
    (1, 'Ewa', 'Kowalska', NULL, 'female', '2004-04-17', 'Warszawa', TRUE, 'studentka', NULL, '#6b8f71', 210, 630),
    (1, 'Tomasz', 'Kowalski', NULL, 'male', '2008-09-28', 'Warszawa', TRUE, 'uczeń', NULL, '#7a8fa6', 546, 630),
    (1, 'Karolina', 'Zielińska', NULL, 'female', '1981-01-12', 'Gdańsk', TRUE, 'projektantka', 'Była partnerka Piotra.', '#a06b75', 42, 336),
    (1, 'Maja', 'Zielińska', NULL, 'female', '2001-12-05', 'Gdańsk', TRUE, 'fotografka', NULL, '#7f8f69', 840, 630);

INSERT INTO partnerships (tree_id, person1_id, person2_id, status, start_date, end_date)
VALUES
    (1, 1, 2, 'spouse', '1972-05-19', NULL),
    (1, 3, 4, 'current', '2002-07-21', NULL),
    (1, 3, 7, 'former', '1999-01-01', '2002-01-01');

INSERT INTO parent_child (tree_id, parent_id, child_id, partnership_id)
VALUES
    (1, 1, 3, 1),
    (1, 2, 3, 1),
    (1, 3, 5, 2),
    (1, 4, 5, 2),
    (1, 3, 6, 2),
    (1, 4, 6, 2),
    (1, 3, 8, 3),
    (1, 7, 8, 3);

UPDATE family_trees
SET root_person_id = 1
WHERE id = 1;

COMMIT;
