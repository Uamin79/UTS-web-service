-- Menambahkan Foreign Key Constraints untuk memastikan integritas data
-- Jalankan setelah db_siap_siswa.sql

-- Foreign keys untuk tabel admins
ALTER TABLE `admins` ADD CONSTRAINT `fk_admins_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel teachers
ALTER TABLE `teachers` ADD CONSTRAINT `fk_teachers_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel classes
ALTER TABLE `classes` ADD CONSTRAINT `fk_classes_homeroom_teacher_id` FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Foreign keys untuk tabel students
ALTER TABLE `students` ADD CONSTRAINT `fk_students_class_id` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel parents
ALTER TABLE `parents` ADD CONSTRAINT `fk_parents_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel student_parent_relations
ALTER TABLE `student_parent_relations` ADD CONSTRAINT `fk_student_parent_relations_student_id` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `student_parent_relations` ADD CONSTRAINT `fk_student_parent_relations_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel teacher_subjects
ALTER TABLE `teacher_subjects` ADD CONSTRAINT `fk_teacher_subjects_teacher_id` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `teacher_subjects` ADD CONSTRAINT `fk_teacher_subjects_subject_id` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `teacher_subjects` ADD CONSTRAINT `fk_teacher_subjects_class_id` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel attendances
ALTER TABLE `attendances` ADD CONSTRAINT `fk_attendances_student_id` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `attendances` ADD CONSTRAINT `fk_attendances_subject_id` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel grades
ALTER TABLE `grades` ADD CONSTRAINT `fk_grades_student_id` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `grades` ADD CONSTRAINT `fk_grades_subject_id` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Foreign keys untuk tabel report_cards
ALTER TABLE `report_cards` ADD CONSTRAINT `fk_report_cards_student_id` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
