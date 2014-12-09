<?php
namespace Custom\Service\LectureNote;

interface LectureNoteService
{
    public function getLectureNote($id);

    public function findAllLectureNotes();

    public function createLectureNote(array $field);

    public function deleteLectureNote($id);

    public function findLessonLectureNotes($lessonId);

    public function findLectureNotesByType($type);
}