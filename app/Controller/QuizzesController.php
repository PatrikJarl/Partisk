<?php
/** 
 * Controller for managing quiz related pages
 *
 * Partisk : Political Party Opinion Visualizer
 * Copyright (c) Partisk.nu Team (https://www.partisk.nu)
 *
 * Partisk is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Partisk is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Partisk. If not, see http://www.gnu.org/licenses/.
 *
 * @copyright   Copyright (c) Partisk.nu Team (https://www.partisk.nu)
 * @link        https://www.partisk.nu
 * @package     app.Controller
 * @license     http://www.gnu.org/licenses/ GPLv2
 */

App::uses('UserLogger', 'Log');

class QuizzesController extends AppController {
    public $helpers = array('Html', 'Form');

    const DEFAULT_IMPORTANCE = 2;
    const QUIZ_VERSION = 2;

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow(array('next', 'prev', 'results', 'close', 'questions' ,'restart'));
    }

    public function beforeRender() {
        parent::beforeRender();
        $this->set("currentPage", "quiz");
    }

    public function index() {
        $quiz = $this->Session->read('quiz');

        if (!empty($quiz)) {
            $this->set('quizId', $quiz['Quiz']['id']);
        }

        $conditions = array('deleted' => false);

        if(!$this->isLoggedIn) {
            $conditions['approved'] = true;
        }   

        $quizzes = $this->Quiz->find('all', array(
                'conditions' => $conditions
            ));

        $this->set('ongoingQuiz', !empty($quiz));
        $this->set('quizzes', $quizzes);
        $this->set('quizIsDone', $this->quizIsDone());
        $this->set('title_for_layout', 'Quiz');
    }

    public function add() {
        if (!$this->canAddQuiz) {
            $this->abuse("Not authorized to add quiz");
            return $this->redirect($this->referer());
        }

        if ($this->request->is('post')) {
            $this->Quiz->create();
            $this->request->data['Quiz']['created_by'] = $this->Auth->user('id');
            $this->request->data['Quiz']['created_date'] = date('c');
            if ($this->Quiz->save($this->request->data)) {
                $this->customFlash(__('Quizen har skapats.'));
                $this->logUser('add', $this->Quiz->getLastInsertId(), $this->request->data['Quiz']['name']);
            } else {
                $this->customFlash(__('Kunde inte skapa quizen.'), 'danger');
                $this->Session->write('validationErrors', $this->Quiz->validationErrors);
            }

            return $this->redirect($this->referer());
        }
    }

    public function delete($id) {
        if (!$this->userCanDeleteQuiz($this->Auth->user('id'), $id)) {
            $this->abuse("Not authorized to delete quiz with id " . $id);
            return $this->redirect($this->referer());
        }

        $this->deleteQuiz($id);

        return $this->redirect($this->referer());
     }

    private function deleteQuiz($id) {
        $this->Quiz->set(
            array('id' => $id,
                  'deleted' => true,
                  'updated_by' => $this->Auth->user('id'),
                  'update_date' => date('c')));

        if ($this->Quiz->save()) {
            $this->customFlash(__('Tog bort quizzen med id: %s.', h($id)));
            $this->logUser('delete', $id);
        } else {
            $this->customFlash(__('Kunde inte ta bort Quizen.'), 'danger');
        }
    }

    public function questions($id = null) {
        if ($this->quizIsDone()) {
            return $this->redirect(array('action' => 'results'));   
        }

        $quiz = $this->Session->read('quiz');

        if (!isset($quiz) || !isset($quiz['Quiz'])) {
            $quiz = $this->Quiz->generateQuiz($id);
            $this->Session->write('quiz', $quiz);
        }

        $index = $quiz['Quiz']['index'];

        $this->loadModel('Question');
        $questions = $this->Question->getQuestions(array('id' => $quiz[$index]['Question']['id']));
        $question = array_pop($questions);
        $choices = $this->Question->getChoicesFromQuestion($question);

        $answer = $this->getCurrentAnswer($quiz, $index);
        $importance = $this->getCurrentImportance($quiz, $index);
        
        $this->set('question', $question);
        $this->set('answer', $answer);
        $this->set('importance', $importance);
        $this->set('choices', $choices);
        $this->set('quiz', $quiz);
        $this->set('title_for_layout', 'Frågor');
    }

    public function next() {
        if ($this->quizIsDone()) {
            return $this->redirect(array('action' => 'results'));   
        }

        if ($this->request->is('post')) {
            $quiz = $this->Session->read('quiz');
            $index = $quiz['Quiz']['index'];

            $quiz[$index]['Question']['answer'] = null;
            $quiz[$index]['Question']['importance'] = null;

            if (isset($this->request->data['Quiz'])) {
                $answer = $this->request->data['Quiz']['answer'];
                $quiz[$index]['Question']['answer'] = ($answer === 'NO_OPINION' ? null : $answer);
                $quiz[$index]['Question']['importance'] = $this->request->data['Quiz']['importance'];
            }

            $index++;

            $quiz['Quiz']['index'] = $index;

            if ($quiz['Quiz']['index'] >= $quiz['Quiz']['questions']) {
                $quiz['Quiz']['done'] = true;
                $this->Session->write('quiz', $quiz);
                return $this->redirect(array('action' => 'results', $quiz['Quiz']['id']));    
            } else {
                $this->Session->write('quiz', $quiz);
                return $this->redirect(array('action' => 'questions'));
            }
        } else {
            return $this->redirect(array('action' => 'index'));
        }
    }

    public function results($guid = null) {
        $quiz = $this->Session->read('quiz');

        if (empty($quiz) && empty($guid) || empty($guid)) {
            return $this->redirect(array('controller' => 'quiz','action' => 'index'));
        }

        $this->loadModel('QuizResult');
        $this->loadModel('Party');

        $quizResult = $this->QuizResult->findById($guid);
        $quiz = $this->Session->read('quiz');

        $ownQuiz = false;
        $data = null;
        $quizVersion = 0;

        if (isset($quiz['Quiz']) && $quiz['Quiz']['id'] == $guid) {
            $points = $this->Quiz->calculatePoints($quiz);
            $this->set('points', $points);
            $ownQuiz = true;

            $generatedData = $this->Quiz->generateGraphData($points['parties']);
            $data = json_encode($generatedData);
        }

        if (!empty($quizResult)) {
            $data = $quizResult['QuizResult']['data'];
            $quizVersion = $quizResult['QuizResult']['version'];
        }

        if (empty($quizResult) && !empty($guid) && !empty($data)) {
            $this->QuizResult->save(array('id' => $guid, 'data' => $data, 'version' => self::QUIZ_VERSION));
            $quizVersion = self::QUIZ_VERSION;
        }

        if (empty($quizResult) && empty($data)) {
            $this->customFlash(__('Kunde inte hitta quizen.'), 'danger');
            return $this->redirect(array('controller' => 'quiz','action' => 'index'));
        }

        if (intval($quizVersion) !== intval(self::QUIZ_VERSION)) {
            $this->customFlash(__('Denna Quiz är inte längre tillgänglig på grund av att poängsystemet ändrat så pass mycket sedan 
                                   resultatet genererades. Gör gärna om testet igen för att få ett nytt resultat.
                                   Vi ber om ursäkt för besväret. Sidan är fortfarande under kraftig uppbygnad och vi gör snabbt ändringar
                                   för att förbättra sidan med den feedback vi får in.'), 'danger');
            return $this->redirect(array('controller' => 'quiz','action' => 'index'));
        }

        $this->Party->recursive = -1;
        $parties = $this->Party->getPartiesHash();

        $this->set('data', $data);
        $this->set('parties', $parties);
        $this->set('ownQuiz', $ownQuiz);
        $this->set('title_for_layout', 'Resultat');
    }

    public function prev() {
        if ($this->quizIsDone()) {
            return $this->redirect(array('action' => 'results'));   
        }

        $quiz = $this->Session->read('quiz');
        $index = $quiz['Quiz']['index'];

        if ($index >= 0) {
            $index--;
            $quiz['Quiz']['index'] = $index;
            $this->Session->write('quiz', $quiz);
        }

        return $this->redirect(array('action' => 'questions'));
    }

    public function close() {
        $this->Session->delete('quiz');
        return $this->redirect(array('controller' => 'quiz','action' => 'index'));
    }

    public function restart() {
        $this->Session->delete('quiz');
        return $this->redirect(array('controller' => 'quiz','action' => 'questions'));
    }

    private function quizIsDone() {
        $quiz = $this->Session->read('quiz');
        return isset($quiz['Quiz']) && isset($quiz['Quiz']['done']) && $quiz['Quiz']['done'];
    }
    
    private function getCurrentAnswer($quiz, $index) {
        $answer = null;

        if (isset($quiz[$index]['Question']['answer'])) {
            $answer = $quiz[$index]['Question']['answer'];
        }

        return $answer;
    }

    private function getCurrentImportance($quiz, $index) {
        $importance = self::DEFAULT_IMPORTANCE;

        if (isset($quiz[$index]['Question']['importance'])) {
            $importance = $quiz[$index]['Question']['importance'];
        }

        return $importance;
    }

    public function admin($id) {

        $questions = $this->Quiz->Question->getQuestionsByQuizId($id, array(

            ));
        $this->set('questions', $questions);
        $this->set('quiz', $this->Quiz->findById($id));
    }

    public function overview() {
        $this->loadModel('QuizResult');

        $this->set('results', $this->QuizResult->find('all', array(
                'limit' => 40,
                'order' => 'created desc'
            )));
    }

    // TODO: remove the nasty conversion from Quiz to QuestionQioz
    // TODO: Log something meaninful in logUser
    public function addQuestion() {
        if (!$this->canEditQuiz) {
            $this->abuse("Not authorized to edit quiz");
            return $this->redirect($this->referer());
        }

        if ($this->request->is('post')) {
            $this->loadModel('QuestionQuiz');
            $this->QuestionQuiz->create();
            $data = array();
            $data['QuestionQuiz'] = $this->request->data['Quiz'];
            if ($this->QuestionQuiz->save($data)) {
                $this->customFlash(__('Frågan har lagts till i quizen.'));
                $this->logUser('add', $this->QuestionQuiz->getLastInsertId(), "");
            } else {
                $this->customFlash(__('Kunde inte lägga till frågan till quizen.'), 'danger');
                $this->Session->write('validationErrors', $this->QuestionQuiz->validationErrors);
            }

            return $this->redirect($this->referer());
        }
    }

    public function deleteQuestion($id) {
        if (!$this->canEditQuiz) {
            $this->abuse("Not authorized to delete relation between question and quiz with id " . $id);
            return $this->redirect($this->referer());
        }

        $this->loadModel('QuestionQuiz');
        $this->QuestionQuiz->set(
            array('id' => $id,
                  'deleted' => true));

        if ($this->QuestionQuiz->save()) {
            $this->customFlash(__('Tog bort frågan i quizen med id: %s.', h($id)));
            $this->logUser('delete', $id);
        } else {
            $this->customFlash(__('Kunde inte ta bort frågan som hör till quizen.'), 'danger');
        }

        return $this->redirect($this->referer());
    }

    public function isAuthorized($user) {
        $role = $user['Role']['name'];

        if ($role == 'admin' && in_array($this->action, array('admin', 'deleteQuestion', 'addQuestion'))) {
            return true;
        }

        return parent::isAuthorized($user);
    }

    public function logUser($action, $object_id, $text = "") {
        UserLogger::write(array('model' => 'quiz', 'action' => $action,
                                'user_id' => $this->Auth->user('id'), 'object_id' => $object_id, 'text' => $text, 'ip' => $this->request->clientIp()));
    }
}

?>