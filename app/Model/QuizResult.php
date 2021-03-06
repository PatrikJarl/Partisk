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
 * @package     app.Model
 * @license     http://opensource.org/licenses/MIT MIT
 */

class QuizResult extends AppModel {
    public $belongsTo = array(
        'Quiz' => array(
            'className' => 'Quiz', 
            'foreignKey' => 'quiz_id'
        )
    );
        
    public function getQuizResults() {
        $result = Cache::read('latest', 'result');
        if (!$result) {
            $result = $this->find('all', array(
                    'limit' => 40,
                    'order' => 'created desc'
                ));
            Cache::write('latest', $result, 'result');
        }
        
        return $result;
    }
    
    public function getById($id) {
        $result = Cache::read('result_' . $id, 'result');
        if (!$result) {
            $result = $this->findById($id);
            Cache::write('result_' . $id, $result, 'result');
        }
        
        return $result;
    }
}

?>