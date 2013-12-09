<?php 
/** 
 * Quiz result model
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
 * @package     app.Model
 * @license     http://www.gnu.org/licenses/ GPLv2
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