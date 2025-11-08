ALTER TABLE student_parent_relations ADD COLUMN relationship_type ENUM('ayah', 'ibu', 'wali', 'kakek', 'nenek', 'saudara') NOT NULL DEFAULT 'ayah' AFTER parent_id;
