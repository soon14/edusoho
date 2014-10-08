<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;

class ClassScheduleController extends ClassBaseController
{
    public function showAction(Request $request, $classId)
    {
        $class = $this->tryViewClass($classId);
        return $this->render("TopxiaWebBundle:ClassSchedule:show.html.twig", array(
            "class" => $class,
        ));
    }

    public function coursesAction(Request $request, $class)
    {
        $this->tryViewClass($class['id']);
        $user = $this->getCurrentUser();
        $courses = array();
        $conditions =array(
            'classId' => $class['id'],
            'status' => 'published',
            'gradeId' => $class['gradeId'],
            'term' => $class['term']
        );

        $total = $this->getCourseService()->searchCourseCount($conditions);

        $courses = $this->getCourseService()->searchCourses($conditions, 'latest', 0, $total);

        $userIds = array();
        foreach ($courses as $course) {
            $userIds = array_merge($userIds, $course['teacherIds']);
        }

        $users = $this->getUserService()->findUsersByIds($userIds);
        
        if($user->isAdmin()) {
            return $this->render('TopxiaWebBundle:ClassSchedule:courses-editable.html.twig', array(
                'courses' => $courses,
                'users' => $users,
                ));
        }

        if($user->isTeacher()) {
            $newCourses = array();
            foreach ($courses as $course) {
                if(in_array($user['id'], $course['teacherIds'])) {
                    $newCourses[] = $course;
                }
            }
            return $this->render('TopxiaWebBundle:ClassSchedule:courses-editable.html.twig', array(
                'courses' => $newCourses,
                'users' => $users,
                ));
        }
        
        return $this->render('TopxiaWebBundle:ClassSchedule:courses.html.twig', array(
            'courses' => $courses,
            'users' => $users,
            ));
    }

    public function getItemsAction($courseId)
    {
        $items = $this->getCourseService()->getCourseItems($courseId);
        $course = $this->getCourseService()->getCourse($courseId);
        return $this->render('TopxiaWebBundle:ClassSchedule:item-list.html.twig', array(
            'items' => $items,
            'course' => $course,
            ));
    }

    public function scheduleAction(Request $request, $classId)
    {
        $user = $this->getCurrentUser();
        $previewAs = $request->query->get('previewAs') ? : 'week';
        $sunDay = $request->query->get('sunday');
        $period = $request->query->get('date');
        if($previewAs == 'week') {
            $results = $this->getScheduleService()->findScheduleLessonsByWeek($classId, $sunDay);
        } else {
            $results = $this->getScheduleService()->findScheduleLessonsByMonth($classId, $period);            
            $courses = $results['courses'];
            foreach ($courses as $key => $course) {
                $middlePicture = $this->get('topxia.twig.web_extension')->getFilePath($course['middlePicture'], 'course-large.png', false);
                $course['middlePicture'] = $middlePicture;
                $courses[$key] = $course;
            }
            $results['courses'] = $courses;
            return $this->createJsonResponse($results);
        }
        $userLessonLearns = $this->getCourseService()->findUserLessonLearns($user['id']);
        $userLessonLearns =ArrayToolkit::index($userLessonLearns, 'lessonId');
        return $this->render("TopxiaWebBundle:ClassSchedule:{$previewAs}-view.html.twig", array(
            'courses' => $results['courses'],
            'lessons' => $results['lessons'],
            'changeMonth' => $results['changeMonth'],
            'schedules' => $results['schedules'],
            'lessonLearns' => $userLessonLearns,
            ));
    }

    public function saveAction(Request $request, $classId)
    {
        $this->tryManageClass($classId);
        $lessons = $request->request->all();
        $lessonIds = explode(',', $lessons['ids']);
        $schedules = array();
        foreach ($lessonIds as $index => $id) {
            $schedule['classId'] = $classId;
            $schedule['lessonId'] = $id;
            $schedule['sequence'] = $index + 1;
            $schedule['date'] = $lessons['day'];
            $schedule['createdTime'] = time();
            $schedules[] = $schedule;    
        }
        $this->getScheduleService()->saveSchedules($schedules);
        return new Response("success");
    }
    private function getScheduleService()
    {
        return $this->getServiceKernel()->createService('Schedule.ScheduleService');
    }

    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }
}