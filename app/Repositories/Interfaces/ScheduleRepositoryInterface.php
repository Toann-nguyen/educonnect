<?php

namespace App\Repositories\Interfaces;

interface ScheduleRepositoryInterface
{
    public function all();
    public function find($id);
    public function findByClass($classId);
    public function findByTeacher($teacherId);
    public function findByStudent($studentId);
    public function findByClassAndWeek($classId, $date);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function getTeacherClasses($teacherId);
}
