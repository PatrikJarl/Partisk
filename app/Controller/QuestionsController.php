<?php
/**
 * Copyright 2013-2014 Partisk.nu Team
 * https://www.partisk.nu/
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * @copyright   Copyright 2013-2014 Partisk.nu Team
 * @link        https://www.partisk.nu
 * @package     app.Controller
 * @license     http://opensource.org/licenses/MIT MIT
 */

App::uses('AppController', 'Controller', 'UserLogger', 'Log');

class QuestionsController extends AppController {
    public $helpers = array('Html', 'Form', 'Cache');
    public $cacheAction = "1 hour";

    public $components = array('Session');

    public function beforeRender() {
        parent::beforeRender();
        $this->set("currentPage", "questions");
    }

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow(array('search'));
    }

    public function index() {
        if(!$this->isLoggedIn) {
            $questions = $this->Question->getVisibleQuestions();
        }  else {  
            $questions = $this->Question->getLoggedInQuestions();
        }

        $this->loadModel('Party');
        $parties = $this->Party->getPartiesOrdered();
        
        $questionIds = $this->Question->getIdsFromModel('Question', $questions);
        $partyIds = $this->Party->getIdsFromModel('Party', $parties);
        
        $answersConditions = array('deleted' => false, 'partyId' => $partyIds, 'questionId' => $questionIds);

        if(!$this->isLoggedIn) {
            $answersConditions['approved'] = true;
        }   
        
        $this->loadModel('Answer');
        $answers = $this->Answer->getAnswers($answersConditions);
        $answersMatrix = $this->Answer->getAnswersMatrix($questions, $answers);
        
        $this->set('questions', $questions);
        $this->set('parties', $parties);
        $this->set('answers', $answersMatrix);
        $this->set('title_for_layout', 'Frågor');
    }

    public function view($title = null) {
        $title = $this->deSlugUrl($title);
        if (!$title) {
            throw new NotFoundException(__('Ogiltig fråga'));
        }

        $question = $this->Question->getByIdOrTitle(urldecode($title));

        if (empty($question)) {
            return $this->redirect(array('controller' => 'questions', 'action' => 'index'));
        }

        $conditions = array('questionId' => $question['Question']['id'], 'includeParty' => true);
        
        if (!$this->Auth->loggedIn()) {
            $conditions['approved'] = true;
        }
        
        $answers = $this->Question->Answer->getAnswers($conditions);
        
        $this->set('question', $question);
        $this->set('answers', $answers);
        $this->set('title_for_layout', ucfirst($question['Question']['title']));
     }

     public function add() {
        if (!$this->canAddQuestion) {
            $this->abuse("Not authorized to add question");
            return $this->redirect($this->referer());
        }

        if ($this->request->is('post')) {
            $this->createQuestion($this->request->data);
            return $this->redirect($this->referer());
        }
     }

     public function delete($id) {
        if (!$this->userCanDeleteQuestion($this->Auth->user('id'), $id)) {
            $this->abuse("Not authorized to delete question with id " . $id);
            return $this->redirect($this->referer());
        }
        
        if ($this->request->is('post') || $this->request->is('put')) {
            $this->deleteQuestion($id);                    
            return $this->redirect($this->referer());
        }
        
        if (!$id) {
            throw new NotFoundException("Ogiltig fråga");
        }

        $question = $this->Question->getByIdOrTitle($id);
        
        if (empty($question)) {
            throw new NotFoundException("Ogiltig fråga");
        }
        
        if (!$this->request->data) {
            $this->request->data = $question;
        }
        
        $this->set('question', $question);        
        $this->renderModal('deleteQuestionModal', array('setAjax' => true));
     }

     public function edit($id = null) { 
        if (empty($id)) {
            $id = $this->request->data['Question']['id'];
        }
        
        if (!$this->userCanEditQuestion($this->Auth->user('id'), $id)) {
            $this->abuse("Not authorized to edit question with id " . $id);
            return $this->redirect($this->referer());
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            $this->saveQuestion($this->request->data);
            return $this->redirect($this->referer());
        } 

        if (!$id) {
            throw new NotFoundException("Ogiltig fråga");
        }

        $question = $this->Question->getByIdOrTitle($id);

        if (empty($question)) {
            throw new NotFoundException("Ogiltig fråga");
        }

        $question['Question']['tags'] = $this->Question->Tag->getTagStringByQuestionid($id);

        $this->set('question', $question);

        $this->renderModal('saveQuestion', array(
            'setEdit' => true,
            'setModal' => true,
            'setAjax' => true,));
           
    }

    public function all() {
        return $this->Question->getAllQuestionsList($this->Auth->loggedIn());
    }

    public function isAuthorized($user) {
        $role = $user['Role']['name'];

        if ($role == 'moderator' && in_array($this->action, array('edit', 'add', 'delete', 'status', 'addTags'))) {
            return true;
        }

        if ($role == 'contributor' && in_array($this->action, array('edit', 'add', 'delete', 'status'))) {
            return true;
        }
        
        if ($role == 'inactive' && in_array($this->action, array('status'))) {
            return true;
        }

        return parent::isAuthorized($user);
    }

    private function createQuestion($data) {
        $this->Question->create();
        $data['Question']['created_by'] = $this->Auth->user('id');
        $data['Question']['created_date'] = date('c');
        $data['Question']['type'] = "YESNO";

        if ($this->Question->save($data)) {
            if ($this->canAddTag) {
                $this->Question->Tag->addTags($data, $this->Question->getLastInsertId());
            }
            $this->customFlash(__('Frågan skapad.'));
            $this->logUser('add', $this->Question->getLastInsertId(), $data['Question']['title']);
        } else {
            $this->customFlash(__('Kunde inte skapa frågan.'), 'danger');
            $this->Session->write('validationErrors', array('Question' => $this->Question->validationErrors, 'mode' => 'create'));
            $this->Session->write('formData', $this->data);
        }
    }

    private function saveQuestion($data) {
        $id = $data['Question']['id'];
        $this->Question->Tag->addTags($data, $id);

        $data['Question']['updated_by'] = $this->Auth->user('id');
        $data['Question']['updated_date'] = date('c');
        $data['Question']['approved'] = isset($data['Question']['approved']) ? $data['Question']['approved'] : false;
        
        $existingQuestion = $this->Question->getByIdOrTitle($id);
       
        if ($existingQuestion['Question']['approved'] !== $data['Question']['approved']) {
            $data['Question']['approved'] = $data['Question']['approved'];
            $data['Question']['approved_by'] = $this->Auth->user('id');
            $data['Question']['approved_date'] = date('c');
        } 
        
        if ($this->Question->save($data)) {
            $this->customFlash(__('Frågan har uppdaterats.'));
            $this->logUser('edit', $id);
        } else {
            $this->customFlash(__('Kunde inte uppdatera frågan.'), 'danger');
            $this->Session->write('validationErrors', array('Question' => $this->Question->validationErrors, 'mode' => 'update'));
            $this->Session->write('formData', $this->data);
        }
    }

    private function deleteQuestion($id) {
        $this->Question->set(
            array('id' => $id,
                  'deleted' => true,
                  'updated_by' => $this->Auth->user('id'),
                  'update_date' => date('c')));

        if ($this->Question->save()) {
            $this->customFlash(__('Tog bort frågan med id: %s.', h($id)));
            $this->logUser('delete', $id);
        } else {
            $this->customFlash(__('Kunde inte ta bort frågan.'), 'danger');
        }
    }
    
    public function search($string) {
        
        $this->layout = 'ajax';
        $this->autoRender=false;
        
        echo json_encode($this->Question->searchQuestion($string, $this->isLoggedIn));

    }

    public function logUser($action, $object_id, $text = "") {
        UserLogger::write(array('model' => 'question', 'action' => $action,
                                'user_id' => $this->Auth->user('id'), 'object_id' => $object_id, 'text' => $text, 'ip' => $this->request->clientIp()));
    }
    
    public function status() {
        $this->set('questions', $this->Question->getUserQuestions($this->Auth->user('id')));
    }
}

?>